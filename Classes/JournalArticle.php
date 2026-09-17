<?php

require_once __DIR__ . '/PublicationIssue.php';

class JournalArticle{
    private int $journalID;
    private int $publicationID;
    private string $title;
    private string $journalPDF;

    public function __construct(int $journalID = 0, string $title = '', string $journalPDF = '', int $publicationID = 0) {
        $this->journalID = $journalID;
        $this->publicationID = $publicationID;
        $this->title = $title;
        $this->journalPDF = $journalPDF;
    }

    public function getJournalID(): int{
        return $this->journalID;
    }

    public function getPublicationID(): int{
        return $this->publicationID;
    }

    public function getTitle(): string{
        return $this->title;
    }

    public function getJournalPDF(): string{
        return $this->journalPDF;
    }

    public function setJournalID(int $journalID): void{
        $this->journalID = $journalID;
    }

    public function setPublicationID(int $publicationID): void{
        $this->publicationID = $publicationID;
    }

    public function setTitle(string $title): void{
        $this->title = $title;
    }

    public function setJournalPDF(string $journalPDF): void{
        $this->journalPDF = $journalPDF;
    }

    public function addJournal(PDO $pdo,int $year,int $volume,int $number,string $publicationPDF,array $articles,bool $isDraft = true): int {
        if ($year <= 0) {
            throw new InvalidArgumentException('Invalid publication year.');
        }

        if ($volume <= 0) {
            throw new InvalidArgumentException('Invalid volume number.');
        }

        if ($number <= 0) {
            throw new InvalidArgumentException('Invalid issue number.');
        }

        if (empty($articles)) {
            throw new InvalidArgumentException(
                'At least one article is required.'
            );
        }

        foreach ($articles as $article) {
            if (empty($article['title']) ||!isset($article['authors']) ||!is_array($article['authors']) ||empty($article['authors'])) {
                throw new InvalidArgumentException('Each article must have a title and at least one author.');
            }
        }

        $isCurrent = !$isDraft;

        try {
            $pdo->beginTransaction();

            $publicationIssue = new PublicationIssue();
            $publicationID = $publicationIssue->createIssue(
                $pdo,
                $year,
                $volume,
                $number,
                $publicationPDF,
                $isCurrent,
                $isDraft
            );

            /*Create articles*/
            foreach ($articles as $article) {
                $title = trim($article['title']);
                $journalPDF = $article['journalPDF'] ?? '';

                $stmt = $pdo->prepare(
                    'INSERT INTO "JournalArticle"
                    (
                        "publicationID",
                        "title",
                        "journalPDF"
                    )
                    VALUES
                    (
                        :publicationID,
                        :title,
                        :journalPDF
                    )
                    RETURNING "journalID"'
                );

                $stmt->execute([
                    ':publicationID' => $publicationID,
                    ':title' => $title,
                    ':journalPDF' => $journalPDF
                ]);

                $journalID = (int) $stmt->fetchColumn();

                /*
                 * Save authors and their relationship
                 * with this article.
                 */
                $this->saveAuthors(
                    $pdo,
                    $journalID,
                    $article['authors']
                );
            }

            $pdo->commit();

            return $publicationID;

        } 
        
        catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateJournal(PDO $pdo,int $journalID,int $year,int $volume,int $number,string $title,?string $journalPDF = null,?string $publicationPDF = null,?array $authors = null): bool {
        if ($journalID <= 0) {
            throw new InvalidArgumentException(
                'Invalid journal ID.'
            );
        }

        /*Get the existing article and publication issue*/
        $stmt = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID",
                pi."year",
                pi."volume",
                pi."number",
                pi."publicationPDF",
                pi."is_current",
                pi."is_draft"
             FROM "JournalArticle" ja
             INNER JOIN "PublicationIssue" pi
                ON ja."publicationID" = pi."publicationID"
             WHERE ja."journalID" = :journalID'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        $existingArticle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingArticle) {
            throw new RuntimeException(
                'Journal article not found.'
            );
        }

        /*
         * Only draft articles can be edited
         *
         * NOTE: This still restricts editing to draft issues only.
         * If current-issue editing is required, this check should
         * be removed to match removeJournal() below.
         */
        if (!(bool) $existingArticle['is_draft']) {
            throw new RuntimeException(
                'Only draft articles can be updated.'
            );
        }

        $this->setJournalID($journalID);
        $this->setTitle(trim($title));

        /*
         * If no new article PDF was supplied,
         * keep the article's existing PDF.
         */
        if ($journalPDF === null || $journalPDF === '') {
            $journalPDF = $existingArticle['journalPDF'];
        }

        $this->setJournalPDF($journalPDF);

        $publicationID = (int) $existingArticle['publicationID'];

        try {
            $pdo->beginTransaction();

            /*
             * Update PublicationIssue through the dedicated issue class.
             * The database column remains "publicationPDF" to avoid renaming the live schema.
             */
            $publicationIssue = new PublicationIssue();
            $publicationIssue->updateIssue(
                $pdo,
                $publicationID,
                $year,
                $volume,
                $number,
                $publicationPDF
            );

            /*Update JournalArticle.*/
            $stmt = $pdo->prepare(
                'UPDATE "JournalArticle"
                 SET
                    "title" = :title,
                    "journalPDF" = :journalPDF
                 WHERE "journalID" = :journalID'
            );

            $stmt->execute([
                ':title' => $this->title,
                ':journalPDF' => $this->journalPDF,
                ':journalID' => $this->journalID
            ]);

            /*Update authors only when supplied.*/
            if ($authors !== null) {
                if (empty($authors)) {
                    throw new InvalidArgumentException(
                        'An article must have at least one author.'
                    );
                }

                $this->saveAuthors(
                    $pdo,
                    $this->journalID,
                    $authors
                );
            }

            $pdo->commit();

            return true;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /*Remove Journal Article*/
    public function removeJournal(PDO $pdo,int $journalID): bool {
        if ($journalID <= 0) {
            throw new InvalidArgumentException(
                'Invalid journal ID.'
            );
        }

        /*Get article and publication information.*/
        $stmt = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."publicationID"
             FROM "JournalArticle" ja
             WHERE ja."journalID" = :journalID'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            throw new RuntimeException(
                'Journal article not found.'
            );
        }

        $publicationID = (int) $article['publicationID'];

        try {
            $pdo->beginTransaction();

            /*Remove author relationships first*/
            $stmt = $pdo->prepare(
                'DELETE FROM "ArticleAuthor"
                 WHERE "journalID" = :journalID'
            );

            $stmt->execute([
                ':journalID' => $journalID
            ]);

            /*Remove the article.*/
            $stmt = $pdo->prepare(
                'DELETE FROM "JournalArticle"
                 WHERE "journalID" = :journalID'
            );

            $stmt->execute([
                ':journalID' => $journalID
            ]);

            /*If this was the last article in the publication issue,
              remove the issue details as well.*/
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM "JournalArticle"
                 WHERE "publicationID" = :publicationID'
            );

            $stmt->execute([
                ':publicationID' => $publicationID
            ]);

            if ((int) $stmt->fetchColumn() === 0) {
                $stmt = $pdo->prepare(
                    'DELETE FROM "PublicationIssue"
                     WHERE "publicationID" = :publicationID'
                );

                $stmt->execute([
                    ':publicationID' => $publicationID
                ]);
            }

            $pdo->commit();

            return true;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /*View Journal Article*/
    public function viewJournal(PDO $pdo,int $journalID): ?array {
        $stmt = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID",
                pi."year",
                pi."volume",
                pi."number",
                pi."publicationPDF",
                pi."is_current",
                pi."is_draft"
             FROM "JournalArticle" ja
             INNER JOIN "PublicationIssue" pi
                ON ja."publicationID" = pi."publicationID"
             WHERE ja."journalID" = :journalID'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            return null;
        }

        /*Get authors*/
        $stmt = $pdo->prepare(
            'SELECT
                a."authorID",
                a."firstName",
                a."lastName"
             FROM "ArticleAuthor" aa
             INNER JOIN "Author" a
                ON aa."authorID" = a."authorID"
             WHERE aa."journalID" = :journalID
             ORDER BY a."lastName", a."firstName"'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        $article['authors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $article;
    }

    public function readJournal(PDO $pdo,int $journalID): ?string {
        $stmt = $pdo->prepare(
            'SELECT "journalPDF"
             FROM "JournalArticle"
             WHERE "journalID" = :journalID'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        $journalPDF = $stmt->fetchColumn();

        if ($journalPDF === false) {
            return null;
        }

        return (string) $journalPDF;
    }

    public function downloadPDF(PDO $pdo,int $journalID): ?string {
        $stmt = $pdo->prepare(
            'SELECT "journalPDF"
             FROM "JournalArticle"
             WHERE "journalID" = :journalID'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        $journalPDF = $stmt->fetchColumn();

        if ($journalPDF === false) {
            return null;
        }

        return (string) $journalPDF;
    }

    /*Search Journal Articles*/
    public function searchJournal(PDO $pdo,string $keyword): array {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID",
                pi."year",
                pi."volume",
                pi."number"
             FROM "JournalArticle" ja
             INNER JOIN "PublicationIssue" pi
                ON ja."publicationID" = pi."publicationID"
             WHERE
                ja."title" ILIKE :keyword
             ORDER BY
                pi."year" DESC,
                pi."volume" DESC,
                pi."number" DESC,
                ja."title" ASC'
        );

        $stmt->execute([
            ':keyword' => '%' . $keyword . '%'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateCitation(array $article,array $publication,array $authors): string {
        $authorNames = [];

        foreach ($authors as $author) {
            $firstName = trim($author['firstName'] ?? '');
            $lastName = trim($author['lastName'] ?? '');

            if ($lastName === '') {
                continue;
            }

            if ($firstName !== '') {
                $initial = strtoupper(
                    mb_substr($firstName, 0, 1)
                ) . '.';

                $authorNames[] =
                    $lastName . ', ' . $initial;
            } else {
                $authorNames[] = $lastName;
            }
        }

        if (empty($authorNames)) {
            $authorText = '';
        } 
        
        elseif (count($authorNames) === 1) {
            $authorText = $authorNames[0];
        } 
        
        elseif (count($authorNames) === 2) {
            $authorText =
                $authorNames[0] . ', & ' . $authorNames[1];
        } 
        
        else {
            $lastAuthor = array_pop($authorNames);

            $authorText =
                implode(', ', $authorNames) .
                ', & ' .
                $lastAuthor;
        }

        $title = trim($article['title'] ?? '');

        $year = $publication['year'] ?? '';
        $volume = $publication['volume'] ?? '';
        $number = $publication['number'] ?? '';

        $citation = '';

        if ($authorText !== '') {
            $citation .= $authorText . ' ';
        }

        if ($year !== '') {
            $citation .= '(' . $year . '). ';
        }

        $citation .= $title . '. ';

        if ($volume !== '') {
            $citation .= 'Convergence, ' . $volume;

            if ($number !== '') {
                $citation .= '(' . $number . ')';
            }

            $citation .= '.';
        }

        return trim($citation);
    }

    public function listJournals(PDO $pdo): array {
        $stmt = $pdo->query(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID",
                pi."year",
                pi."volume",
                pi."number",
                pi."publicationPDF",
                pi."is_current",
                pi."is_draft"
             FROM "JournalArticle" ja
             INNER JOIN "PublicationIssue" pi
                ON ja."publicationID" = pi."publicationID"
             ORDER BY
                pi."year" DESC,
                pi."volume" DESC,
                pi."number" DESC,
                ja."journalID" ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function saveAuthors(PDO $pdo,int $journalID,array $authors): void {

        $stmt = $pdo->prepare(
            'DELETE FROM "ArticleAuthor"
             WHERE "journalID" = :journalID'
        );

        $stmt->execute([
            ':journalID' => $journalID
        ]);

        foreach ($authors as $author) {
            $firstName = trim($author['firstName'] ?? '');
            $lastName = trim($author['lastName'] ?? '');

            if ($firstName === '' && $lastName === '') {
                continue;
            }

            if ($lastName === '') {
                throw new InvalidArgumentException(
                    'Author last name is required.'
                );
            }

            /*Find an existing author*/
            $authorID = $this->findAuthor(
                $pdo,
                $firstName,
                $lastName
            );

            /*Create the author if one does not exist.*/
            if ($authorID === null) {
                $authorID = $this->createAuthor(
                    $pdo,
                    $firstName,
                    $lastName
                );
            }

            /*Create ArticleAuthor relationship.*/
            $stmt = $pdo->prepare(
                'INSERT INTO "ArticleAuthor"
                (
                    "journalID",
                    "authorID"
                )

                VALUES
                (
                    :journalID,
                    :authorID
                )'
            );

            $stmt->execute([
                ':journalID' => $journalID,
                ':authorID' => $authorID
            ]);
        }
    }

    private function findAuthor(PDO $pdo, string $firstName,string $lastName): ?int 
    {
        $stmt = $pdo->prepare(
            'SELECT "authorID"
             FROM "Author"
             WHERE
                LOWER("firstName") = LOWER(:firstName)
                AND LOWER("lastName") = LOWER(:lastName)
             LIMIT 1'
        );

        $stmt->execute([
            ':firstName' => $firstName,
            ':lastName' => $lastName
        ]);

        $authorID = $stmt->fetchColumn();

        if ($authorID === false) {
            return null;
        }

        return (int) $authorID;
    }

    private function createAuthor(PDO $pdo, string $firstName, string $lastName): int {
        $stmt = $pdo->prepare(
            'INSERT INTO "Author"
            (
                "firstName",
                "lastName"
            )
            VALUES
            (
                :firstName,
                :lastName
            )
            RETURNING "authorID"'
        );

        $stmt->execute([
            ':firstName' => $firstName,
            ':lastName' => $lastName
        ]);

        return (int) $stmt->fetchColumn();
    }
}
<?php

class JournalArticle
{
    private int $journalID;
    private string $title;
    private string $journalPDF;

    public function __construct(int $journalID = 0,string $title = '',string $journalPDF = '') {
        $this->journalID = $journalID;
        $this->title = $title;
        $this->journalPDF = $journalPDF;
    }

    /*Add Journal*/
    public function addJournal(PDO $pdo,int $year,int $volume,int $number,array $publicationPdf,array $articles): int {

        if ($year <= 0 || $volume <= 0 || $number <= 0) {
            throw new Exception('Invalid publication issue information.');
        }

        if (empty($publicationPdf)) {
            throw new Exception('Publication issue PDF is required.');
        }

        if (empty($articles)) {
            throw new Exception('At least one article is required.');
        }

        validatePdf($publicationPdf,'Publication issue PDF');

        $publicationID = null;
        $uploadedFiles = [];
        $authorCache = [];

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Create Publication Issue
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'INSERT INTO "PublicationIssue"
                ("year", "volume", "number")
                VALUES (:year, :volume, :number)
                RETURNING "publicationID"'
            );

            $statement->execute([
                ':year' => $year,
                ':volume' => $volume,
                ':number' => $number
            ]);

            $publicationID = (int) $statement->fetchColumn();

            if ($publicationID <= 0) {
                throw new Exception(
                    'Unable to create publication issue.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Publication Issue PDF
            |--------------------------------------------------------------------------
            */

            $publicationFilename =
                createStorageFilename(
                    $publicationPdf['name'],
                    'publication_issue'
                );

            $publicationPath =
                $year .
                '/publication_' .
                $publicationID .
                '/' .
                $publicationFilename;

            $uploads = [];

            $uploads[] = [
                'localFile' =>
                    $publicationPdf['tmp_name'],

                'storagePath' =>
                    $publicationPath,

                'description' =>
                    'Publication issue PDF'
            ];

            /*
            |--------------------------------------------------------------------------
            | Article PDFs
            |--------------------------------------------------------------------------
            */

            foreach ($articles as $index => $article) {

                $articleNumber =
                    $index + 1;

                $articleTitle =
                    trim(
                        $article['title'] ?? ''
                    );

                if ($articleTitle === '') {
                    throw new Exception(
                        'Title for Article ' .
                        $articleNumber .
                        ' is required.'
                    );
                }

                $fileKey =
                    'pdf_' . $index;

                if (!isset($_FILES[$fileKey])) {
                    throw new Exception(
                        'PDF for Article ' .
                        $articleNumber .
                        ' is missing.'
                    );
                }

                $pdf =
                    $_FILES[$fileKey];

                validatePdf(
                    $pdf,
                    'PDF for Article ' .
                    $articleNumber
                );

                $articleFilename =
                    createStorageFilename(
                        $pdf['name'],
                        'article'
                    );

                $articlePath =
                    $year .
                    '/publication_' .
                    $publicationID .
                    '/' .
                    $articleFilename;

                $uploads[] = [
                    'localFile' =>
                        $pdf['tmp_name'],

                    'storagePath' =>
                        $articlePath,

                    'description' =>
                        'Article ' .
                        $articleNumber .
                        ' PDF'
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Upload Files
            |--------------------------------------------------------------------------
            */

            uploadPdfsConcurrently(
                $uploads
            );

            foreach ($uploads as $upload) {
                $uploadedFiles[] =
                    $upload['storagePath'];
            }

            /*
            |--------------------------------------------------------------------------
            | Save Publication PDF
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'UPDATE "PublicationIssue"
                SET "publicationPDF" = :publicationPDF
                WHERE "publicationID" = :publicationID'
            );

            $statement->execute([
                ':publicationPDF' =>
                    $uploads[0]['storagePath'],

                ':publicationID' =>
                    $publicationID
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Journal Articles
            |--------------------------------------------------------------------------
            */

            foreach ($articles as $index => $article) {

                $this->title =
                    trim(
                        $article['title']
                    );

                $this->journalPDF =
                    $uploads[$index + 1]['storagePath'];

                /*
                | Create Journal
                */

                $statement = $pdo->prepare(
                    'INSERT INTO "JournalArticle"
                    ("title", "journalPDF", "publicationID")
                    VALUES (:title, :journalPDF, :publicationID)
                    RETURNING "journalID"'
                );

                $statement->execute([
                    ':title' =>
                        $this->title,

                    ':journalPDF' =>
                        $this->journalPDF,

                    ':publicationID' =>
                        $publicationID
                ]);

                $this->journalID =
                    (int) $statement->fetchColumn();

                if ($this->journalID <= 0) {
                    throw new Exception(
                        'Unable to create journal article.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Authors
                |--------------------------------------------------------------------------
                */

                $authors =
                    $article['authors'] ?? [];

                if (
                    !is_array($authors) ||
                    count($authors) === 0
                ) {
                    throw new Exception(
                        'Article ' .
                        ($index + 1) .
                        ' must have at least one author.'
                    );
                }

                foreach ($authors as $author) {

                    $firstName =
                        trim(
                            $author['firstName'] ?? ''
                        );

                    $lastName =
                        trim(
                            $author['lastName'] ?? ''
                        );

                    if (
                        $firstName === '' ||
                        $lastName === ''
                    ) {
                        throw new Exception(
                            'Author first name and last name are required.'
                        );
                    }

                    $authorID =
                        $this->findOrCreateAuthor(
                            $pdo,
                            $firstName,
                            $lastName,
                            $authorCache
                        );

                    $statement =
                        $pdo->prepare(
                            'INSERT INTO "ArticleAuthor"
                            ("journalID", "authorID")
                            VALUES (:journalID, :authorID)'
                        );

                    $statement->execute([
                        ':journalID' =>
                            $this->journalID,

                        ':authorID' =>
                            $authorID
                    ]);
                }
            }

            $pdo->commit();

            return $publicationID;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($uploadedFiles as $uploadedFile) {

                try {
                    deletePdf($uploadedFile);
                } catch (Throwable $cleanupError) {
                    error_log(
                        'Storage cleanup error: ' .
                        $cleanupError->getMessage()
                    );
                }
            }

            throw $e;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Journal
    |--------------------------------------------------------------------------
    */

    public function updateJournal(
        PDO $pdo,
        int $journalID,
        int $year,
        int $volume,
        int $number,
        string $title,
        ?array $journalPdf = null,
        ?array $publicationPdf = null,
        ?array $authors = null
    ): void {

        if ($journalID <= 0) {
            throw new Exception(
                'Invalid journal ID.'
            );
        }

        $this->journalID =
            $journalID;

        $this->title =
            trim($title);

        if ($this->title === '') {
            throw new Exception(
                'Journal title is required.'
            );
        }

        if ($year <= 0 || $volume <= 0 || $number <= 0) {
            throw new Exception(
                'Invalid publication issue information.'
            );
        }

        $oldArticlePdf = null;
        $oldPublicationPdf = null;

        $newArticlePdfPath = null;
        $newPublicationPdfPath = null;

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Get Existing Journal
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'SELECT
                    ja."journalID",
                    ja."title",
                    ja."journalPDF",
                    ja."publicationID",
                    pi."year",
                    pi."volume",
                    pi."number",
                    pi."publicationPDF"
                FROM "JournalArticle" ja
                INNER JOIN "PublicationIssue" pi
                    ON ja."publicationID" =
                       pi."publicationID"
                WHERE ja."journalID" = :journalID
                FOR UPDATE'
            );

            $statement->execute([
                ':journalID' =>
                    $this->journalID
            ]);

            $current =
                $statement->fetch();

            if (!$current) {
                throw new Exception(
                    'Journal article not found.'
                );
            }

            $publicationID =
                (int) $current['publicationID'];

            $oldArticlePdf =
                $current['journalPDF'];

            $oldPublicationPdf =
                $current['publicationPDF'];


            /*
            |--------------------------------------------------------------------------
            | New Journal PDF
            |--------------------------------------------------------------------------
            */

            if ($journalPdf !== null) {

                validatePdf(
                    $journalPdf,
                    'Journal article PDF'
                );

                $newArticlePdfPath =
                    $year .
                    '/publication_' .
                    $publicationID .
                    '/' .
                    createStorageFilename(
                        $journalPdf['name'],
                        'article'
                    );

                uploadPdf(
                    $journalPdf['tmp_name'],
                    $newArticlePdfPath
                );

                $this->journalPDF =
                    $newArticlePdfPath;

            } else {

                $this->journalPDF =
                    $oldArticlePdf;
            }


            /*
            |--------------------------------------------------------------------------
            | New Publication PDF
            |--------------------------------------------------------------------------
            */

            if ($publicationPdf !== null) {

                validatePdf(
                    $publicationPdf,
                    'Publication issue PDF'
                );

                $newPublicationPdfPath =
                    $year .
                    '/publication_' .
                    $publicationID .
                    '/' .
                    createStorageFilename(
                        $publicationPdf['name'],
                        'publication_issue'
                    );

                uploadPdf(
                    $publicationPdf['tmp_name'],
                    $newPublicationPdfPath
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update Publication Issue
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'UPDATE "PublicationIssue"
                SET
                    "year" = :year,
                    "volume" = :volume,
                    "number" = :number
                WHERE "publicationID" = :publicationID'
            );

            $statement->execute([
                ':year' =>
                    $year,

                ':volume' =>
                    $volume,

                ':number' =>
                    $number,

                ':publicationID' =>
                    $publicationID
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Publication PDF
            |--------------------------------------------------------------------------
            */

            if ($newPublicationPdfPath !== null) {

                $statement = $pdo->prepare(
                    'UPDATE "PublicationIssue"
                    SET "publicationPDF" = :publicationPDF
                    WHERE "publicationID" = :publicationID'
                );

                $statement->execute([
                    ':publicationPDF' =>
                        $newPublicationPdfPath,

                    ':publicationID' =>
                        $publicationID
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Update Journal
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'UPDATE "JournalArticle"
                SET
                    "title" = :title,
                    "journalPDF" = :journalPDF
                WHERE "journalID" = :journalID'
            );

            $statement->execute([
                ':title' =>
                    $this->title,

                ':journalPDF' =>
                    $this->journalPDF,

                ':journalID' =>
                    $this->journalID
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Authors
            |--------------------------------------------------------------------------
            */

            if ($authors !== null) {

                if (
                    !is_array($authors) ||
                    count($authors) === 0
                ) {
                    throw new Exception(
                        'At least one author is required.'
                    );
                }

                $statement = $pdo->prepare(
                    'DELETE FROM "ArticleAuthor"
                    WHERE "journalID" = :journalID'
                );

                $statement->execute([
                    ':journalID' =>
                        $this->journalID
                ]);

                $authorCache = [];

                foreach ($authors as $author) {

                    $firstName =
                        trim(
                            $author['firstName'] ?? ''
                        );

                    $lastName =
                        trim(
                            $author['lastName'] ?? ''
                        );

                    if (
                        $firstName === '' ||
                        $lastName === ''
                    ) {
                        throw new Exception(
                            'Author first name and last name are required.'
                        );
                    }

                    $authorID =
                        $this->findOrCreateAuthor(
                            $pdo,
                            $firstName,
                            $lastName,
                            $authorCache
                        );

                    $statement =
                        $pdo->prepare(
                            'INSERT INTO "ArticleAuthor"
                            ("journalID", "authorID")
                            VALUES (:journalID, :authorID)'
                        );

                    $statement->execute([
                        ':journalID' =>
                            $this->journalID,

                        ':authorID' =>
                            $authorID
                    ]);
                }
            }

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Delete Old Files
            |--------------------------------------------------------------------------
            */

            if ($newArticlePdfPath !== null) {

                try {
                    deletePdf(
                        $oldArticlePdf
                    );
                } catch (Throwable $e) {
                    error_log(
                        'Old article PDF cleanup error: ' .
                        $e->getMessage()
                    );
                }
            }

            if ($newPublicationPdfPath !== null) {

                try {
                    deletePdf(
                        $oldPublicationPdf
                    );
                } catch (Throwable $e) {
                    error_log(
                        'Old publication PDF cleanup error: ' .
                        $e->getMessage()
                    );
                }
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newArticlePdfPath !== null) {

                try {
                    deletePdf(
                        $newArticlePdfPath
                    );
                } catch (Throwable $cleanupError) {
                    error_log(
                        'New article PDF cleanup error: ' .
                        $cleanupError->getMessage()
                    );
                }
            }

            if ($newPublicationPdfPath !== null) {

                try {
                    deletePdf(
                        $newPublicationPdfPath
                    );
                } catch (Throwable $cleanupError) {
                    error_log(
                        'New publication PDF cleanup error: ' .
                        $cleanupError->getMessage()
                    );
                }
            }

            throw $e;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Journal
    |--------------------------------------------------------------------------
    */

    public function removeJournal(
        PDO $pdo,
        int $journalID
    ): void {

        if ($journalID <= 0) {
            throw new Exception(
                'Invalid journal ID.'
            );
        }

        $this->journalID =
            $journalID;

        $journalPDF = null;
        $publicationPDF = null;
        $publicationID = null;

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Find Journal
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'SELECT
                    "journalID",
                    "journalPDF",
                    "publicationID"
                FROM "JournalArticle"
                WHERE "journalID" = :journalID
                FOR UPDATE'
            );

            $statement->execute([
                ':journalID' =>
                    $this->journalID
            ]);

            $journal =
                $statement->fetch();

            if (!$journal) {
                throw new Exception(
                    'Journal article not found.'
                );
            }

            $journalPDF =
                $journal['journalPDF'];

            $publicationID =
                (int) $journal['publicationID'];


            /*
            |--------------------------------------------------------------------------
            | Delete ArticleAuthor
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'DELETE FROM "ArticleAuthor"
                WHERE "journalID" = :journalID'
            );

            $statement->execute([
                ':journalID' =>
                    $this->journalID
            ]);


            /*
            |--------------------------------------------------------------------------
            | Delete Journal
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'DELETE FROM "JournalArticle"
                WHERE "journalID" = :journalID'
            );

            $statement->execute([
                ':journalID' =>
                    $this->journalID
            ]);


            /*
            |--------------------------------------------------------------------------
            | Check if Publication Issue is Empty
            |--------------------------------------------------------------------------
            */

            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                FROM "JournalArticle"
                WHERE "publicationID" = :publicationID'
            );

            $statement->execute([
                ':publicationID' =>
                    $publicationID
            ]);

            $remainingArticles =
                (int) $statement->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Delete Publication Issue
            |--------------------------------------------------------------------------
            */

            if ($remainingArticles === 0) {

                $statement = $pdo->prepare(
                    'SELECT "publicationPDF"
                    FROM "PublicationIssue"
                    WHERE "publicationID" = :publicationID'
                );

                $statement->execute([
                    ':publicationID' =>
                        $publicationID
                ]);

                $publicationPDF =
                    $statement->fetchColumn();


                $statement = $pdo->prepare(
                    'DELETE FROM "PublicationIssue"
                    WHERE "publicationID" = :publicationID'
                );

                $statement->execute([
                    ':publicationID' =>
                        $publicationID
                ]);
            }

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Delete Storage Files
            |--------------------------------------------------------------------------
            */

            if ($journalPDF) {
                deletePdf(
                    $journalPDF
                );
            }

            if ($publicationPDF) {
                deletePdf(
                    $publicationPDF
                );
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | View Journal
    |--------------------------------------------------------------------------
    */

    public function viewJournal(
        PDO $pdo,
        int $journalID
    ): ?array {

        if ($journalID <= 0) {
            throw new Exception(
                'Invalid journal ID.'
            );
        }

        $this->journalID =
            $journalID;


        /*
        |--------------------------------------------------------------------------
        | Get Journal
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID",
                pi."year",
                pi."volume",
                pi."number",
                pi."publicationPDF"
            FROM "JournalArticle" ja
            INNER JOIN "PublicationIssue" pi
                ON ja."publicationID" =
                   pi."publicationID"
            WHERE ja."journalID" = :journalID'
        );

        $statement->execute([
            ':journalID' =>
                $this->journalID
        ]);

        $article =
            $statement->fetch();

        if (!$article) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Store Object Attributes
        |--------------------------------------------------------------------------
        */

        $this->title =
            $article['title'];

        $this->journalPDF =
            $article['journalPDF'];


        /*
        |--------------------------------------------------------------------------
        | Get Authors
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            'SELECT
                a."authorID",
                a."firstName",
                a."lastName"
            FROM "ArticleAuthor" aa
            INNER JOIN "Author" a
                ON aa."authorID" =
                   a."authorID"
            WHERE aa."journalID" = :journalID
            ORDER BY aa."authorID"'
        );

        $statement->execute([
            ':journalID' =>
                $this->journalID
        ]);

        $authors =
            $statement->fetchAll();


        /*
        |--------------------------------------------------------------------------
        | Publication Information
        |--------------------------------------------------------------------------
        */

        $publication = [

            'publicationID' =>
                (int) $article['publicationID'],

            'year' =>
                (int) $article['year'],

            'volume' =>
                (int) $article['volume'],

            'number' =>
                (int) $article['number'],

            'publicationPDF' =>
                $article['publicationPDF']
        ];


        unset(
            $article['year'],
            $article['volume'],
            $article['number'],
            $article['publicationPDF']
        );


        $article['journalID'] =
            (int) $article['journalID'];

        $article['publicationID'] =
            (int) $article['publicationID'];


        return [

            'article' =>
                $article,

            'publication' =>
                $publication,

            'authors' =>
                $authors
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Read Journal
    |--------------------------------------------------------------------------
    */

    public function readJournal(
        PDO $pdo,
        int $journalID
    ): ?string {

        $result =
            $this->viewJournal(
                $pdo,
                $journalID
            );

        if ($result === null) {
            return null;
        }

        $this->journalID =
            $journalID;

        $this->title =
            $result['article']['title'];

        $this->journalPDF =
            $result['article']['journalPDF'];

        if (
            trim($this->journalPDF) === ''
        ) {
            return null;
        }

        return createPublicPdfUrl(
            $this->journalPDF
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Download PDF
    |--------------------------------------------------------------------------
    */

    public function downloadPDF(
        PDO $pdo,
        int $journalID
    ): ?string {

        $result =
            $this->viewJournal(
                $pdo,
                $journalID
            );

        if ($result === null) {
            return null;
        }

        $this->journalID =
            $journalID;

        $this->title =
            $result['article']['title'];

        $this->journalPDF =
            $result['article']['journalPDF'];

        if (
            trim($this->journalPDF) === ''
        ) {
            return null;
        }

        $pdfUrl =
            createPublicPdfUrl(
                $this->journalPDF
            );

        $filename =
            basename(
                normalizeStoragePath(
                    $this->journalPDF
                )
            );

        return $pdfUrl .
            '?download=' .
            rawurlencode($filename);
    }


    /*
    |--------------------------------------------------------------------------
    | Search Journal
    |--------------------------------------------------------------------------
    */

    public function searchJournal(
        PDO $pdo,
        string $keyword
    ): array {

        $keyword =
            trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $statement = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID",
                pi."year",
                pi."volume",
                pi."number",
                pi."publicationPDF"
            FROM "JournalArticle" ja
            INNER JOIN "PublicationIssue" pi
                ON ja."publicationID" =
                   pi."publicationID"
            WHERE ja."title" ILIKE :keyword
            ORDER BY
                pi."year" DESC,
                pi."volume" DESC,
                pi."number" DESC,
                ja."journalID" DESC'
        );

        $statement->execute([
            ':keyword' =>
                '%' . $keyword . '%'
        ]);

        $rows =
            $statement->fetchAll();

        if (empty($rows)) {
            return [];
        }

        $journalIDs =
            array_map(
                'intval',
                array_column(
                    $rows,
                    'journalID'
                )
            );

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($journalIDs),
                    '?'
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Get Authors
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            'SELECT
                aa."journalID",
                a."authorID",
                a."firstName",
                a."lastName"
            FROM "ArticleAuthor" aa
            INNER JOIN "Author" a
                ON aa."authorID" =
                   a."authorID"
            WHERE aa."journalID" IN (' .
                $placeholders .
                ')'
        );

        $statement->execute(
            $journalIDs
        );

        $authorRows =
            $statement->fetchAll();

        $authorMap = [];

        foreach ($authorRows as $author) {

            $id =
                (int) $author['journalID'];

            if (
                !isset(
                    $authorMap[$id]
                )
            ) {
                $authorMap[$id] = [];
            }

            $authorMap[$id][] = [

                'authorID' =>
                    (int) $author['authorID'],

                'firstName' =>
                    $author['firstName'],

                'lastName' =>
                    $author['lastName']
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Build Results
        |--------------------------------------------------------------------------
        */

        $journals = [];

        foreach ($rows as $row) {

            $this->journalID =
                (int) $row['journalID'];

            $this->title =
                $row['title'];

            $this->journalPDF =
                $row['journalPDF'];

            $journals[] = [

                'id' =>
                    $this->journalID,

                'publicationID' =>
                    (int) $row['publicationID'],

                'year' =>
                    (int) $row['year'],

                'volume' =>
                    (int) $row['volume'],

                'number' =>
                    (int) $row['number'],

                'publicationPDF' =>
                    $row['publicationPDF'],

                'title' =>
                    $this->title,

                'journalPDF' =>
                    $this->journalPDF,

                'authors' =>
                    $authorMap[
                        $this->journalID
                    ] ?? []
            ];
        }

        return $journals;
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Citation
    |--------------------------------------------------------------------------
    */

    public function generateCitation(
        array $article,
        array $publication,
        array $authors
    ): string {

        $this->journalID =
            (int) (
                $article['journalID'] ??
                0
            );

        $this->title =
            trim(
                $article['title'] ??
                'Untitled Article'
            );

        $this->journalPDF =
            $article['journalPDF'] ??
            '';


        /*
        |--------------------------------------------------------------------------
        | Format Authors
        |--------------------------------------------------------------------------
        */

        $authorParts = [];

        foreach ($authors as $author) {

            $firstName =
                trim(
                    $author['firstName'] ??
                    ''
                );

            $lastName =
                trim(
                    $author['lastName'] ??
                    ''
                );

            $initials = '';

            if ($firstName !== '') {

                $nameParts =
                    preg_split(
                        '/\s+/',
                        $firstName
                    );

                foreach ($nameParts as $part) {

                    if ($part === '') {
                        continue;
                    }

                    $initials .=
                        strtoupper(
                            substr(
                                $part,
                                0,
                                1
                            )
                        ) .
                        '. ';
                }

                $initials =
                    trim($initials);
            }

            if ($lastName !== '') {

                if ($initials !== '') {

                    $authorParts[] =
                        $lastName .
                        ', ' .
                        $initials;

                } else {

                    $authorParts[] =
                        $lastName;
                }

            } elseif ($firstName !== '') {

                $authorParts[] =
                    $firstName;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Author Text
        |--------------------------------------------------------------------------
        */

        if (count($authorParts) === 0) {

            $authorText =
                'Unknown Author.';

        } elseif (count($authorParts) === 1) {

            $authorText =
                $authorParts[0] .
                '.';

        } elseif (count($authorParts) === 2) {

            $authorText =
                $authorParts[0] .
                ', & ' .
                $authorParts[1] .
                '.';

        } else {

            $authorText =
                $authorParts[0] .
                ' et al.';
        }


        /*
        |--------------------------------------------------------------------------
        | Publication Information
        |--------------------------------------------------------------------------
        */

        $year =
            $publication['year'] ??
            'n.d.';

        $volume =
            $publication['volume'] ??
            '';

        $number =
            $publication['number'] ??
            '';


        /*
        |--------------------------------------------------------------------------
        | Citation
        |--------------------------------------------------------------------------
        */

        return
            $authorText .
            ' (' .
            $year .
            '). ' .
            $this->title .
            '. Convergence: A Multidisciplinary Journal, ' .
            $volume .
            '(' .
            $number .
            ').';
    }


    /*
    |--------------------------------------------------------------------------
    | List Journals
    |--------------------------------------------------------------------------
    */

    public function listJournals(
        PDO $pdo
    ): array {

        $statement =
            $pdo->query(
                'SELECT
                    ja."journalID",
                    ja."title",
                    ja."journalPDF",
                    ja."publicationID",
                    pi."year",
                    pi."volume",
                    pi."number",
                    pi."publicationPDF"
                FROM "JournalArticle" ja
                INNER JOIN "PublicationIssue" pi
                    ON ja."publicationID" =
                       pi."publicationID"
                ORDER BY
                    pi."year" DESC,
                    pi."volume" DESC,
                    pi."number" DESC,
                    ja."journalID" DESC'
            );

        $rows =
            $statement->fetchAll();

        if (empty($rows)) {
            return [];
        }

        $journalIDs =
            array_map(
                'intval',
                array_column(
                    $rows,
                    'journalID'
                )
            );

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($journalIDs),
                    '?'
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Get Authors
        |--------------------------------------------------------------------------
        */

        $statement =
            $pdo->prepare(
                'SELECT
                    aa."journalID",
                    a."authorID",
                    a."firstName",
                    a."lastName"
                FROM "ArticleAuthor" aa
                INNER JOIN "Author" a
                    ON aa."authorID" =
                       a."authorID"
                WHERE aa."journalID" IN (' .
                    $placeholders .
                    ')'
            );

        $statement->execute(
            $journalIDs
        );

        $authorRows =
            $statement->fetchAll();

        $authorMap = [];

        foreach ($authorRows as $author) {

            $id =
                (int) $author['journalID'];

            if (
                !isset(
                    $authorMap[$id]
                )
            ) {
                $authorMap[$id] = [];
            }

            $authorMap[$id][] = [

                'authorID' =>
                    (int) $author['authorID'],

                'firstName' =>
                    $author['firstName'],

                'lastName' =>
                    $author['lastName']
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Build Journal List
        |--------------------------------------------------------------------------
        */

        $journals = [];

        foreach ($rows as $row) {

            $this->journalID =
                (int) $row['journalID'];

            $this->title =
                $row['title'];

            $this->journalPDF =
                $row['journalPDF'];

            $journals[] = [

                'id' =>
                    $this->journalID,

                'publicationID' =>
                    (int) $row['publicationID'],

                'year' =>
                    (int) $row['year'],

                'volume' =>
                    (int) $row['volume'],

                'number' =>
                    (int) $row['number'],

                'publicationPDF' =>
                    $row['publicationPDF'],

                'title' =>
                    $this->title,

                'journalPDF' =>
                    $this->journalPDF,

                'authors' =>
                    $authorMap[
                        $this->journalID
                    ] ?? []
            ];
        }

        return $journals;
    }


    /*
    |--------------------------------------------------------------------------
    | Find Author
    |--------------------------------------------------------------------------
    */

    private function findAuthor(
        PDO $pdo,
        string $firstName,
        string $lastName
    ): ?int {

        $statement =
            $pdo->prepare(
                'SELECT "authorID"
                FROM "Author"
                WHERE "firstName" = :firstName
                AND "lastName" = :lastName
                LIMIT 1'
            );

        $statement->execute([

            ':firstName' =>
                trim($firstName),

            ':lastName' =>
                trim($lastName)
        ]);

        $authorID =
            $statement->fetchColumn();

        if ($authorID === false) {
            return null;
        }

        return (int) $authorID;
    }


    /*
    |--------------------------------------------------------------------------
    | Create Author
    |--------------------------------------------------------------------------
    */

    private function createAuthor(
        PDO $pdo,
        string $firstName,
        string $lastName
    ): int {

        $statement =
            $pdo->prepare(
                'INSERT INTO "Author"
                ("firstName", "lastName")
                VALUES (:firstName, :lastName)
                RETURNING "authorID"'
            );

        $statement->execute([

            ':firstName' =>
                trim($firstName),

            ':lastName' =>
                trim($lastName)
        ]);

        $authorID =
            $statement->fetchColumn();

        if ($authorID === false) {
            throw new Exception(
                'Unable to create author.'
            );
        }

        return (int) $authorID;
    }


    /*
    |--------------------------------------------------------------------------
    | Find or Create Author
    |--------------------------------------------------------------------------
    */

    private function findOrCreateAuthor(PDO $pdo,string $firstName,string $lastName,array &$authorCache): int {

        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $cacheKey = strtolower($firstName .'|' .$lastName);

        if (isset($authorCache[$cacheKey])) {
            return $authorCache[$cacheKey];
        }

        $authorID =
            $this->findAuthor(
                $pdo,
                $firstName,
                $lastName
            );

        if ($authorID === null) {

            $authorID =
                $this->createAuthor(
                    $pdo,
                    $firstName,
                    $lastName
                );
        }

        $authorCache[$cacheKey] =
            $authorID;

        return $authorID;
    }
}
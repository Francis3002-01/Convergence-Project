<?php

class PublicationIssue
{
    private int $publicationID;
    private int $year;
    private int $volume;
    private int $number;
    private string $editorNotePDF;
    private bool $is_current;

    public function __construct(int $publicationID = 0, int $year = 0, int $volume = 0, int $number = 0, string $editorNotePDF = '', bool $is_current = false)
    {
        $this->publicationID = $publicationID;
        $this->year = $year;
        $this->volume = $volume;
        $this->number = $number;
        $this->editorNotePDF = $editorNotePDF;
        $this->is_current = $is_current;
    }

    public function getPublicationID(): int
    {
        return $this->publicationID;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getEditorNotePDF(): string
    {
        return $this->editorNotePDF;
    }

    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    public function setPublicationID(int $publicationID): void
    {
        $this->publicationID = $publicationID;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function setVolume(int $volume): void
    {
        $this->volume = $volume;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function setEditorNotePDF(string $editorNotePDF): void
    {
        $this->editorNotePDF = $editorNotePDF;
    }

    public function setIsCurrent(bool $is_current): void
    {
        $this->is_current = $is_current;
    }

    public function createIssue(PDO $pdo, int $year, int $volume, int $number, string $publicationPDF, bool $isCurrent, bool $isDraft): int
    {
        if ($year <= 0) {
            throw new InvalidArgumentException('Invalid publication year.');
        }

        if ($volume <= 0) {
            throw new InvalidArgumentException('Invalid volume number.');
        }

        if ($number <= 0) {
            throw new InvalidArgumentException('Invalid issue number.');
        }

        try {



            $stmt = $pdo->prepare(
                'INSERT INTO "PublicationIssue"
                (
                    "year",
                    "volume",
                    "number",
                    "publicationPDF",
                    "is_current",
                    "is_draft"
                )
                VALUES
                (
                    :year,
                    :volume,
                    :number,
                    :publicationPDF,
                    :is_current,
                    :is_draft
                )
                RETURNING "publicationID"'
            );

            $stmt->execute([
                ':year' => $year,
                ':volume' => $volume,
                ':number' => $number,
                ':publicationPDF' => $publicationPDF,
                ':is_current' => $isCurrent,
                ':is_draft' => $isDraft
            ]);

            $publicationID = $stmt->fetchColumn();
            if ($publicationID === false) {
                throw new RuntimeException('Failed to create publication issue.');
            }

            return (int) $publicationID;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function updateIssue(PDO $pdo, int $publicationID, int $year, int $volume, int $number, ?string $publicationPDF = null): bool
    {
        if ($publicationID <= 0) {
            throw new InvalidArgumentException('Invalid publication ID.');
        }

        if ($year <= 0) {
            throw new InvalidArgumentException('Invalid publication year.');
        }

        if ($volume <= 0) {
            throw new InvalidArgumentException('Invalid volume number.');
        }

        if ($number <= 0) {
            throw new InvalidArgumentException('Invalid issue number.');
        }

        if ($publicationPDF !== null && $publicationPDF !== '') {
            $stmt = $pdo->prepare(
                'UPDATE "PublicationIssue"
                 SET
                    "year" = :year,
                    "volume" = :volume,
                    "number" = :number,
                    "publicationPDF" = :publicationPDF
                 WHERE "publicationID" = :publicationID'
            );

            $stmt->execute([
                ':year' => $year,
                ':volume' => $volume,
                ':number' => $number,
                ':publicationPDF' => $publicationPDF,
                ':publicationID' => $publicationID
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE "PublicationIssue"
                 SET
                    "year" = :year,
                    "volume" = :volume,
                    "number" = :number
                 WHERE "publicationID" = :publicationID'
            );

            $stmt->execute([
                ':year' => $year,
                ':volume' => $volume,
                ':number' => $number,
                ':publicationID' => $publicationID
            ]);
        }

        return true;
    }

    public function updateCurrentStatus(PDO $pdo, int $publicationID, bool $isCurrent): bool
    {
        if ($publicationID <= 0) {
            throw new InvalidArgumentException('Invalid publication ID.');
        }

        if ($isCurrent) {
            $stmt = $pdo->prepare(
                'UPDATE "PublicationIssue"
                 SET "is_current" = FALSE
                 WHERE "is_current" = TRUE'
            );
            $stmt->execute();
        }

        $stmt = $pdo->prepare(
            'UPDATE "PublicationIssue"
             SET "is_current" = :is_current
             WHERE "publicationID" = :publicationID'
        );

        $stmt->execute([
            ':is_current' => $isCurrent,
            ':publicationID' => $publicationID
        ]);

        return true;
    }

    public function getIssueDetails(PDO $pdo, int $publicationID): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT
                "publicationID",
                "year",
                "volume",
                "number",
                "publicationPDF",
                "is_current"
             FROM "PublicationIssue"
             WHERE "publicationID" = :publicationID
             LIMIT 1'
        );

        $stmt->execute([':publicationID' => $publicationID]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$issue) {
            return null;
        }

        return $issue;
    }

    public function viewEditorNotePDF(PDO $pdo, int $publicationID): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT "publicationPDF"
             FROM "PublicationIssue"
             WHERE "publicationID" = :publicationID
             LIMIT 1'
        );

        $stmt->execute([':publicationID' => $publicationID]);
        $editorNotePDF = $stmt->fetchColumn();

        if ($editorNotePDF === false) {
            return null;
        }

        return (string) $editorNotePDF;
    }

    public function downloadEditorNote(PDO $pdo, int $publicationID): void
    {
        if ($publicationID <= 0) {
            throw new InvalidArgumentException('Invalid publication ID.');
        }

        $stmt = $pdo->prepare(
            'SELECT "publicationPDF", "volume", "number"
         FROM "PublicationIssue"
         WHERE "publicationID" = :publicationID
         LIMIT 1'
        );

        $stmt->execute([
            ':publicationID' => $publicationID
        ]);

        $issue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$issue || empty($issue['publicationPDF'])) {
            http_response_code(404);
            exit('Editorial note PDF not found.');
        }

        $pdfPath = trim((string) $issue['publicationPDF']);

        /*
     * Build the Supabase PDF URL
     */
        if (filter_var($pdfPath, FILTER_VALIDATE_URL)) {

            $pdfUrl = $pdfPath;
        } else {

            $supabaseUrl = trim((string) ($_ENV['SUPABASE_URL'] ?? ''));
            $bucketName = trim((string) ($_ENV['SUPABASE_BUCKET'] ?? ''));

            if ($supabaseUrl === '' || $bucketName === '') {
                throw new RuntimeException('Supabase configuration is missing.');
            }

            $pdfPath = ltrim($pdfPath, '/');

            $encodedPath = implode(
                '/',
                array_map('rawurlencode', explode('/', $pdfPath))
            );

            $pdfUrl = rtrim($supabaseUrl, '/')
                . '/storage/v1/object/public/'
                . rawurlencode($bucketName)
                . '/'
                . $encodedPath;
        }

        /*
     * Retrieve the PDF
     */
        $pdfContent = file_get_contents($pdfUrl);

        if ($pdfContent === false) {
            http_response_code(404);
            exit('Unable to retrieve editorial note PDF.');
        }

        /*
     * Create download filename
     */
        $filename = 'Convergence_Volume_' .
            $issue['volume'] .
            '_Number_' .
            $issue['number'] .
            '_Editorial_Note.pdf';

        /*
     * Force browser download
     */
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        echo $pdfContent;
        exit;
    }
}

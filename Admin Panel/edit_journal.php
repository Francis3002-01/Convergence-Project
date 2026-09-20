<?php
require_once __DIR__ . '/../config/database.php';

$publicationID = filter_input(INPUT_GET, 'publicationID', FILTER_VALIDATE_INT);
$journalID = filter_input(INPUT_GET, 'journalID', FILTER_VALIDATE_INT);

if (($publicationID === false || $publicationID === null || $publicationID <= 0) && ($journalID === false || $journalID === null || $journalID <= 0)) {
    header('Location: manage_journal.php');
    exit;
}

if ($publicationID === false || $publicationID === null || $publicationID <= 0) {
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->prepare('SELECT "publicationID" FROM "JournalArticle" WHERE "journalID" = :journalID LIMIT 1');
    $stmt->execute([':journalID' => $journalID]);
    $publicationID = (int) ($stmt->fetchColumn() ?: 0);
}

if (!$publicationID || $publicationID <= 0) {
    header('Location: manage_journal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Journal - Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/edit_journal.css">
</head>
<body>
    <?php include 'components/left_sidebar.php'; ?> <div class="main-area">
        <?php include 'components/header.php'; ?> <main class="content">

            <!-- PAGE HEADER -->
            <header class="page-header">
                <div>
                    <h1>Edit Journal</h1>
                    <p>Update the article and publication information.</p>
                </div>

                <button type="button" class="back-button" onclick="goBack()">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>

            </header>

            <!-- LOADING MESSAGE -->
            <div id="loading" class="status-message" role="status" aria-live="polite">
                <i class="fa-solid fa-spinner fa-spin"></i> Loading journal...
            </div>

            <!-- ERROR MESSAGE -->
            <div id="errorMessage" class="status-message error-message" role="alert" aria-live="assertive" style="display: none;"> </div>

            <!-- EDIT FORM -->
            <form id="editForm" data-publication-id="<?= htmlspecialchars((string) $publicationID) ?>" enctype="multipart/form-data" style="display: none;">
                <input type="hidden" id="publicationID" name="publicationID" value="<?= htmlspecialchars((string) $publicationID) ?>">

                <section class="edit-section" aria-labelledby="article-information-heading">
                    <header class="section-header">
                        <div class="section-icon" aria-hidden="true"> <i class="fa-solid fa-file-lines"></i> </div>
                        <div>
                            <h2 id="article-information-heading"> Articles </h2>
                            <p> Edit every article in this publication issue. </p>
                        </div>
                    </header>

                    <div id="articlesContainer" class="articles-container"></div>
                </section>

                <!-- PUBLICATION ISSUE -->
                <section class="edit-section" aria-labelledby="publication-issue-heading">

                    <header class="section-header">
                        <div class="section-icon" aria-hidden="true"> <i class="fa-solid fa-book"></i> </div>
                        <div>

                            <h2 id="publication-issue-heading"> Publication Issue </h2>
                            <p> Edit the publication issue information. </p>
                        </div>

                    </header>

                    <!-- YEAR / VOLUME / NUMBER -->
                    <fieldset class="publication-grid">
                        <legend class="sr-only"> Publication issue information </legend>

                        <!-- YEAR -->
                        <div class="form-group">
                            <label for="year"> Year
                                <span class="required" aria-hidden="true">*</span>
                            </label>

                            <input type="number" id="year" name="year" min="1900" max="2100" required>
                        </div>

                        <!-- VOLUME -->
                        <div class="form-group">
                            <label for="volume"> Volume
                                <span class="required" aria-hidden="true">*</span>
                            </label> <input type="number" id="volume" name="volume" min="1" required>
                        </div>

                        <!-- NUMBER -->
                        <div class="form-group">
                            <label for="number"> Number <span class="required" aria-hidden="true">*</span> </label>
                            <input type="number" id="number" name="number" min="1" required>
                        </div>
                    </fieldset>

                    <!-- CURRENT PUBLICATION PDF -->
                    <div class="form-group">
                        <label for="currentPublicationPdf"> Current Publication PDF </label>
                        <div class="current-file">
                            <div class="file-icon" aria-hidden="true">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                            <div class="file-information">
                                <span class="file-label"> Current Publication PDF </span>
                                <div id="currentPublicationPdf"> Loading... </div>
                            </div>
                        </div>
                    </div>

                    <!-- REPLACE PUBLICATION PDF -->
                    <div class="form-group">
                        <label for="publicationPDF"> Replace Publication PDF </label>
                        <div class="pdf-upload-box" id="publicationPdfUploadBox">
                            <div class="pdf-upload-icon" aria-hidden="true">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                            <div class="pdf-upload-text">
                                <strong> Select a new publication PDF </strong>
                                <span id="publicationPdfFileName"> No file selected </span>
                            </div>

                            <button type="button" class="choose-pdf" onclick="document.getElementById('publicationPDF').click()">
                                <i class="fa-solid fa-folder-open"></i> Choose File
                            </button> <input type="file" id="publicationPDF" name="publicationPDF" accept="application/pdf">
                        </div>

                        <small class="form-help"> Optional. Leave empty to keep the current PDF</small>
                    </div>

                </section>

                <!-- FORM ACTIONS -->
                <footer class="form-actions">
                    <button type="button" class="cancel-button" onclick="goBack()"> Cancel </button>
                    <button type="submit" class="save-button" id="saveButton"> Confirm Changes </button>
                </footer>

            </form>

        </main>
    </div>

    <!--JAVASCRIPT -->
    <script src="../javascript/edit_journal.js"></script>
</body>
</html>
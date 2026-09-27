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

    $stmt = $pdo->prepare(
        'SELECT "publicationID"
         FROM "JournalArticle"
         WHERE "journalID" = :journalID
         LIMIT 1'
    );

    $stmt->execute([
        ':journalID' => $journalID
    ]);

    $publicationID = (int) ($stmt->fetchColumn() ?: 0);
}

if (!$publicationID || $publicationID <= 0) {
    header('Location: manage_journal.php');
    exit;
}

$pageTitle = 'Edit Journal - Convergence';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/edit_journal.css">
    <link rel="stylesheet" href="../css/edit_journal_modals.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="icon" type="image/png" href="../Images/Convergence Logo.png">

</head>

<body>

    <?php include 'components/left_sidebar.php'; ?>

    <div class="main-area">

        <?php include 'components/header.php'; ?>

        <main class="content">
            <section class="edit-journal-container">

                <div class="page-header">
                    <div class="page-heading">
                        <h1 id="pageTitle">Edit Journal</h1>
                        <p id="pageDescription">Update the publication issue and its articles</p>
                    </div>
                </div>

                <div id="loading" class="status-message" role="status" aria-live="polite">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    Loading journal...
                </div>

                <div id="errorMessage" class="status-message error-message" role="alert" aria-live="assertive" style="display: none;"></div>

                <form id="editForm" data-publication-id="<?= htmlspecialchars((string) $publicationID) ?>" enctype="multipart/form-data" style="display: none;">

                    <input type="hidden" id="publicationID" name="publicationID" value="<?= htmlspecialchars((string) $publicationID) ?>">
                    <div class="form-step" id="publicationStep">
                        <div class="form-header">

                            <div>
                                <h2>Publication Issue</h2>
                                <p>Edit the publication issue information</p><br>
                            </div>

                        </div>


                        <div class="form-section">


                            <!-- YEAR / VOLUME / NUMBER -->

                            <div class="publication-fields">


                                <div class="form-group">

                                    <label for="year">

                                        Year

                                        <span class="required">*</span>

                                    </label>

                                    <input
                                        type="number"
                                        id="year"
                                        name="year"
                                        min="1900"
                                        max="2100"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label for="volume">

                                        Volume

                                        <span class="required">*</span>

                                    </label>

                                    <input
                                        type="number"
                                        id="volume"
                                        name="volume"
                                        min="1"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label for="number">

                                        Number

                                        <span class="required">*</span>

                                    </label>

                                    <input
                                        type="number"
                                        id="number"
                                        name="number"
                                        min="1"
                                        required>

                                </div>


                            </div>


                            <!-- CURRENT EDITORIAL NOTE -->

                            <div class="form-group">

                                <label>
                                    Current Editorial Note PDF
                                </label>

                                <div class="current-file">

                                    <div class="file-icon">

                                        <i class="fa-solid fa-file-pdf"></i>

                                    </div>

                                    <div class="file-information">

                                        <span class="file-label">
                                            Current Editorial Note PDF
                                        </span>

                                        <div id="currentPublicationPdf">
                                            Loading...
                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- REPLACE EDITORIAL NOTE -->

                            <div class="form-group">

                                <label for="publicationPDF">
                                    Replace Editorial Note PDF
                                </label>

                                <div
                                    class="pdf-upload-box"
                                    id="publicationPdfUploadBox">

                                    <input
                                        type="file"
                                        id="publicationPDF"
                                        name="publicationPDF"
                                        accept="application/pdf,.pdf"
                                        hidden>

                                    <div class="pdf-upload-inner">

                                        <label
                                            for="publicationPDF"
                                            class="pdf-upload-label">

                                            <div class="upload-icon">

                                                <i class="fa-solid fa-file-pdf"></i>

                                            </div>

                                            <span class="upload-title">
                                                Choose Editorial Note PDF
                                            </span>

                                            <span
                                                class="upload-file-name"
                                                id="publicationPdfFileName">
                                                No file selected
                                            </span>

                                        </label>


                                        <button
                                            type="button"
                                            class="undo-pdf-btn"
                                            id="publicationPdfUndo"
                                            hidden>
                                            Undo
                                        </button>

                                    </div>

                                </div>


                                <small class="form-help">
                                    Optional. Leave empty to keep the current PDF.
                                </small>

                            </div>


                        </div>

                    </div>

                    <div
                        class="form-step"
                        id="articleStep">

                        <div class="form-header">

                            <div>

                                <h2>
                                    Articles
                                </h2>

                                <p>
                                    Select an article to edit its information.
                                </p>

                                <br>

                            </div>

                        </div>


                        <div class="form-section">

                            <div class="form-group">
                                <label for="articleSelect">Select Article</label>

                                <select id="articleSelect" name="articleSelect">
                                    <option value="">Select an article</option>
                                </select>
                            </div>


                            <div id="selectedArticleEditor" class="article-form-card" style="display: none;">

                                <!-- ARTICLE HEADING -->
                                <div class="article-heading-row">

                                    <div>
                                        <span class="article-label">Article</span>
                                        <h3 id="articleHeading">Article</h3>
                                    </div>
                                </div>


                                <!-- ARTICLE TITLE -->

                                <div class="form-group">

                                    <label for="articleTitle">
                                        Article Title
                                    </label>

                                    <input
                                        type="text"
                                        id="articleTitle"
                                        placeholder="Enter article title"
                                        required>

                                </div>


                                <!-- CURRENT ARTICLE PDF -->

                                <div class="form-group">

                                    <label>
                                        Current Article PDF
                                    </label>

                                    <div class="current-file">

                                        <div class="file-icon">

                                            <i class="fa-solid fa-file-pdf"></i>

                                        </div>

                                        <div class="file-information">

                                            <span class="file-label">
                                                Current Article PDF
                                            </span>

                                            <div id="currentArticlePdf">
                                                No PDF available
                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- REPLACE ARTICLE PDF -->

                                <div class="form-group">

                                    <label for="articlePDF">
                                        Replace Article PDF
                                    </label>

                                    <div
                                        class="pdf-upload-box"
                                        id="articlePdfUploadBox">

                                        <input
                                            type="file"
                                            id="articlePDF"
                                            name="articlePDF"
                                            accept="application/pdf,.pdf"
                                            hidden>


                                        <div class="pdf-upload-inner">

                                            <label
                                                for="articlePDF"
                                                class="pdf-upload-label">

                                                <div class="upload-icon">

                                                    <i class="fa-solid fa-file-pdf"></i>

                                                </div>

                                                <span class="upload-title">
                                                    Choose Article PDF
                                                </span>

                                                <span
                                                    class="upload-file-name"
                                                    id="articlePdfFileName">
                                                    No file selected
                                                </span>

                                            </label>

                                            <button type="button" class="undo-pdf-btn" id="articlePdfUndo" hidden>Undo</button>
                                        </div>

                                    </div>

                                    <small class="form-help">Optional. Leave empty to keep the current PDF</small>

                                </div>


                                <!-- AUTHORS -->
                                <div class="form-group">
                                    <div class="field-label-row">
                                        <label>Authors</label>
                                        <button type="button" class="add-author-button" onclick="addAuthor()">
                                            <i class="fa-solid fa-plus"></i>
                                            Add Author
                                        </button>

                                    </div>

                                    <div id="authors" class="authors-container"></div>
                                </div>


                            </div>


                        </div>

                        <!-- FORM ACTIONS -->
                        <div class="form-actions article-actions">
                            <button type="button" class="cancel-btn" onclick="openCancelModal()">Cancel</button>
                            <button type="submit" class="save-draft-btn" id="saveButton">Confirm Changes</button>
                        </div>
                    </div>
                </form>
            </section>
        </main>

    </div>

    <?php include 'edit_journal_modals.php'; ?>

    <script src="../javascript/edit_journal_modal.js"></script>
    <script src="../javascript/edit_journal.js"></script>
</body>

</html>
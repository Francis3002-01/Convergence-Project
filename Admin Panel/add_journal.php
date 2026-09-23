<?php

$pageTitle = "Add Journal - Convergence";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/add_journal.css">
    <link rel="icon" type="image/png"href="../Images/Convergence Logo.png">
</head>

<body>

    <?php include 'components/left_sidebar.php'; ?>

    <div class="main-area">

        <?php include 'components/header.php'; ?>

        <main class="content">

            <section class="add-journal-container">

                <!-- PAGE HEADER -->
                <div class="page-header">
                    <div class="page-heading">
                        <h1 id="pageTitle">Add Journal</h1>

                        <p id="pageDescription">
                            Add a new publication issue and its articles
                        </p>
                    </div>
                </div>


                <!-- JOURNAL FORM -->
                <div class="journal-form" id="journalForm">

                    <!-- PUBLICATION STEP -->
                    <div class="form-step" id="publicationStep">

                        <div class="form-header">
                            <div>
                                <h2>Publication Issue</h2>

                                <p>
                                    Enter the publication issue information
                                </p>

                                <br>
                            </div>
                        </div>


                        <div class="form-section">

                            <!-- YEAR / VOLUME / NUMBER -->
                            <div class="publication-fields">

                                <div class="form-group">
                                    <label for="year">
                                        Year
                                    </label>

                                    <select id="year" name="year" required>
                                        <option value="">
                                            Select year
                                        </option>
                                    </select>
                                </div>


                                <div class="form-group">
                                    <label for="volume">
                                        Volume
                                    </label>

                                    <input
                                        type="number"
                                        id="volume"
                                        name="volume"
                                        min="1"
                                        placeholder="e.g. 11"
                                        required
                                    >
                                </div>


                                <div class="form-group">
                                    <label for="number">
                                        Number
                                    </label>

                                    <input
                                        type="number"
                                        id="number"
                                        name="number"
                                        min="1"
                                        placeholder="e.g. 1"
                                        required
                                    >
                                </div>

                            </div>


                            <!-- EDITORIAL NOTE PDF -->
                            <div class="form-group">

                                <label>
                                    Editorial Note PDF
                                </label>

                                <div
                                    class="pdf-upload-box"
                                    id="publicationPdfUploadBox"
                                >

                                    <input
                                        type="file"
                                        id="publicationPDF"
                                        name="publicationPDF"
                                        accept="application/pdf,.pdf"
                                        hidden
                                    >

                                    <label
                                        for="publicationPDF"
                                        class="pdf-upload-label"
                                    >

                                        <div class="upload-icon">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>

                                        <span class="upload-title">
                                            Choose Publication Issue PDF
                                        </span>

                                        <span
                                            class="upload-file-name"
                                            id="publicationPdfFileName"
                                        >
                                            No file selected
                                        </span>

                                    </label>


                                    <button
                                        type="button"
                                        class="undo-pdf-btn"
                                        id="publicationPdfUndo"
                                        hidden
                                    >
                                        Undo
                                    </button>

                                </div>

                            </div>

                        </div>


                        <!-- PUBLICATION ACTIONS -->
                        <div class="form-actions">

                            <button
                                type="button"
                                class="cancel-btn"
                                onclick="cancelAddJournal()"
                            >
                                Cancel
                            </button>

                            <button
                                type="button"
                                class="next-btn"
                                onclick="goToArticles()"
                            >
                                Next
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>

                        </div>

                    </div>


                    <!-- ARTICLE STEP -->
                    <div
                        class="form-step"
                        id="articleStep"
                        style="display: none;"
                    >

                        <!-- BACK TO PUBLICATION -->
                        <div class="article-step-top">

                            <button
                                type="button"
                                class="back-to-publication"
                                onclick="goToPublication()"
                            >
                                <i class="fa-solid fa-arrow-left"></i>
                                Back to Publication Issue
                            </button>

                        </div>


                        <!-- PUBLICATION SUMMARY -->
                        <div class="publication-summary">

                            <div>
                                <span class="summary-label">
                                    Year
                                </span>

                                <strong id="displayYear">
                                    —
                                </strong>
                            </div>


                            <div>
                                <span class="summary-label">
                                    Volume
                                </span>

                                <strong id="displayVolume">
                                    —
                                </strong>
                            </div>


                            <div>
                                <span class="summary-label">
                                    Number
                                </span>

                                <strong id="displayNumber">
                                    —
                                </strong>
                            </div>

                        </div>


                        <!-- ARTICLE TABS -->
                        <div class="article-tabs-wrapper">

                            <div
                                class="article-tabs"
                                id="articleTabs"
                            ></div>

                            <button
                                type="button"
                                class="add-article-btn"
                                onclick="addArticle()"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Add Article
                            </button>

                        </div>


                        <!-- ARTICLE INFORMATION -->
                        <div class="article-form-card">

                            <div class="article-heading-row">

                                <div>
                                    <span class="article-label">
                                        Article
                                    </span>

                                    <h3 id="articleHeading">
                                        Article 1
                                    </h3>
                                </div>


                                <button
                                    type="button"
                                    class="delete-article-button"
                                    id="deleteArticleButton"
                                    onclick="requestDeleteArticle()"
                                    title="Remove article"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                    Remove Article
                                </button>

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
                                    required
                                >

                            </div>


                            <!-- ARTICLE PDF -->
                            <div class="form-group">

                                <label>
                                    PDF File
                                </label>

                                <div
                                    class="pdf-upload-box"
                                    id="articlePdfUploadBox"
                                >

                                    <input
                                        type="file"
                                        id="pdfFile"
                                        accept="application/pdf,.pdf"
                                        hidden
                                    >

                                    <label
                                        for="pdfFile"
                                        class="pdf-upload-label"
                                    >

                                        <div class="upload-icon">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>

                                        <span class="upload-title">
                                            Choose Article PDF
                                        </span>

                                        <span
                                            class="upload-file-name"
                                            id="pdfFileName"
                                        >
                                            No file selected
                                        </span>

                                    </label>


                                    <button
                                        type="button"
                                        class="undo-pdf-btn"
                                        id="articlePdfUndo"
                                        hidden
                                    >
                                        Undo
                                    </button>

                                </div>

                            </div>


                            <!-- AUTHORS -->
                            <div class="form-group">

                                <div class="field-label-row">

                                    <label>
                                        Authors
                                    </label>

                                    <button
                                        type="button"
                                        class="add-author-button"
                                        onclick="addAuthor()"
                                    >
                                        <i class="fa-solid fa-plus"></i>
                                        Add Author
                                    </button>

                                </div>


                                <div
                                    id="authors"
                                    class="authors-container"
                                ></div>

                            </div>

                        </div>


                        <!-- ARTICLE ACTIONS -->
                        <div class="form-actions article-actions">

                            <button
                                type="button"
                                class="cancel-btn"
                                onclick="cancelAddJournal()"
                            >
                                Cancel
                            </button>


                            <!-- SAVE AS DRAFT -->
                            <button
                                type="button"
                                class="save-draft-btn"
                                id="saveDraftButton"
                                onclick="savePublication('draft')"
                            >
                                <i class="fa-solid fa-file-pen"></i>
                                Save as Draft
                            </button>


                            <!-- PUBLISH -->
                            <button
                                type="button"
                                class="publish-btn"
                                id="publishButton"
                                onclick="savePublication('publish')"
                            >
                                <i class="fa-solid fa-upload"></i>
                                Publish
                            </button>

                        </div>

                    </div>

                </div>

            </section>

        </main>

    </div>


    <!-- REMOVE ARTICLE CONFIRMATION MODAL -->
    <div
        class="confirmation-modal"
        id="confirmationModal"
    >

        <div
            class="confirmation-overlay"
            onclick="closeConfirmationModal()"
        ></div>


        <div class="confirmation-dialog">

            <div class="confirmation-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>


            <h3>
                Remove Article?
            </h3>


            <p id="confirmationMessage">
                Are you sure you want to remove this article?
            </p>


            <div class="confirmation-actions">

                <button
                    type="button"
                    class="modal-cancel-btn"
                    onclick="closeConfirmationModal()"
                >
                    Cancel
                </button>


                <button
                    type="button"
                    class="modal-confirm-btn"
                    id="confirmDeleteButton"
                >
                    Remove Article
                </button>

            </div>

        </div>

    </div>


    <script src="../javascript/add_journal.js"></script>

</body>

</html>
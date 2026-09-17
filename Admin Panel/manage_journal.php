<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Journals - Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/manage_journal.css">
</head>

<body>

    <?php include 'components/left_sidebar.php'; ?>
    <div class="main-area">
        <?php include 'components/header.php'; ?>
        <main class="content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-heading">
                    <h1 id="pageTitle">Manage Journals</h1>
                    <p id="pageDescription">Manage the current publication and prepare the next issue.</p>
                </div>

                <div class="page-actions" id="pageActions">
                    <!-- SEARCH -->
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search articles..." autocomplete="off">
                        <button type="button" id="searchButton" aria-label="Search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>

                    <!-- ADD JOURNAL -->
                    <button type="button" class="add-button" id="addJournalButton" onclick="showForm()">Add Journal</button>
                </div>
            </div>

            <!-- JOURNAL LIST -->
            <div class="journal-list-container" id="journalListContainer">
                <div class="journal-tabs">
                    <button type="button" class="journal-tab active" id="currentTabButton" onclick="showTab('current', this)">Current</button>
                    <button type="button" class="journal-tab" id="draftTabButton" onclick="showTab('draft', this)">Draft
                        <span class="draft-count" id="draftCount">0</span>
                    </button>
                </div>

                <!--CURRENT TAB -->
                <div id="currentJournalContent" class="journal-tab-content">
                    <div class="journal-list-header">
                        <div>Title</div>
                        <div>Authors</div>
                        <div>Actions</div>
                    </div>

                    <div id="currentJournalList" class="journal-list"></div>
                </div>


                <!--DRAFT TAB -->
                <div id="draftJournalContent" class="journal-tab-content" style="display: none;">
                    <div class="journal-list-header">
                        <div>Title</div>
                        <div>Authors</div>
                        <div>Actions</div>
                    </div>

                    <div id="draftJournalList" class="journal-list"></div>
                </div>

            </div>

            <!--ADD / EDIT JOURNAL FORM -->
            <section class="journal-form" id="journalForm" style="display: none;">
                <div class="form-step" id="publicationStep">
                    <div class="form-header">
                        <div>
                            <h2>Add Journal</h2>
                            <p>Enter the publication issue information</p>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">
                            <h3>Publication Issue</h3>
                        </div>


                        <!-- YEAR / VOLUME / NUMBER -->
                        <div class="publication-fields">
                            <div class="form-group">
                                <label for="year">Year</label>
                                <select id="year" name="year" required>
                                    <option value="">Select year</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="volume">Volume</label>
                                <input type="number" id="volume" name="volume" min="1" placeholder="e.g. 11" required>
                            </div>


                            <div class="form-group">
                                <label for="number">Number</label>
                                <input type="number" id="number" name="number" min="1" placeholder="e.g. 1" required>
                            </div>
                        </div>


                        <!-- PUBLICATION PDF -->
                        <div class="form-group">
                            <label>Publication Issue PDF</label>


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
                                            Choose Publication Issue PDF
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

                        </div>

                    </div>


                    <!-- PUBLICATION ACTIONS -->

                    <div class="form-actions">
                        <button type="button" class="cancel-btn" onclick="hideForm()">Cancel</button>
                        <button type="button" class="next-btn" onclick="goToArticles()">Next
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- =================================================
                     ARTICLE STEP
                     ================================================= -->

                <div
                    class="form-step"
                    id="articleStep"
                    style="display: none;">

                    <!-- BACK TO PUBLICATION -->

                    <div class="article-step-top">

                        <button
                            type="button"
                            class="back-to-publication"
                            onclick="goToPublication()">

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
                            id="articleTabs"></div>


                        <button
                            type="button"
                            class="add-article-btn"
                            onclick="addArticle()">

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
                                title="Remove article">

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
                                required>

                        </div>


                        <!-- ARTICLE PDF -->

                        <div class="form-group">

                            <label>
                                PDF File
                            </label>


                            <div
                                class="pdf-upload-box"
                                id="articlePdfUploadBox">

                                <input
                                    type="file"
                                    id="pdfFile"
                                    accept="application/pdf,.pdf"
                                    hidden>


                                <div class="pdf-upload-inner">

                                    <label
                                        for="pdfFile"
                                        class="pdf-upload-label">

                                        <div class="upload-icon">

                                            <i class="fa-solid fa-file-pdf"></i>

                                        </div>


                                        <span class="upload-title">
                                            Choose Article PDF
                                        </span>


                                        <span
                                            class="upload-file-name"
                                            id="pdfFileName">
                                            No file selected
                                        </span>

                                    </label>


                                    <button
                                        type="button"
                                        class="undo-pdf-btn"
                                        id="articlePdfUndo"
                                        hidden>

                                        Undo

                                    </button>

                                </div>

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
                                    onclick="addAuthor()">

                                    <i class="fa-solid fa-plus"></i>
                                    Add Author

                                </button>

                            </div>


                            <div
                                id="authors"
                                class="authors-container"></div>

                        </div>

                    </div>


                    <div class="form-actions article-actions">

                        <button
                            type="button"
                            class="cancel-btn"
                            onclick="hideForm()">

                            Cancel

                        </button>


                        <!-- SAVE AS DRAFT -->

                        <button
                            type="button"
                            class="save-draft-btn"
                            id="saveDraftButton"
                            onclick="savePublication('draft')">

                            <i class="fa-solid fa-file-pen"></i>
                            Save as Draft

                        </button>


                        <!-- PUBLISH -->

                        <button
                            type="button"
                            class="publish-btn"
                            id="publishButton"
                            onclick="savePublication('publish')">

                            <i class="fa-solid fa-upload"></i>
                            Publish

                        </button>

                    </div>

                </div>

            </section>

        </main>

    </div>


    <!-- REMOVE ARTICLE CONFIRMATION MODAL -->
    <div class="confirmation-modal" id="confirmationModal" style="display: none;">
        <div class="confirmation-overlay"></div>

        <div class="confirmation-dialog">
            <div class="confirmation-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h3>Remove Article?</h3>
            <p id="confirmationMessage">Are you sure you want to remove this article?</p>

            <div class="confirmation-actions">

                <button type="button" class="modal-cancel-btn" onclick="closeConfirmationModal()">Cancel</button>

                <button type="button" class="modal-confirm-btn" id="confirmDeleteButton">
                    Remove Article
                </button>

            </div>

        </div>

    </div>
    <script src="../javascript/manage_journal.js"></script>
</body>

</html>
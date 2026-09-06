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

            <!--Page Header-->
            <div class="page-header">
                <h1 id="pageTitle">Manage Journals</h1>
                <div class="page-actions">

                    <div class="search-box">

                        <input type="text" id="searchInput" placeholder="Search journals...">

                        <button type="button" onclick="searchJournals()" aria-label="Search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>

                    <button type="button" class="add-button" onclick="showForm()">Add Journal</button>
                </div>
            </div>


            <!--Journal List-->
            <div class="journal-list-container" id="journalListContainer">
                <div id="journalList"></div>
            </div>

            <!--Journal Form-->
            <div class="journal-form" id="journalForm">

                <!--Publication Issue-->
                <div id="publicationStep">
                    <div class="form-group">
                        <label for="year">Publication Year</label>
                        <select id="year"></select>
                    </div>


                    <div class="form-group">
                        <label for="volume">Volume</label>
                        <input type="number" id="volume" min="1" placeholder="Enter volume">
                    </div>


                    <div class="form-group">
                        <label for="number">Number</label>
                        <input type="number" id="number" min="1" placeholder="Enter number">
                    </div>


                    <!-- Publication Issue PDF-->
                    <div class="form-group">
                        <label for="publicationPDF">Publication Issue PDF</label>
                        <div class="pdf-upload-box" id="publicationPdfUploadBox">

                            <input type="file" id="publicationPDF" accept=".pdf,application/pdf">
                            <div class="pdf-upload-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 16V4"></path>
                                    <path d="M7 9l5-5 5 5"></path>
                                    <path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"></path>
                                </svg>
                            </div>

                            <div class="pdf-upload-text">
                                <strong>Drag and drop or choose a PDF file</strong>
                                <span id="publicationPdfFileName">PDF files only</span>
                            </div>

                            <label for="publicationPDF" class="choose-pdf">Choose PDF</label>
                        </div>
                    </div>


                    <!--Publication Actions-->
                    <div class="form-actions">
                        <button type="button" class="cancel-btn" onclick="hideForm()">Cancel</button>
                        <button type="button" class="save-btn" onclick="goToArticles()">Next</button>
                    </div>
                </div>


                <!--Article Step-->
                <div id="articleStep">
                    <div class="article-sticky">

                        <!-- Back Button -->
                        <button type="button" class="back-publication" onclick="goToPublication()">
                            <i class="fa-solid fa-arrow-left"></i>Back to Publication Issue
                        </button>

                        <!-- Publication Summary -->
                        <div class="publication-summary">
                            <div class="publication-detail">
                                <span>Year</span>
                                <strong id="displayYear"></strong>
                            </div>

                            <div class="publication-detail">
                                <span>Volume</span>
                                <strong id="displayVolume"></strong>
                            </div>

                            <div class="publication-detail">
                                <span>Number</span>
                                <strong id="displayNumber"></strong>
                            </div>
                        </div>

                        <!-- Article Tabs -->
                        <div class="article-tabs-row">
                            <div class="article-tabs" id="articleTabs"></div>
                            <button type="button" class="add-article-btn" onclick="addArticle()">
                                <i class="fa-solid fa-plus"></i>Add Article
                            </button>
                        </div>

                        <!-- Article Heading -->
                        <div class="article-heading">
                            <h2 id="articleHeading">Article 1</h2>
                            <button type="button" class="delete-article-btn" id="deleteArticleButton" onclick="requestDeleteArticle()" title="Remove Article" aria-label="Remove Article">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!--Article Content-->
                    <div class="article-content">
                        <!-- Article Title -->
                        <div class="form-group">
                            <label for="articleTitle">Title</label>
                            <input type="text" id="articleTitle" placeholder="Enter article title">
                        </div>


                        <!-- Article PDF -->
                        <div class="form-group">
                            <label for="pdfFile">PDF File</label>
                            <div class="pdf-upload-box" id="pdfUploadBox">
                                <input type="file" id="pdfFile" accept=".pdf,application/pdf">
                                <div class="pdf-upload-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 16V4"></path>
                                        <path d="M7 9l5-5 5 5"></path>
                                        <path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"></path>
                                    </svg>
                                </div>
                                <div class="pdf-upload-text">
                                    <strong>Drag and drop or choose a PDF file</strong>
                                    <span id="pdfFileName">PDF files only</span>
                                </div>
                                <label for="pdfFile" class="choose-pdf">Choose PDF</label>
                            </div>
                        </div>

                        <!--Authors -->
                        <div class="authors-section">
                            <h3>Authors</h3>
                            <div id="authors"></div>
                            <button type="button" class="add-author-btn"onclick="addAuthor()">
                                <i class="fa-solid fa-plus"></i>Add Author
                            </button>
                        </div>

                        <!--Article Action -->
                        <div class="form-actions article-actions">
                            <button type="button" class="cancel-btn" onclick="hideForm()">
                                Cancel
                            </button>

                            <button type="button" class="save-btn" onclick="savePublication()">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!--Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-box">

            <h3 id="modalTitle">Remove Article?</h3>
            <p id="modalMessage"></p>
            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeModal()">
                    Cancel
                </button>

                <button type="button" class="remove-btn" id="confirmDeleteButton">
                    Remove
                </button>
            </div>
        </div>

    </div>

    <!--JavaScript-->
    <script src="../javascript/manage_journal.js"></script>
</body>

</html>
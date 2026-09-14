<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Details | Convergence</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/admin_journal_details.css">
</head>

<body>
    
    <?php include 'components/left_sidebar.php'; ?>
    <?php include 'components/header.php'; ?>

    <main class="main-content">
        <div class="details-container">

            <a href="manage_journal.php" class="back-button">
                <span class="back-arrow">←
                </span>Back to Manage Journals
            </a>

            <div id="loading" class="loading">
                <p>Loading journal details...</p>
                <div class="loading-bar-container">
                    <div class="loading-bar"></div>
                </div>
            </div>

            <div id="error" class="error" style="display: none;">
                <p id="errorMessage"></p>
            </div>

            <div id="detailsCard" class="details-card"style="display: none;">

                <div class="details-header">
                    <h1 id="articleTitle"></h1>
                </div>

                <div class="publication-info">
                    <span id="publicationInfo"></span>
                </div>

                <div class="details-section">
                    <h2>Authors</h2>
                    <div id="authors" class="authors-list"></div>
                </div>

                <div class="details-section">
                    <h2>APA Citation</h2>
                    <div class="citation-box">
                        <p id="citation"></p>
                        <button type="button" id="copyCitation" class="copy-button">
                            Copy Citation
                        </button>
                    </div>
                </div>

                <div id="pdfSection" class="pdf-section" style="display: none;">

                    <!--PDF ACTIONS -->
                    <div class="pdf-actions">
                        <!-- READ ONLINE -->
                        <button id="readOnline" type="button" class="action-button">Read Online</button>
                        <!-- DOWNLOAD PDF -->
                        <a id="downloadPdf" class="action-button secondary" href="#" download>Download PDF</a>
                    </div>

                    <!--PDF VIEWER -->
                    <div id="pdfViewerContainer" class="pdf-viewer-container" style="display: none;">
                        <iframe id="pdfViewer" class="pdf-viewer"title="Journal PDF Viewer"></iframe>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <script src="../javascript/admin_journal_details.js"></script>
</body>
</html>
<?php
$pageTitle = "Manage Journals - Convergence";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/manage_journal.css">
    <link rel="icon" type="image/png" href="../Images/Convergence Logo.png">
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
                    <p id="pageDescription">Manage the current publication and prepare the next issue</p>
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
                    <button type="button" class="add-button"id="addJournalButton">Add Journal</button>
                </div>
            </div>

            <!-- JOURNAL LIST -->
            <div class="journal-list-container" id="journalListContainer">
                <div class="journal-tabs">
                    <button type="button" class="journal-tab active" id="currentTabButton" onclick="showTab('current', this)">Current</button>

                    <button type="button" class="journal-tab" id="draftTabButton"onclick="showTab('draft', this)">Draft
                        
                    </button>

                </div>


                <!-- CURRENT TAB -->
                <div id="currentJournalContent" class="journal-tab-content">

                    <div class="journal-list-header">
                        <div>Title</div>
                        <div>Authors</div>
                        <div>Actions</div>
                    </div>

                    <div id="currentJournalList" class="journal-list"></div>
                </div>


                <!-- DRAFT TAB -->
                <div id="draftJournalContent" class="journal-tab-content"style="display: none;">
                    <div class="journal-list-header">
                        <div>Title</div>
                        <div>Authors</div>
                        <div>Actions</div>

                    </div>

                    <div
                        id="draftJournalList"
                        class="journal-list"
                    ></div>

                </div>

            </div>

        </main>

    </div>


    <!-- REMOVE ARTICLE CONFIRMATION MODAL -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-overlay"></div>

        <div class="confirmation-dialog">

            <div class="confirmation-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h3>Remove Article?</h3>

            <p id="confirmationMessage">Are you sure you want to remove this article?</p>

            <div class="confirmation-actions">
                <button type="button"class="modal-cancel-btn"onclick="closeConfirmationModal()">Cancel
                </button>
                <button type="button" class="modal-confirm-btn" id="confirmDeleteButton">Remove Article</button>
            </div>

        </div>

    </div>


    <script src="../javascript/manage_journal.js"></script>

</body>

</html>
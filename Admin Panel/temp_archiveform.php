<?php

require_once '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

define('TEST_MODE', false);

$message = '';
$messageType = '';


// ============================================================
// FORM SUBMISSION
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --------------------------------------------------------
    // Get issue information
    // --------------------------------------------------------

    $year = isset($_POST['year'])
        ? (int) $_POST['year']
        : 0;

    $volume = isset($_POST['volume'])
        ? (int) $_POST['volume']
        : 0;

    $number = isset($_POST['number'])
        ? (int) $_POST['number']
        : 0;

    $articles = $_POST['articles'] ?? [];


    // --------------------------------------------------------
    // Validate issue information
    // --------------------------------------------------------

    if ($year <= 0 || $volume <= 0 || $number <= 0) {

        $message = 'Please enter a valid year, volume, and number.';
        $messageType = 'error';

    } elseif (empty($articles)) {

        $message = 'Please add at least one article.';
        $messageType = 'error';

    } else {

        try {

            // =================================================
            // START TRANSACTION
            // =================================================

            $pdo->beginTransaction();


            // =================================================
            // 1. INSERT PUBLICATION ISSUE
            // =================================================

            $issueSQL = '
                INSERT INTO "PublicationIssue"
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
                    NULL,
                    false,
                    false
                )
                RETURNING "publicationID"
            ';

            $issueStmt = $pdo->prepare($issueSQL);

            $issueStmt->execute([
                ':year' => $year,
                ':volume' => $volume,
                ':number' => $number
            ]);


            // ------------------------------------------------
            // Get generated publicationID
            // ------------------------------------------------

            $publicationID = $issueStmt->fetchColumn();

            if (!$publicationID) {
                throw new Exception(
                    'Publication issue was inserted, but no publicationID was returned.'
                );
            }


            // =================================================
            // 2. PREPARE ARTICLE INSERT
            // =================================================

            $articleSQL = '
                INSERT INTO "JournalArticle"
                (
                    "title",
                    "journalPDF",
                    "publicationID"
                )
                VALUES
                (
                    :title,
                    NULL,
                    :publicationID
                )
                RETURNING "journalID"
            ';

            $articleStmt = $pdo->prepare($articleSQL);


            // =================================================
            // 3. PREPARE AUTHOR INSERT
            // =================================================

            $authorSQL = '
                INSERT INTO "Author"
                (
                    "firstName",
                    "lastName"
                )
                VALUES
                (
                    :firstName,
                    :lastName
                )
                RETURNING "authorID"
            ';

            $authorStmt = $pdo->prepare($authorSQL);


            // =================================================
            // 4. PREPARE ARTICLE-AUTHOR INSERT
            // =================================================

            $articleAuthorSQL = '
                INSERT INTO "ArticleAuthor"
                (
                    "journalID",
                    "authorID"
                )
                VALUES
                (
                    :journalID,
                    :authorID
                )
            ';

            $articleAuthorStmt = $pdo->prepare(
                $articleAuthorSQL
            );


            // =================================================
            // 5. INSERT ARTICLES AND AUTHORS
            // =================================================

            $articleCount = 0;
            $authorCount = 0;


            foreach ($articles as $article) {

                // ------------------------------------------------
                // Get article title
                // ------------------------------------------------

                $title = trim(
                    $article['title'] ?? ''
                );

                $authors = $article['authors'] ?? [];


                // ------------------------------------------------
                // Ignore completely empty article
                // ------------------------------------------------

                if ($title === '' && empty($authors)) {
                    continue;
                }


                // ------------------------------------------------
                // Validate article title
                // ------------------------------------------------

                if ($title === '') {
                    throw new Exception(
                        'Every article must have an article title.'
                    );
                }


                // ------------------------------------------------
                // Make sure article has at least one author
                // ------------------------------------------------

                if (empty($authors)) {
                    throw new Exception(
                        'Every article must have at least one author.'
                    );
                }


                // =================================================
                // INSERT ARTICLE
                // =================================================

                $articleStmt->execute([
                    ':title' => $title,
                    ':publicationID' => $publicationID
                ]);


                // ------------------------------------------------
                // Get generated journalID
                // ------------------------------------------------

                $journalID = $articleStmt->fetchColumn();

                if (!$journalID) {
                    throw new Exception(
                        'Article was inserted, but no journalID was returned.'
                    );
                }


                // =================================================
                // INSERT ALL AUTHORS FOR THIS ARTICLE
                // =================================================

                $validAuthorCount = 0;


                foreach ($authors as $author) {

                    $firstName = trim(
                        $author['firstName'] ?? ''
                    );

                    $lastName = trim(
                        $author['lastName'] ?? ''
                    );


                    // ------------------------------------------------
                    // Ignore completely empty author rows
                    // ------------------------------------------------

                    if (
                        $firstName === '' &&
                        $lastName === ''
                    ) {
                        continue;
                    }


                    // ------------------------------------------------
                    // Validate author
                    // ------------------------------------------------

                    if (
                        $firstName === '' ||
                        $lastName === ''
                    ) {
                        throw new Exception(
                            'Every author must have a first name and last name.'
                        );
                    }


                    // =================================================
                    // INSERT AUTHOR
                    // =================================================

                    $authorStmt->execute([
                        ':firstName' => $firstName,
                        ':lastName' => $lastName
                    ]);


                    // ------------------------------------------------
                    // Get generated authorID
                    // ------------------------------------------------

                    $authorID = $authorStmt->fetchColumn();

                    if (!$authorID) {
                        throw new Exception(
                            'Author was inserted, but no authorID was returned.'
                        );
                    }


                    // =================================================
                    // CONNECT ARTICLE TO AUTHOR
                    // =================================================

                    $articleAuthorStmt->execute([
                        ':journalID' => $journalID,
                        ':authorID' => $authorID
                    ]);


                    $validAuthorCount++;
                    $authorCount++;
                }


                // ------------------------------------------------
                // Make sure article has at least one valid author
                // ------------------------------------------------

                if ($validAuthorCount === 0) {
                    throw new Exception(
                        'Every article must have at least one valid author.'
                    );
                }


                $articleCount++;
            }


            // =================================================
            // MAKE SURE AT LEAST ONE ARTICLE WAS PROCESSED
            // =================================================

            if ($articleCount === 0) {
                throw new Exception(
                    'No valid articles were provided.'
                );
            }


            // =================================================
            // FINISH TRANSACTION
            // =================================================

            if (TEST_MODE) {

                // ------------------------------------------------
                // TEST MODE
                // ------------------------------------------------

                $pdo->rollBack();

                $message =
                    'SQL test successful! ' .
                    'Generated Publication ID: ' .
                    $publicationID .
                    '. ' .
                    $articleCount .
                    ' article(s) and ' .
                    $authorCount .
                    ' author(s) processed. ' .
                    'Everything was rolled back, so nothing was saved.';

                $messageType = 'success';

            } else {

                // ------------------------------------------------
                // ACTUAL SAVE MODE
                // ------------------------------------------------

                $pdo->commit();

                $message =
                    'Archive issue added successfully! ' .
                    'Publication ID: ' .
                    $publicationID .
                    '. ' .
                    $articleCount .
                    ' article(s) and ' .
                    $authorCount .
                    ' author(s) added.';

                $messageType = 'success';
            }


        } catch (Throwable $e) {

            // =================================================
            // ERROR
            // =================================================

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message =
                'SQL test/save failed: ' .
                $e->getMessage();

            $messageType = 'error';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Temporary Archive Form</title>


    <style>

        /* =====================================================
           GENERAL
        ====================================================== */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f5f5;
            color: #222;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;

            background: #ffffff;

            padding: 30px;

            border: 1px solid #ddd;
            border-radius: 8px;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .description {
            margin-top: 0;
            margin-bottom: 25px;
            color: #666;
        }


        /* =====================================================
           TEST MODE NOTICE
        ====================================================== */

        .test-mode {
            padding: 12px 15px;
            margin-bottom: 25px;

            background: #fff8e1;

            border: 1px solid #e0c36d;
            border-radius: 5px;

            color: #6b5500;
        }


        /* =====================================================
           MESSAGES
        ====================================================== */

        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .success {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #256029;
        }

        .error {
            background: #ffebee;
            border: 1px solid #ef9a9a;
            color: #b71c1c;
        }


        /* =====================================================
           SECTIONS
        ====================================================== */

        .section {
            margin-bottom: 30px;
        }

        .section h2 {
            font-size: 20px;
            margin-bottom: 15px;

            padding-bottom: 10px;

            border-bottom: 1px solid #ddd;
        }


        /* =====================================================
           ISSUE FIELDS
        ====================================================== */

        .issue-fields {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 6px;
        }

        input {
            width: 100%;

            padding: 10px 12px;

            border: 1px solid #ccc;
            border-radius: 5px;

            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #800000;
        }


        /* =====================================================
           ARTICLE TABS
        ====================================================== */

        .article-tabs {
            display: flex;

            gap: 5px;

            overflow-x: auto;

            padding-bottom: 5px;

            border-bottom: 1px solid #ddd;
        }

        .article-tab {
            flex-shrink: 0;

            padding: 11px 18px;

            background: #eeeeee;

            color: #333;

            border: 1px solid #ddd;
            border-bottom: none;

            border-radius: 5px 5px 0 0;

            cursor: pointer;

            font-size: 14px;
        }

        .article-tab:hover {
            background: #e2e2e2;
        }

        .article-tab.active {
            background: #800000;
            color: #ffffff;
            border-color: #800000;
        }


        /* =====================================================
           ARTICLE PANEL
        ====================================================== */

        .article-panel {
            display: none;

            border: 1px solid #ddd;
            border-top: none;

            padding: 20px;

            background: #fafafa;
        }

        .article-panel.active {
            display: block;
        }

        .article-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;
        }

        .article-header h3 {
            margin: 0;
            font-size: 17px;
        }


        /* =====================================================
           ARTICLE TITLE
        ====================================================== */

        .article-title {
            margin-bottom: 25px;
        }


        /* =====================================================
           AUTHORS
        ====================================================== */

        .authors-section {
            border-top: 1px solid #ddd;

            padding-top: 20px;
        }

        .authors-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 15px;
        }

        .authors-header h4 {
            margin: 0;

            font-size: 16px;
        }

        .author-row {
            display: grid;

            grid-template-columns: 1fr 1fr auto;

            gap: 10px;

            align-items: end;

            margin-bottom: 12px;

            padding: 12px;

            background: #ffffff;

            border: 1px solid #ddd;

            border-radius: 5px;
        }

        .author-row .field {
            min-width: 0;
        }

        .remove-author-button {
            height: 40px;

            padding: 10px 14px;

            background: #f3f3f3;

            color: #a42821;

            border: 1px solid #ddd;

            border-radius: 5px;

            cursor: pointer;
        }

        .remove-author-button:hover {
            background: #eeeeee;
        }

        .add-author-button {
            padding: 9px 14px;

            background: #eeeeee;

            color: #333;

            border: 1px solid #ddd;

            border-radius: 5px;

            cursor: pointer;
        }

        .add-author-button:hover {
            background: #dddddd;
        }


        /* =====================================================
           REMOVE ARTICLE
        ====================================================== */

        .remove-button {
            border: none;

            background: none;

            color: #a42821;

            cursor: pointer;

            font-size: 14px;
        }

        .remove-button:hover {
            text-decoration: underline;
        }


        /* =====================================================
           BUTTONS
        ====================================================== */

        .button-row {
            display: flex;

            gap: 10px;

            margin-top: 20px;
        }

        button {
            cursor: pointer;

            border: none;

            border-radius: 5px;

            padding: 11px 18px;

            font-size: 14px;
        }

        .add-button {
            background: #eeeeee;
            color: #333;
        }

        .add-button:hover {
            background: #dddddd;
        }

        .save-button {
            background: #800000;
            color: #ffffff;
        }

        .save-button:hover {
            background: #660000;
        }


        /* =====================================================
           INFORMATION NOTE
        ====================================================== */

        .note {
            margin-top: 20px;

            padding: 12px 15px;

            background: #f8f8f8;

            border-left: 4px solid #800000;

            color: #555;

            font-size: 14px;
        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 700px) {

            .issue-fields {
                grid-template-columns: 1fr;
            }

            .author-row {
                grid-template-columns: 1fr;
            }

            .remove-author-button {
                width: 100%;
            }

            .container {
                padding: 20px;
            }
        }

    </style>

</head>


<body>


<div class="container">


    <h1>
        Temporary Archive Issue Form
    </h1>


    <p class="description">
        Use this form to test adding archive issues
        and their articles. PDFs are not included yet.
    </p>


    <!-- =====================================================
         TEST MODE NOTICE
    ====================================================== -->

    <?php if (TEST_MODE): ?>

        <div class="test-mode">

            <strong>TEST MODE:</strong>

            SQL commands will execute normally,
            but the transaction will be rolled back.

            <br><br>

            <strong>
                Nothing will be permanently saved.
            </strong>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         RESULT MESSAGE
    ====================================================== -->

    <?php if ($message !== ''): ?>

        <div class="message <?= htmlspecialchars($messageType) ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         FORM
    ====================================================== -->

    <form method="POST">


        <!-- =================================================
             ISSUE INFORMATION
        ================================================== -->

        <div class="section">

            <h2>
                Issue Information
            </h2>


            <div class="issue-fields">


                <div class="field">

                    <label for="year">
                        Year
                    </label>

                    <input
                        type="number"
                        id="year"
                        name="year"
                        min="1900"
                        max="2100"
                        required
                    >

                </div>


                <div class="field">

                    <label for="volume">
                        Volume
                    </label>

                    <input
                        type="number"
                        id="volume"
                        name="volume"
                        min="1"
                        required
                    >

                </div>


                <div class="field">

                    <label for="number">
                        Number
                    </label>

                    <input
                        type="number"
                        id="number"
                        name="number"
                        min="1"
                        required
                    >

                </div>

            </div>


            <div class="note">

                This will create an archive issue with:

                <br><br>

                <strong>
                    is_current = false
                </strong>

                <br>

                <strong>
                    is_draft = false
                </strong>

                <br><br>

                The editorial note and article PDFs
                will remain NULL for now.

            </div>

        </div>


        <!-- =================================================
             ARTICLES
        ================================================== -->

        <div class="section">

            <h2>
                Articles
            </h2>


            <!-- =================================================
                 ARTICLE TABS
            ================================================== -->

            <div
                id="articleTabs"
                class="article-tabs"
            >
            </div>


            <!-- =================================================
                 ARTICLE PANELS
            ================================================== -->

            <div id="articlesContainer">
                <!-- Article panels are generated by JavaScript -->
            </div>

            <!--BUTTONS -->
            <div class="button-row">
                <button type="button" class="add-button"onclick="addArticle()">+ Add Article</button>
                <button type="submit"class="save-button">
                    <?= TEST_MODE
                        ? 'Test SQL'
                        : 'Save Archive Issue';
                    ?>
                </button>

            </div>

        </div>


    </form>


</div>


<script>

    // ========================================================
    // ARTICLE COUNTER
    // ========================================================

    let articleCount = 0;
    let activeArticleIndex = 0;


    // ========================================================
    // INITIAL ARTICLE
    // ========================================================

    addArticle();


    // ========================================================
    // ADD ARTICLE
    // ========================================================

    function addArticle() {

        const articleIndex = articleCount;

        articleCount++;


        const tabsContainer =
            document.getElementById('articleTabs');

        const articlesContainer =
            document.getElementById('articlesContainer');


        // ====================================================
        // CREATE TAB
        // ====================================================

        const tab =
            document.createElement('button');

        tab.type = 'button';

        tab.className = 'article-tab';

        tab.textContent =
            'Article ' + (articleIndex + 1);

        tab.dataset.index = articleIndex;


        tab.onclick = function () {

            switchArticle(articleIndex);

        };


        tabsContainer.appendChild(tab);


        // ====================================================
        // CREATE ARTICLE PANEL
        // ====================================================

        const panel =
            document.createElement('div');

        panel.className = 'article-panel';

        panel.dataset.index = articleIndex;


        panel.innerHTML = `

            <div class="article-header">

                <h3>
                    Article ${articleIndex + 1}
                </h3>

                <button
                    type="button"
                    class="remove-button"
                    onclick="removeArticle(${articleIndex})"
                >
                    Remove Article
                </button>

            </div>


            <!-- Article Title -->

            <div class="field article-title">

                <label>
                    Article Title
                </label>

                <input
                    type="text"
                    class="article-title-input"
                    name="articles[${articleIndex}][title]"
                    required
                >

            </div>


            <!-- Authors -->

            <div class="authors-section">

                <div class="authors-header">

                    <h4>
                        Authors
                    </h4>

                    <button
                        type="button"
                        class="add-author-button"
                        onclick="addAuthor(${articleIndex})"
                    >
                        + Add Author
                    </button>

                </div>


                <div
                    class="authors-container"
                    data-article-index="${articleIndex}"
                >
                </div>

            </div>

        `;


        articlesContainer.appendChild(panel);


        // ====================================================
        // ADD FIRST AUTHOR
        // ====================================================

        addAuthor(articleIndex);


        // ====================================================
        // SWITCH TO NEW ARTICLE
        // ====================================================

        switchArticle(articleIndex);

    }


    // ========================================================
    // SWITCH ARTICLE TAB
    // ========================================================

    function switchArticle(index) {

        activeArticleIndex = index;


        // ----------------------------------------------------
        // Update tabs
        // ----------------------------------------------------

        const tabs =
            document.querySelectorAll('.article-tab');


        tabs.forEach(function(tab) {

            const tabIndex =
                parseInt(tab.dataset.index);


            if (tabIndex === index) {

                tab.classList.add('active');

            } else {

                tab.classList.remove('active');

            }

        });


        // ----------------------------------------------------
        // Update article panels
        // ----------------------------------------------------

        const panels =
            document.querySelectorAll('.article-panel');


        panels.forEach(function(panel) {

            const panelIndex =
                parseInt(panel.dataset.index);


            if (panelIndex === index) {

                panel.classList.add('active');

            } else {

                panel.classList.remove('active');

            }

        });

    }


    // ========================================================
    // ADD AUTHOR
    // ========================================================

    function addAuthor(articleIndex) {

        const panel =
            document.querySelector(
                `.article-panel[data-index="${articleIndex}"]`
            );


        if (!panel) {
            return;
        }


        const authorsContainer =
            panel.querySelector('.authors-container');


        const authorIndex =
            authorsContainer.querySelectorAll('.author-row').length;


        // ====================================================
        // CREATE AUTHOR ROW
        // ====================================================

        const authorRow =
            document.createElement('div');

        authorRow.className = 'author-row';


        authorRow.innerHTML = `

            <div class="field">

                <label>
                    Author First Name
                </label>

                <input
                    type="text"
                    class="author-first-name"
                    name="articles[${articleIndex}][authors][${authorIndex}][firstName]"
                    required
                >

            </div>


            <div class="field">

                <label>
                    Author Last Name
                </label>

                <input
                    type="text"
                    class="author-last-name"
                    name="articles[${articleIndex}][authors][${authorIndex}][lastName]"
                    required
                >

            </div>


            <button
                type="button"
                class="remove-author-button"
                onclick="removeAuthor(this)"
            >
                Remove
            </button>

        `;


        authorsContainer.appendChild(authorRow);

    }


    // ========================================================
    // REMOVE AUTHOR
    // ========================================================

    function removeAuthor(button) {

        const authorsContainer =
            button.closest('.authors-container');


        const authorRows =
            authorsContainer.querySelectorAll(
                '.author-row'
            );


        // ----------------------------------------------------
        // Keep at least one author
        // ----------------------------------------------------

        if (authorRows.length <= 1) {

            alert(
                'Every article must have at least one author.'
            );

            return;
        }


        button
            .closest('.author-row')
            .remove();


        // ----------------------------------------------------
        // Re-number author input names
        // ----------------------------------------------------

        updateAuthorNames(
            authorsContainer
        );

    }


    // ========================================================
    // UPDATE AUTHOR NAMES
    // ========================================================

    function updateAuthorNames(authorsContainer) {

        const articleIndex =
            authorsContainer.dataset.articleIndex;


        const authorRows =
            authorsContainer.querySelectorAll(
                '.author-row'
            );


        authorRows.forEach(
            function(row, authorIndex) {

                const firstName =
                    row.querySelector(
                        '.author-first-name'
                    );

                const lastName =
                    row.querySelector(
                        '.author-last-name'
                    );


                firstName.name =
                    `articles[${articleIndex}][authors][${authorIndex}][firstName]`;


                lastName.name =
                    `articles[${articleIndex}][authors][${authorIndex}][lastName]`;

            }
        );

    }


    // ========================================================
    // REMOVE ARTICLE
    // ========================================================

    function removeArticle(index) {

        const articlePanels =
            document.querySelectorAll(
                '.article-panel'
            );


        // ----------------------------------------------------
        // Keep at least one article
        // ----------------------------------------------------

        if (articlePanels.length <= 1) {

            alert(
                'An archive issue must have at least one article.'
            );

            return;
        }


        // ----------------------------------------------------
        // Find and remove panel
        // ----------------------------------------------------

        const panel =
            document.querySelector(
                `.article-panel[data-index="${index}"]`
            );


        if (panel) {
            panel.remove();
        }


        // ----------------------------------------------------
        // Find and remove tab
        // ----------------------------------------------------

        const tab =
            document.querySelector(
                `.article-tab[data-index="${index}"]`
            );


        if (tab) {
            tab.remove();
        }


        // ----------------------------------------------------
        // Renumber visible article labels
        // ----------------------------------------------------

        updateArticleNumbers();


        // ----------------------------------------------------
        // Select another article
        // ----------------------------------------------------

        const remainingPanels =
            document.querySelectorAll(
                '.article-panel'
            );


        if (remainingPanels.length > 0) {

            const firstPanel =
                remainingPanels[0];

            const newIndex =
                parseInt(firstPanel.dataset.index);

            switchArticle(newIndex);

        }

    }


    // ========================================================
    // UPDATE ARTICLE NUMBERS
    // ========================================================

    function updateArticleNumbers() {

        const panels =
            document.querySelectorAll(
                '.article-panel'
            );


        const tabs =
            document.querySelectorAll(
                '.article-tab'
            );


        panels.forEach(
            function(panel, index) {

                const heading =
                    panel.querySelector('h3');


                if (heading) {

                    heading.textContent =
                        'Article ' + (index + 1);

                }

            }
        );


        tabs.forEach(
            function(tab, index) {

                tab.textContent =
                    'Article ' + (index + 1);

            }
        );

    }

</script>



</body>

</html>

<?php
//GET JOURNAL ID
$journalID = filter_input(INPUT_GET, 'journalID', FILTER_VALIDATE_INT);

if (!$journalID || $journalID <= 0) {
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

    <?php include 'components/left_sidebar.php'; ?>
    <div class="main-area">
        <?php include 'components/header.php'; ?>
        <main class="content">

            <div class="page-header">

                <div>
                    <h1>Edit Journal</h1>
                    <p>Update the article and publication information</p>
                </div>

                <button type="button" class="back-button" onclick="goBack()">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back
                </button>
            </div>


            <!--LOADING MESSAGE-->
            <div id="loading" class="status-message">
                <i class="fa-solid fa-spinner fa-spin"></i>
                Loading journal...
            </div>

            <!--ERROR MESSAGE-->
            <div id="errorMessage" class="status-message error-message" style="display: none;"></div>

            <!--EDIT FORM-->
            <form id="editForm" enctype="multipart/form-data" style="display: none;">

                <!--HIDDEN JOURNAL ID -->
                <input type="hidden" id="journalID" name="journalID" value="<?= htmlspecialchars((string) $journalID) ?>">

                <!--SECTION 1: ARTICLE INFORMATION-->

                <section class="edit-section">

                    <!-- SECTION HEADER -->
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>

                        <div>
                            <h2>Article Information</h2>
                            <p>Edit the article details and authors.</p>
                        </div>
                    </div>

                    <!--ARTICLE TITLE -->
                    <div class="form-group">
                        <label for="title">
                            Article Title
                            <span class="required">*</span>
                        </label>

                        <input type="text" id="title" name="title" maxlength="500" required placeholder="Enter article title">
                    </div>


                    <!--AUTHOR-->
                    <div class="form-group">
                        <div class="label-row">
                            <label>Authors
                                <span class="required">*</span>
                            </label>


                            <button type="button" id="addAuthorButton" class="add-author-button">
                                <i class="fa-solid fa-plus"></i>
                                Add Author
                            </button>
                        </div>


                        <div
                            id="authorsContainer"
                            class="authors-container">
                        </div>


                        <small class="form-help"> Edit the author's name, add another author, or remove an author</small>
                    </div>


                    <!-- ==================================================
                     CURRENT ARTICLE PDF
                =================================================== -->

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
                                    Current PDF
                                </span>


                                <div id="currentPdf">
                                    Loading...
                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ==================================================
                     REPLACE ARTICLE PDF
                =================================================== -->

                    <div class="form-group">

                        <label>
                            Replace Article PDF
                        </label>


                        <div
                            class="pdf-upload-box"
                            id="articlePdfUploadBox">

                            <div class="pdf-upload-icon">

                                <i class="fa-solid fa-file-pdf"></i>

                            </div>


                            <div class="pdf-upload-text">

                                <strong>
                                    Select a new article PDF
                                </strong>

                                <span id="articlePdfFileName">
                                    No file selected
                                </span>

                            </div>


                            <button
                                type="button"
                                class="choose-pdf"
                                onclick="document.getElementById('journalPDF').click()">

                                <i class="fa-solid fa-folder-open"></i>

                                Choose File

                            </button>


                            <input
                                type="file"
                                id="journalPDF"
                                name="journalPDF"
                                accept="application/pdf">

                        </div>


                        <small class="form-help">
                            Optional. Leave empty to keep the current PDF.
                        </small>

                    </div>

                </section>


                <!-- ==================================================
                 SECTION 2: PUBLICATION ISSUE
            =================================================== -->

                <section class="edit-section">

                    <!-- SECTION HEADER -->

                    <div class="section-header">

                        <div class="section-icon">

                            <i class="fa-solid fa-book"></i>

                        </div>


                        <div>

                            <h2>Publication Issue</h2>

                            <p>
                                Edit the publication issue information.
                            </p>

                        </div>

                    </div>


                    <!-- ==================================================
                     YEAR / VOLUME / NUMBER
                =================================================== -->

                    <div class="publication-grid">

                        <!-- YEAR -->

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


                        <!-- VOLUME -->

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


                        <!-- NUMBER -->

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


                    <!-- ==================================================
                     CURRENT PUBLICATION PDF
                =================================================== -->

                    <div class="form-group">

                        <label>
                            Current Publication PDF
                        </label>


                        <div class="current-file">

                            <div class="file-icon">

                                <i class="fa-solid fa-file-pdf"></i>

                            </div>


                            <div class="file-information">

                                <span class="file-label">
                                    Current Publication PDF
                                </span>


                                <div id="currentPublicationPdf">
                                    Loading...
                                </div>

                            </div>

                        </div>

                    </div>


                    <!--REPLACE PUBLICATION PDF -->
                    <div class="form-group">
                        <label>
                            Replace Publication PDF
                        </label>


                        <div class="pdf-upload-box" id="publicationPdfUploadBox">
                            <div class="pdf-upload-icon">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>


                            <div class="pdf-upload-text">
                                <strong>Select a new publication PDF</strong>
                                <span id="publicationPdfFileName">No file selected</span>
                            </div>


                            <button
                                type="button"
                                class="choose-pdf"
                                onclick="document.getElementById('publicationPDF').click()">

                                <i class="fa-solid fa-folder-open"></i>

                                Choose File

                            </button>


                            <input
                                type="file"
                                id="publicationPDF"
                                name="publicationPDF"
                                accept="application/pdf">

                        </div>

                        <small class="form-help">Optional. Leave empty to keep the current PDF</small>
                    </div>
                </section>


                <!--SAVE / CANCEL-->
                <div class="form-actions">
                    <button type="button" class="cancel-button" onclick="goBack()">
                        Cancel
                    </button>

                    <button type="submit" class="save-button" id="saveButton">
                        Save Changes
                    </button>

                </div>

            </form>

        </main>

    </div>


    <!-- ============================================================
     JAVASCRIPT
============================================================= -->

    <script>
        // ============================================================
        // BASIC SETTINGS
        // ============================================================

        const journalID =
            <?= json_encode($journalID) ?>;

        const API_URL =
            'manage_journal_api.php';


        // ============================================================
        // PAGE INITIALIZATION
        // ============================================================

        document.addEventListener(
            'DOMContentLoaded',
            loadJournal
        );


        // ============================================================
        // BACK BUTTON
        // ============================================================

        function goBack() {

            window.location.href =
                'manage_journal.php';

        }


        // ============================================================
        // LOAD JOURNAL
        // ============================================================

        async function loadJournal() {

            const loading =
                document.getElementById('loading');

            const errorMessage =
                document.getElementById('errorMessage');

            const editForm =
                document.getElementById('editForm');


            try {

                // ----------------------------------------------------
                // CALL API
                // ----------------------------------------------------

                const response =
                    await fetch(
                        `${API_URL}?action=view&journalID=${encodeURIComponent(journalID)}`
                    );


                const responseText =
                    await response.text();


                console.log(
                    'View API:',
                    responseText
                );


                let result;


                try {

                    result =
                        JSON.parse(responseText);

                } catch {

                    throw new Error(
                        'The API did not return valid JSON.'
                    );

                }


                // ----------------------------------------------------
                // CHECK API RESULT
                // ----------------------------------------------------

                if (!result.success) {

                    throw new Error(
                        result.message ||
                        'Unable to load journal.'
                    );

                }


                // ----------------------------------------------------
                // API RETURNS DATA DIRECTLY
                // ----------------------------------------------------

                const article =
                    result.article;

                const publication =
                    result.publication;

                const authors =
                    result.authors || [];


                if (!article || !publication) {

                    throw new Error(
                        'The API returned incomplete journal data.'
                    );

                }


                // ----------------------------------------------------
                // FILL JOURNAL ID
                // ----------------------------------------------------

                document.getElementById(
                        'journalID'
                    ).value =
                    article.journalID || journalID;


                // ----------------------------------------------------
                // FILL ARTICLE TITLE
                // ----------------------------------------------------

                document.getElementById(
                        'title'
                    ).value =
                    article.title || '';


                // ----------------------------------------------------
                // FILL PUBLICATION ISSUE
                // ----------------------------------------------------

                document.getElementById(
                        'year'
                    ).value =
                    publication.year || '';


                document.getElementById(
                        'volume'
                    ).value =
                    publication.volume || '';


                document.getElementById(
                        'number'
                    ).value =
                    publication.number || '';


                // ----------------------------------------------------
                // FILL AUTHORS
                // ----------------------------------------------------

                const authorsContainer =
                    document.getElementById(
                        'authorsContainer'
                    );


                authorsContainer.innerHTML =
                    '';


                if (
                    Array.isArray(authors) &&
                    authors.length > 0
                ) {

                    authors.forEach(
                        author => {

                            addAuthor(
                                author.firstName || '',
                                author.lastName || ''
                            );

                        }
                    );

                } else {

                    addAuthor();

                }


                // ----------------------------------------------------
                // CURRENT ARTICLE PDF
                // ----------------------------------------------------

                showArticlePdf(
                    result.pdfUrl
                );


                // ----------------------------------------------------
                // CURRENT PUBLICATION PDF
                // ----------------------------------------------------

                showPublicationPdf(
                    publication.publicationPDF
                );


                // ----------------------------------------------------
                // SHOW FORM
                // ----------------------------------------------------

                loading.style.display =
                    'none';

                editForm.style.display =
                    'block';


            } catch (error) {

                console.error(error);


                loading.style.display =
                    'none';


                errorMessage.textContent =
                    error.message;


                errorMessage.style.display =
                    'block';

            }

        }


        // ============================================================
        // ADD AUTHOR ROW
        // ============================================================

        function addAuthor(firstName = '', lastName = '') {
            const container = document.getElementById('authorsContainer');


            // AUTHOR ROW
            const row =
                document.createElement('div');


            row.className =
                'author-row';


            // --------------------------------------------------------
            // FIRST NAME
            // --------------------------------------------------------

            const firstNameInput = document.createElement('input');
            firstNameInput.type = 'text';
            firstNameInput.className = 'author-first-name';
            firstNameInput.placeholder = 'First name';
            firstNameInput.value = firstName;
            firstNameInput.required = true;

            // --------------------------------------------------------
            // LAST NAME
            // --------------------------------------------------------

            const lastNameInput =
                document.createElement('input');


            lastNameInput.type =
                'text';


            lastNameInput.className =
                'author-last-name';


            lastNameInput.placeholder =
                'Last name';


            lastNameInput.value =
                lastName;


            lastNameInput.required =
                true;


            // --------------------------------------------------------
            // REMOVE BUTTON
            // --------------------------------------------------------

            const removeButton =
                document.createElement('button');


            removeButton.type =
                'button';


            removeButton.className =
                'remove-author-button';


            removeButton.innerHTML =
                '<i class="fa-solid fa-trash"></i>';


            removeButton.title =
                'Remove author';


            removeButton.onclick =
                function() {

                    row.remove();


                    // Always keep one author input.

                    if (
                        document.querySelectorAll(
                            '.author-row'
                        ).length === 0
                    ) {

                        addAuthor();

                    }

                };


            // --------------------------------------------------------
            // ADD ELEMENTS TO ROW
            // --------------------------------------------------------

            row.appendChild(
                firstNameInput
            );


            row.appendChild(
                lastNameInput
            );


            row.appendChild(
                removeButton
            );


            // --------------------------------------------------------
            // ADD ROW TO CONTAINER
            // --------------------------------------------------------

            container.appendChild(
                row
            );

        }

        // ADD AUTHOR BUTTON
        document.getElementById('addAuthorButton').addEventListener(
            'click',
            function() {
                addAuthor();
            }
        );


        // GET AUTHORS
        function getAuthors() {

            const rows = document.querySelectorAll('.author-row');
            const authors = [];

            rows.forEach(
                row => {

                    const firstName =
                        row.querySelector(
                            '.author-first-name'
                        ).value.trim();


                    const lastName =
                        row.querySelector(
                            '.author-last-name'
                        ).value.trim();


                    if (
                        firstName !== '' &&
                        lastName !== ''
                    ) {

                        authors.push({

                            firstName: firstName,

                            lastName: lastName

                        });

                    }

                }
            );


            return authors;

        }


        // ============================================================
        // SHOW CURRENT ARTICLE PDF
        // ============================================================

        function showArticlePdf(pdfUrl) {

            const container =
                document.getElementById(
                    'currentPdf'
                );


            container.innerHTML =
                '';


            if (!pdfUrl) {

                container.innerHTML =
                    '<span class="no-file">No article PDF available.</span>';

                return;

            }


            const link =
                document.createElement('a');


            link.href =
                pdfUrl;


            link.target =
                '_blank';


            link.rel =
                'noopener noreferrer';


            link.className =
                'pdf-link';


            link.innerHTML =
                '<i class="fa-solid fa-eye"></i> View Current PDF';


            container.appendChild(
                link
            );

        }


        // ============================================================
        // SHOW CURRENT PUBLICATION PDF
        // ============================================================

        function showPublicationPdf(pdfPath) {

            const container =
                document.getElementById(
                    'currentPublicationPdf'
                );


            container.innerHTML =
                '';


            if (!pdfPath) {

                container.innerHTML =
                    '<span class="no-file">No publication PDF available.</span>';

                return;

            }


            const text =
                document.createElement('span');


            text.className =
                'file-path';


            text.textContent =
                pdfPath;


            container.appendChild(
                text
            );

        }


        // ============================================================
        // PDF FILE INPUTS
        // ============================================================

        const journalPDFInput =
            document.getElementById(
                'journalPDF'
            );


        const publicationPDFInput =
            document.getElementById(
                'publicationPDF'
            );


        const articlePdfFileName =
            document.getElementById(
                'articlePdfFileName'
            );


        const publicationPdfFileName =
            document.getElementById(
                'publicationPdfFileName'
            );


        const articlePdfUploadBox =
            document.getElementById(
                'articlePdfUploadBox'
            );


        const publicationPdfUploadBox =
            document.getElementById(
                'publicationPdfUploadBox'
            );


        // ============================================================
        // ARTICLE PDF FILE SELECTION
        // ============================================================

        journalPDFInput.addEventListener(
            'change',
            function() {

                if (
                    this.files &&
                    this.files.length > 0
                ) {

                    articlePdfFileName.textContent =
                        this.files[0].name;


                    articlePdfUploadBox.classList.add(
                        'has-file'
                    );

                } else {

                    articlePdfFileName.textContent =
                        'No file selected';


                    articlePdfUploadBox.classList.remove(
                        'has-file'
                    );

                }

            }
        );


        // ============================================================
        // PUBLICATION PDF FILE SELECTION
        // ============================================================

        publicationPDFInput.addEventListener(
            'change',
            function() {

                if (
                    this.files &&
                    this.files.length > 0
                ) {

                    publicationPdfFileName.textContent =
                        this.files[0].name;


                    publicationPdfUploadBox.classList.add(
                        'has-file'
                    );

                } else {

                    publicationPdfFileName.textContent =
                        'No file selected';


                    publicationPdfUploadBox.classList.remove(
                        'has-file'
                    );

                }

            }
        );

        // SAVE FORM

        document.getElementById('editForm').addEventListener('submit', saveChanges);

        // SAVE CHANGES

        async function saveChanges(event) {

            event.preventDefault();

            const saveButton = document.getElementById('saveButton');


            // GET AUTHORS
            const authors = getAuthors();


            if (authors.length === 0) {

                alert('Please add at least one author.');
                return;

            }


            // --------------------------------------------------------
            // DISABLE SAVE BUTTON
            // --------------------------------------------------------

            saveButton.disabled =
                true;


            saveButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';


            try {

                // ----------------------------------------------------
                // CREATE FORM DATA
                // ----------------------------------------------------

                const form =
                    document.getElementById(
                        'editForm'
                    );


                const formData =
                    new FormData(form);


                // ----------------------------------------------------
                // ACTION
                // ----------------------------------------------------

                formData.set(
                    'action',
                    'update'
                );


                // ----------------------------------------------------
                // AUTHORS
                // ----------------------------------------------------

                formData.set(
                    'authors',
                    JSON.stringify(authors)
                );


                // ----------------------------------------------------
                // SEND UPDATE
                // ----------------------------------------------------

                const response =
                    await fetch(
                        API_URL, {
                            method: 'POST',
                            body: formData
                        }
                    );


                const responseText = await response.text();

                console.log(
                    'Update API:',
                    responseText
                );


                let result;


                try {
                    result = JSON.parse(responseText);

                } catch {

                    throw new Error(
                        'The API did not return valid JSON.'
                    );

                }

                // CHECK RESULT
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to update journal.');
                }

                // SUCCESS
                alert(result.message || 'Journal updated successfully.');
                window.location.href = 'manage_journal.php';
            } catch (error) {
                console.error(error);

                alert(error.message || 'An error occurred while saving.');

                // RE-ENABLE SAVE BUTTON
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fa-solid fa-check"></i> Save Changes';
            }

        }
    </script>
</body>

</html>
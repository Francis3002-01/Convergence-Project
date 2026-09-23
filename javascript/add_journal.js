let articles = [];

let currentArticleIndex = 0;

let publicationPdfFile = null;

let articleDeleteIndex = null;

let authorDeleteIndex = null;


// ==============================
// BASIC HELPERS
// ==============================

function escapeHtml(value) {

    if (value === null || value === undefined) {
        return "";
    }

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}


function createArticle() {

    return {
        journalID: null,
        title: "",
        pdf: null,
        authors: [
            {
                firstName: "",
                lastName: ""
            }
        ]
    };

}


// ==============================
// INITIALIZE FORM
// ==============================

function initializeAddJournalPage() {

    resetPublicationForm();

    const publicationPdfUploadBox =
        document.getElementById("publicationPdfUploadBox");

    const publicationPdfInput =
        document.getElementById("publicationPDF");

    const articlePdfUploadBox =
        document.getElementById("articlePdfUploadBox");

    const articlePdfInput =
        document.getElementById("pdfFile");


    // Publication PDF upload

    if (publicationPdfUploadBox && publicationPdfInput) {
        setupPublicationPdfUpload();
    }


    // Article PDF upload

    if (articlePdfUploadBox && articlePdfInput) {
        setupPdfUpload();
    }

}


// ==============================
// PUBLICATION FORM
// ==============================

function resetPublicationForm() {

    const yearSelect =
        document.getElementById("year");

    const volumeInput =
        document.getElementById("volume");

    const numberInput =
        document.getElementById("number");


    if (yearSelect) {

        yearSelect.innerHTML = "";

        const currentYear =
            new Date().getFullYear();

        for (
            let year = currentYear;
            year >= 2000;
            year--
        ) {

            const option =
                document.createElement("option");

            option.value = year;

            option.textContent = year;

            yearSelect.appendChild(option);
        }

    }


    if (volumeInput) {
        volumeInput.value = "";
    }


    if (numberInput) {
        numberInput.value = "";
    }


    articles = [createArticle()];

    currentArticleIndex = 0;


    resetPublicationPdfInput();


    const publicationStep =
        document.getElementById("publicationStep");

    const articleStep =
        document.getElementById("articleStep");


    if (publicationStep) {
        publicationStep.style.display = "block";
    }


    if (articleStep) {
        articleStep.style.display = "none";
    }


    renderArticleTabs();

    loadArticle(0);

}


// ==============================
// GO TO ARTICLES
// ==============================

function goToArticles() {

    const year =
        document.getElementById("year");

    const volume =
        document.getElementById("volume");

    const number =
        document.getElementById("number");


    if (!year || !volume || !number) {
        return;
    }


    if (!year.value) {

        alert("Please select the publication year.");

        return;
    }


    if (!volume.value.trim()) {

        alert("Please enter the volume.");

        volume.focus();

        return;
    }


    if (!number.value.trim()) {

        alert("Please enter the issue number.");

        number.focus();

        return;
    }


    if (!publicationPdfFile) {

        alert("Please upload the Publication PDF.");

        return;
    }


    // Save publication information for display

    const displayYear =
        document.getElementById("displayYear");

    const displayVolume =
        document.getElementById("displayVolume");

    const displayNumber =
        document.getElementById("displayNumber");


    if (displayYear) {
        displayYear.textContent = year.value;
    }


    if (displayVolume) {
        displayVolume.textContent = volume.value;
    }


    if (displayNumber) {
        displayNumber.textContent = number.value;
    }


    // Move to article step

    const publicationStep =
        document.getElementById("publicationStep");

    const articleStep =
        document.getElementById("articleStep");


    if (publicationStep) {
        publicationStep.style.display = "none";
    }


    if (articleStep) {
        articleStep.style.display = "block";
    }


    renderArticleTabs();

    loadArticle(currentArticleIndex);

}


// ==============================
// GO BACK TO PUBLICATION
// ==============================

function goToPublication() {

    saveCurrentArticle();


    const publicationStep =
        document.getElementById("publicationStep");

    const articleStep =
        document.getElementById("articleStep");


    if (articleStep) {
        articleStep.style.display = "none";
    }


    if (publicationStep) {
        publicationStep.style.display = "block";
    }


    setupPublicationPdfUpload();

}


// ==============================
// ARTICLE TABS
// ==============================

function renderArticleTabs() {

    const articleTabs =
        document.getElementById("articleTabs");


    if (!articleTabs) {
        return;
    }


    articleTabs.innerHTML = "";


    articles.forEach((article, index) => {

        const tab =
            document.createElement("button");


        tab.type = "button";

        tab.className = "article-tab";


        if (index === currentArticleIndex) {
            tab.classList.add("active");
        }


        tab.textContent =
            `Article ${index + 1}`;


        tab.addEventListener("click", function () {

            saveCurrentArticle();

            currentArticleIndex = index;

            renderArticleTabs();

            loadArticle(index);

        });


        articleTabs.appendChild(tab);

    });

}


// ==============================
// LOAD ARTICLE
// ==============================

function loadArticle(index) {

    if (!articles[index]) {
        return;
    }


    currentArticleIndex = index;


    const article =
        articles[index];


    const articleHeading =
        document.getElementById("articleHeading");

    const articleTitle =
        document.getElementById("articleTitle");

    const articleDeleteButton =
        document.getElementById("deleteArticleButton");


    if (articleHeading) {

        articleHeading.textContent =
            `Article ${index + 1}`;

    }


    if (articleTitle) {

        articleTitle.value =
            article.title || "";

    }


    // Only allow deleting an article
    // if there is more than one

    if (articleDeleteButton) {

        articleDeleteButton.style.display =
            articles.length > 1
                ? "inline-flex"
                : "none";

    }


    renderAuthors();


    resetPdfInput();


    // Restore existing PDF information

    if (article.pdf) {

        const fileName =
            document.getElementById("pdfFileName");

        const uploadTitle =
            document.querySelector(
                "#articlePdfUploadBox .upload-title"
            );

        const undoButton =
            document.getElementById("articlePdfUndo");


        if (fileName) {

            fileName.textContent =
                article.pdf.name;

        }


        if (uploadTitle) {

            uploadTitle.hidden = true;

        }


        if (undoButton) {

            undoButton.hidden = false;

        }


        const pdfInput =
            document.getElementById("pdfFile");


        if (pdfInput) {

            try {

                const dataTransfer =
                    new DataTransfer();

                dataTransfer.items.add(article.pdf);

                pdfInput.files =
                    dataTransfer.files;

            } catch (error) {

                console.warn(
                    "Unable to restore PDF input:",
                    error
                );

            }

        }

    }


    renderArticleTabs();

}


// ==============================
// SAVE CURRENT ARTICLE
// ==============================

function saveCurrentArticle() {

    const article =
        articles[currentArticleIndex];


    if (!article) {
        return;
    }


    const titleInput =
        document.getElementById("articleTitle");

    const pdfInput =
        document.getElementById("pdfFile");


    if (titleInput) {

        article.title =
            titleInput.value.trim();

    }


    if (
        pdfInput &&
        pdfInput.files &&
        pdfInput.files.length > 0
    ) {

        article.pdf =
            pdfInput.files[0];

    }


    // Save authors

    const authorRows =
        document.querySelectorAll(".author-row");


    article.authors = [];


    authorRows.forEach(row => {

        const firstNameInput =
            row.querySelector(".author-first-name");

        const lastNameInput =
            row.querySelector(".author-last-name");


        article.authors.push({

            firstName:
                firstNameInput
                    ? firstNameInput.value.trim()
                    : "",

            lastName:
                lastNameInput
                    ? lastNameInput.value.trim()
                    : ""

        });

    });


    // Make sure there is always
    // at least one author

    if (article.authors.length === 0) {

        article.authors.push({

            firstName: "",

            lastName: ""

        });

    }

}


// ==============================
// ADD ARTICLE
// ==============================

function addArticle() {

    saveCurrentArticle();


    articles.push(
        createArticle()
    );


    currentArticleIndex =
        articles.length - 1;


    renderArticleTabs();

    loadArticle(
        currentArticleIndex
    );

}


// ==============================
// DELETE ARTICLE
// ==============================

function requestDeleteArticle() {

    if (articles.length <= 1) {

        alert(
            "At least one article is required."
        );

        return;
    }


    articleDeleteIndex =
        currentArticleIndex;


    const modal =
        document.getElementById(
            "confirmationModal"
        );

    const message =
        document.getElementById(
            "confirmationMessage"
        );


    if (message) {

        message.textContent =
            `Are you sure you want to remove Article ${articleDeleteIndex + 1}?`;

    }


    if (modal) {
        modal.style.display = "flex";
    }


    const confirmButton =
        document.getElementById(
            "confirmDeleteButton"
        );


    if (confirmButton) {

        confirmButton.onclick =
            deleteArticle;

    }

}


function deleteArticle() {

    if (
        articleDeleteIndex === null ||
        articleDeleteIndex < 0 ||
        articleDeleteIndex >= articles.length
    ) {

        closeConfirmationModal();

        return;
    }


    articles.splice(
        articleDeleteIndex,
        1
    );


    if (
        currentArticleIndex >=
        articles.length
    ) {

        currentArticleIndex =
            articles.length - 1;

    }


    if (currentArticleIndex < 0) {
        currentArticleIndex = 0;
    }


    articleDeleteIndex = null;


    closeConfirmationModal();


    renderArticleTabs();

    loadArticle(
        currentArticleIndex
    );

}


// ==============================
// AUTHORS
// ==============================

function renderAuthors() {

    const authorsContainer =
        document.getElementById("authors");


    if (!authorsContainer) {
        return;
    }


    const article =
        articles[currentArticleIndex];


    if (!article) {
        return;
    }


    authorsContainer.innerHTML = "";


    article.authors.forEach(
        (author, index) => {

            const row =
                document.createElement("div");


            row.className =
                "author-row";


            row.innerHTML = `
                <div class="author-fields">

                    <div class="form-group">

                        <label>First Name</label>

                        <input
                            type="text"
                            class="author-first-name"
                            value="${escapeHtml(author.firstName)}"
                            placeholder="Enter first name"
                        >

                    </div>


                    <div class="form-group">

                        <label>Last Name</label>

                        <input
                            type="text"
                            class="author-last-name"
                            value="${escapeHtml(author.lastName)}"
                            placeholder="Enter last name"
                        >

                    </div>

                    ${
                        article.authors.length > 1
                        ? `
                            <button
                                type="button"
                                class="delete-author-btn"
                                onclick="requestDeleteAuthor(${index})"
                                title="Remove author"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        `
                        : ""
                    }

                </div>
            `;


            authorsContainer.appendChild(row);

        }
    );

}


function addAuthor() {

    saveCurrentArticle();


    articles[currentArticleIndex]
        .authors.push({

            firstName: "",

            lastName: ""

        });


    renderAuthors();

}


function requestDeleteAuthor(index) {

    const article =
        articles[currentArticleIndex];


    if (!article) {
        return;
    }


    if (article.authors.length <= 1) {

        alert(
            "At least one author is required."
        );

        return;
    }


    authorDeleteIndex = index;


    const modal =
        document.getElementById(
            "confirmationModal"
        );

    const message =
        document.getElementById(
            "confirmationMessage"
        );


    if (message) {

        message.textContent =
            "Are you sure you want to remove this author?";

    }


    if (modal) {
        modal.style.display = "flex";
    }


    const confirmButton =
        document.getElementById(
            "confirmDeleteButton"
        );


    if (confirmButton) {

        confirmButton.onclick =
            deleteAuthor;

    }

}


function deleteAuthor() {

    const article =
        articles[currentArticleIndex];


    if (!article) {

        closeConfirmationModal();

        return;
    }


    if (
        authorDeleteIndex === null ||
        authorDeleteIndex < 0 ||
        authorDeleteIndex >= article.authors.length
    ) {

        closeConfirmationModal();

        return;
    }


    article.authors.splice(
        authorDeleteIndex,
        1
    );


    authorDeleteIndex = null;


    closeConfirmationModal();


    renderAuthors();

}


// ==============================
// MODALS
// ==============================

function closeModal() {

    const modal =
        document.getElementById(
            "confirmationModal"
        );


    if (modal) {
        modal.style.display = "none";
    }

}


function closeConfirmationModal() {

    const modal =
        document.getElementById(
            "confirmationModal"
        );


    if (modal) {
        modal.style.display = "none";
    }


    articleDeleteIndex = null;

    authorDeleteIndex = null;

}


// ==============================
// PUBLICATION PDF UPLOAD
// ==============================

function setupPublicationPdfUpload() {

    const uploadBox =
        document.getElementById(
            "publicationPdfUploadBox"
        );

    const fileInput =
        document.getElementById(
            "publicationPDF"
        );

    const undoButton =
        document.getElementById(
            "publicationPdfUndo"
        );


    if (!uploadBox || !fileInput) {
        return;
    }


    // Prevent duplicate event listeners

    if (
        uploadBox.dataset.initialized ===
        "true"
    ) {

        return;
    }


    uploadBox.dataset.initialized =
        "true";


    // ==============================
    // CLICK / FILE SELECT
    // ==============================

    fileInput.addEventListener(
        "change",
        function () {

            if (
                this.files &&
                this.files.length > 0
            ) {

                handlePublicationPdfFile(
                    this.files[0]
                );

            }

        }
    );


    // ==============================
    // DRAG ENTER
    // ==============================

    uploadBox.addEventListener(
        "dragenter",
        function (event) {

            event.preventDefault();

            event.stopPropagation();

            uploadBox.classList.add(
                "drag-over"
            );

        }
    );


    // ==============================
    // DRAG OVER
    // ==============================

    uploadBox.addEventListener(
        "dragover",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            if (event.dataTransfer) {

                event.dataTransfer.dropEffect =
                    "copy";

            }


            uploadBox.classList.add(
                "drag-over"
            );

        }
    );


    // ==============================
    // DRAG LEAVE
    // ==============================

    uploadBox.addEventListener(
        "dragleave",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            if (
                !uploadBox.contains(
                    event.relatedTarget
                )
            ) {

                uploadBox.classList.remove(
                    "drag-over"
                );

            }

        }
    );


    // ==============================
    // DROP
    // ==============================

    uploadBox.addEventListener(
        "drop",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            uploadBox.classList.remove(
                "drag-over"
            );


            const files =
                event.dataTransfer
                    ? event.dataTransfer.files
                    : null;


            if (
                !files ||
                files.length === 0
            ) {

                return;
            }


            const file =
                files[0];


            // Put the dropped file
            // into the actual file input

            try {

                const dataTransfer =
                    new DataTransfer();


                dataTransfer.items.add(
                    file
                );


                fileInput.files =
                    dataTransfer.files;

            } catch (error) {

                console.warn(
                    "Unable to sync dropped publication PDF:",
                    error
                );

            }


            handlePublicationPdfFile(
                file
            );

        }
    );


    // ==============================
    // UNDO
    // ==============================

    if (undoButton) {

        undoButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                event.stopPropagation();


                resetPublicationPdfInput();

            }
        );

    }

}


function handlePublicationPdfFile(file) {

    if (!file) {
        return;
    }


    const isPdf =
        file.type === "application/pdf" ||
        file.name
            .toLowerCase()
            .endsWith(".pdf");


    if (!isPdf) {

        alert(
            "Please select a PDF file."
        );

        resetPublicationPdfInput();

        return;
    }


    const maxSize =
        40 * 1024 * 1024;


    if (file.size > maxSize) {

        alert(
            "The PDF file must not exceed 40 MB."
        );

        resetPublicationPdfInput();

        return;
    }


    publicationPdfFile =
        file;


    const fileName =
        document.getElementById(
            "publicationPdfFileName"
        );

    const uploadTitle =
        document.querySelector(
            "#publicationPdfUploadBox .upload-title"
        );

    const undoButton =
        document.getElementById(
            "publicationPdfUndo"
        );


    if (fileName) {

        fileName.textContent =
            file.name;

    }


    if (uploadTitle) {

        uploadTitle.hidden =
            true;

    }


    if (undoButton) {

        undoButton.hidden =
            false;

    }

}


function resetPublicationPdfInput() {

    publicationPdfFile =
        null;


    const fileInput =
        document.getElementById(
            "publicationPDF"
        );

    const fileName =
        document.getElementById(
            "publicationPdfFileName"
        );

    const uploadTitle =
        document.querySelector(
            "#publicationPdfUploadBox .upload-title"
        );

    const undoButton =
        document.getElementById(
            "publicationPdfUndo"
        );

    const uploadBox =
        document.getElementById(
            "publicationPdfUploadBox"
        );


    if (fileInput) {

        fileInput.value = "";

    }


    if (fileName) {

        fileName.textContent =
            "No file selected";

    }


    if (uploadTitle) {

        uploadTitle.hidden =
            false;

    }


    if (undoButton) {

        undoButton.hidden =
            true;

    }


    if (uploadBox) {

        uploadBox.classList.remove(
            "drag-over"
        );

    }

}


// ==============================
// ARTICLE PDF UPLOAD
// ==============================

function setupPdfUpload() {

    const uploadBox =
        document.getElementById(
            "articlePdfUploadBox"
        );

    const fileInput =
        document.getElementById(
            "pdfFile"
        );

    const undoButton =
        document.getElementById(
            "articlePdfUndo"
        );


    if (!uploadBox || !fileInput) {
        return;
    }


    // Prevent duplicate event listeners

    if (
        uploadBox.dataset.initialized ===
        "true"
    ) {

        return;
    }


    uploadBox.dataset.initialized =
        "true";


    // ==============================
    // CLICK / FILE SELECT
    // ==============================

    fileInput.addEventListener(
        "change",
        function () {

            if (
                this.files &&
                this.files.length > 0
            ) {

                handlePdfFile(
                    this.files[0]
                );

            }

        }
    );


    // ==============================
    // DRAG ENTER
    // ==============================

    uploadBox.addEventListener(
        "dragenter",
        function (event) {

            event.preventDefault();

            event.stopPropagation();

            uploadBox.classList.add(
                "drag-over"
            );

        }
    );


    // ==============================
    // DRAG OVER
    // ==============================

    uploadBox.addEventListener(
        "dragover",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            if (event.dataTransfer) {

                event.dataTransfer.dropEffect =
                    "copy";

            }


            uploadBox.classList.add(
                "drag-over"
            );

        }
    );


    // ==============================
    // DRAG LEAVE
    // ==============================

    uploadBox.addEventListener(
        "dragleave",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            if (
                !uploadBox.contains(
                    event.relatedTarget
                )
            ) {

                uploadBox.classList.remove(
                    "drag-over"
                );

            }

        }
    );


    // ==============================
    // DROP
    // ==============================

    uploadBox.addEventListener(
        "drop",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            uploadBox.classList.remove(
                "drag-over"
            );


            const files =
                event.dataTransfer
                    ? event.dataTransfer.files
                    : null;


            if (
                !files ||
                files.length === 0
            ) {

                return;
            }


            const file =
                files[0];


            // Put the dropped file
            // into the actual file input

            try {

                const dataTransfer =
                    new DataTransfer();


                dataTransfer.items.add(
                    file
                );


                fileInput.files =
                    dataTransfer.files;

            } catch (error) {

                console.warn(
                    "Unable to sync dropped article PDF:",
                    error
                );

            }


            handlePdfFile(
                file
            );

        }
    );


    // ==============================
    // UNDO
    // ==============================

    if (undoButton) {

        undoButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                event.stopPropagation();


                undoPdfFile();

            }
        );

    }

}


function handlePdfFile(file) {

    if (!file) {
        return;
    }


    const isPdf =
        file.type === "application/pdf" ||
        file.name
            .toLowerCase()
            .endsWith(".pdf");


    if (!isPdf) {

        alert(
            "Please select a PDF file."
        );

        resetPdfInput();

        return;
    }


    const maxSize =
        40 * 1024 * 1024;


    if (file.size > maxSize) {

        alert(
            "The PDF file must not exceed 40 MB."
        );

        resetPdfInput();

        return;
    }


    if (
        articles[currentArticleIndex]
    ) {

        articles[currentArticleIndex].pdf =
            file;

    }


    const fileName =
        document.getElementById(
            "pdfFileName"
        );

    const uploadTitle =
        document.querySelector(
            "#articlePdfUploadBox .upload-title"
        );

    const undoButton =
        document.getElementById(
            "articlePdfUndo"
        );


    if (fileName) {

        fileName.textContent =
            file.name;

    }


    if (uploadTitle) {

        uploadTitle.hidden =
            true;

    }


    if (undoButton) {

        undoButton.hidden =
            false;

    }

}


function resetPdfInput() {

    const fileInput =
        document.getElementById(
            "pdfFile"
        );

    const fileName =
        document.getElementById(
            "pdfFileName"
        );

    const uploadTitle =
        document.querySelector(
            "#articlePdfUploadBox .upload-title"
        );

    const undoButton =
        document.getElementById(
            "articlePdfUndo"
        );

    const uploadBox =
        document.getElementById(
            "articlePdfUploadBox"
        );


    if (fileInput) {

        fileInput.value = "";

    }


    if (fileName) {

        fileName.textContent =
            "No file selected";

    }


    if (uploadTitle) {

        uploadTitle.hidden =
            false;

    }


    if (undoButton) {

        undoButton.hidden =
            true;

    }


    if (uploadBox) {

        uploadBox.classList.remove(
            "drag-over"
        );

    }

}


function undoPdfFile() {

    if (
        articles[currentArticleIndex]
    ) {

        articles[currentArticleIndex].pdf =
            null;

    }


    resetPdfInput();

}


// ==============================
// SAVE / PUBLISH JOURNAL
// ==============================

async function savePublication(
    mode = "draft"
) {

    saveCurrentArticle();


    const year =
        document.getElementById(
            "year"
        )?.value.trim();

    const volume =
        document.getElementById(
            "volume"
        )?.value.trim();

    const number =
        document.getElementById(
            "number"
        )?.value.trim();


    // ==============================
    // BASIC VALIDATION
    // ==============================

    if (!year) {

        alert(
            "Please select the publication year."
        );

        return;
    }


    if (!volume) {

        alert(
            "Please enter the volume."
        );

        return;
    }


    if (!number) {

        alert(
            "Please enter the issue number."
        );

        return;
    }


    if (!publicationPdfFile) {

        alert(
            "Please upload the Publication PDF."
        );

        return;
    }


    if (articles.length === 0) {

        alert(
            "Please add at least one article."
        );

        return;
    }


    // ==============================
    // VALIDATE ARTICLES
    // ==============================

    for (
        let i = 0;
        i < articles.length;
        i++
    ) {

        const article =
            articles[i];


        if (!article.title.trim()) {

            alert(
                `Please enter a title for Article ${i + 1}.`
            );


            currentArticleIndex =
                i;


            renderArticleTabs();

            loadArticle(i);


            return;
        }


        if (!article.pdf) {

            alert(
                `Please upload a PDF for Article ${i + 1}.`
            );


            currentArticleIndex =
                i;


            renderArticleTabs();

            loadArticle(i);


            return;
        }


        if (
            !article.authors ||
            article.authors.length === 0
        ) {

            alert(
                `Please add at least one author for Article ${i + 1}.`
            );


            currentArticleIndex =
                i;


            renderArticleTabs();

            loadArticle(i);


            return;
        }


        for (
            let authorIndex = 0;
            authorIndex < article.authors.length;
            authorIndex++
        ) {

            const author =
                article.authors[
                    authorIndex
                ];


            if (
                !author.firstName.trim() ||
                !author.lastName.trim()
            ) {

                alert(
                    `Please complete Author ${authorIndex + 1} for Article ${i + 1}.`
                );


                currentArticleIndex =
                    i;


                renderArticleTabs();

                loadArticle(i);


                return;
            }

        }

    }


    // ==============================
    // CONFIRM PUBLISH
    // ==============================

    if (mode === "publish") {

        const confirmed =
            confirm(
                "Are you sure you want to publish this journal?"
            );


        if (!confirmed) {
            return;
        }

    }


    // ==============================
    // PREPARE FORM DATA
    // ==============================

    const formData =
        new FormData();


    formData.append(
        "action",
        "add"
    );


    formData.append(
        "mode",
        mode
    );


    formData.append(
        "year",
        year
    );


    formData.append(
        "volume",
        volume
    );


    formData.append(
        "number",
        number
    );


    formData.append(
        "publicationPDF",
        publicationPdfFile
    );


    // Send article information
    // without PDF files

    const articleData =
        articles.map(article => ({

            journalID:
                article.journalID || null,

            title:
                article.title,

            authors:
                article.authors.map(
                    author => ({

                        firstName:
                            author.firstName,

                        lastName:
                            author.lastName

                    })
                )

        }));


    formData.append(
        "articleData",
        JSON.stringify(articleData)
    );


    // Attach article PDFs

    articles.forEach(
        (article, index) => {

            formData.append(
                `pdf_${index}`,
                article.pdf
            );

        }
    );


    // ==============================
    // SEND TO BACKEND
    // ==============================

    try {

        const response =
            await fetch(
                "manage_journal_api.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const result =
            await response.json();


        if (
            !response.ok ||
            !result.success
        ) {

            throw new Error(
                result.message ||
                "Failed to save the journal."
            );

        }


        alert(
            mode === "publish"
                ? "Journal published successfully."
                : "Journal saved as draft successfully."
        );


        // Return to Manage Journals

        window.location.href =
            "manage_journals.php";


    } catch (error) {

        console.error(
            "Save publication error:",
            error
        );


        alert(
            error.message ||
            "An error occurred while saving the journal."
        );

    }

}

// ==============================
// CANCEL ADD JOURNAL
// ==============================

function cancelAddJournal() {

    const confirmed = confirm(
        "Are you sure you want to cancel? Any unsaved changes will be lost."
    );

    if (!confirmed) {
        return;
    }

    window.location.href = "manage_journal.php";
}


// ==============================
// PAGE LOAD
// ==============================

document.addEventListener(
    "DOMContentLoaded",
    initializeAddJournalPage
);
let pendingPublicationMode = null;
let articles = [];
let currentArticleIndex = 0;
let publicationPdfFile = null;
let editingPublicationID = null;
let existingPublicationPdf = null;
let articleDeleteIndex = null;
let authorDeleteIndex = null;
/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */
/*
 * Escape HTML characters before putting values into innerHTML.
 * This prevents saved author names or titles from being
 * interpreted as HTML.
 */
function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/*
 * Create a new empty article object.
 */
function createArticle() {
  return {
    journalID: null,
    title: "",
    pdf: null,
    existingPdf: null,

    authors: [
      {
        firstName: "",
        lastName: "",
      },
    ],
  };
}

/*
 * Get the file name from a stored file path.
 */
function getFileNameFromPath(path) {
  if (!path) {
    return "";
  }

  const normalizedPath = String(path).replace(/\\/g, "/");
  const parts = normalizedPath.split("/");

  return parts[parts.length - 1];
}

/* =========================================================
   MESSAGE MODAL
   Replaces browser alert()
   ========================================================= */

/*
 * Show a general information/message modal.
 */
function showMessage(title, message) {
  const modal = document.getElementById("messageModal");
  const titleElement = document.getElementById("messageTitle");
  const messageElement = document.getElementById("messageText");

  if (!modal || !titleElement || !messageElement) {
    console.error("Message modal elements not found.");
    return;
  }

  titleElement.textContent = title;
  messageElement.textContent = message;

  modal.classList.add("show");
}

/*
 * Close the general information/message modal.
 */
function closeMessageModal() {
  const modal = document.getElementById("messageModal");
  if (modal) {
    modal.classList.remove("show");
  }
}

/* =========================================================
   CONFIRMATION MODAL
   Used for removing articles/authors
   ========================================================= */

/*
 * Close the remove confirmation modal.
 */
function closeConfirmationModal() {
  const modal = document.getElementById("confirmationModal");

  if (modal) {
    modal.classList.remove("show");
  }

  articleDeleteIndex = null;
  authorDeleteIndex = null;
}

/* =========================================================
   CANCEL CONFIRMATION MODAL
   ========================================================= */
/*
 * Open the cancel confirmation modal.
 */
function cancelAddJournal() {
  const modal = document.getElementById("cancelConfirmationModal");
  if (!modal) {
    console.error("Cancel confirmation modal not found.");
    return;
  }

  modal.classList.add("show");
}

/*
 * Close the cancel confirmation modal.
 */
function closeCancelConfirmationModal() {
  const modal = document.getElementById("cancelConfirmationModal");

  if (modal) {
    modal.classList.remove("show");
  }
}
/*
 * Confirm cancellation.
 *
 * Goes back to Manage Journal.
 */
function confirmCancelAddJournal() {
  window.location.href = "manage_journal.php";
}

/* =========================================================
   PUBLISH CONFIRMATION MODAL
   ========================================================= */
/*
 * Close the publish confirmation modal.
 */
function closePublishConfirmationModal() {
  const modal = document.getElementById("publishConfirmationModal");

  if (modal) {
    modal.classList.remove("show");
  }

  pendingPublicationMode = null;
}

/*
 * Confirm publishing.
 */
function confirmPublish() {
  closePublishConfirmationModal();
  submitPublication("publish");
}

/* =========================================================
   INITIALIZE PAGE
   ========================================================= */
async function initializeAddJournalPage() {
  const urlParams = new URLSearchParams(window.location.search);
  const publicationID = urlParams.get("publicationID");

  /*
   * If publicationID exists, this page is being used
   * to continue editing an existing draft.
   */
  if (publicationID) {
    const parsedID = Number(publicationID);

    if (Number.isInteger(parsedID) && parsedID > 0) {
      editingPublicationID = parsedID;
      resetPublicationForm();
      await loadDraftPublication(editingPublicationID);
    } else {
      console.error("Invalid publicationID.");
      resetPublicationForm();
    }
  } else {
    /*
     * No publicationID means this is a new journal.
     */
    resetPublicationForm();
  }

  setupPublicationPdfUpload();
  setupArticlePdfUpload();

  /*
   * Connect the publish confirmation button.
   */
  const confirmPublishButton = document.getElementById("confirmPublishButton");

  if (confirmPublishButton) {
    confirmPublishButton.onclick = function () {
      confirmPublish();
    };
  }
}

function resetPublicationForm() {
  const year = document.getElementById("year");
  const volume = document.getElementById("volume");
  const number = document.getElementById("number");

  if (year) {
    year.innerHTML = "";

    const currentYear = new Date().getFullYear();

    for (let y = currentYear; y >= 2000; y--) {
      const option = document.createElement("option");

      option.value = y;
      option.textContent = y;

      year.appendChild(option);
    }

    year.value = currentYear;
  }

  if (volume) {
    volume.value = "";
  }

  if (number) {
    number.value = "";
  }

  /*
   * Start with one empty article.
   */
  articles = [createArticle()];

  currentArticleIndex = 0;

  /*
   * Reset publication PDF state.
   */
  publicationPdfFile = null;
  existingPublicationPdf = null;

  const publicationPDF = document.getElementById("publicationPDF");

  if (publicationPDF) {
    publicationPDF.value = "";
  }

  const publicationPdfFileName = document.getElementById(
    "publicationPdfFileName",
  );

  if (publicationPdfFileName) {
    publicationPdfFileName.textContent = "No file selected";
  }

  const publicationPdfUploadBox = document.getElementById(
    "publicationPdfUploadBox",
  );

  if (publicationPdfUploadBox) {
    publicationPdfUploadBox.classList.remove("has-file");
  }

  const publicationPdfUndo = document.getElementById("publicationPdfUndo");

  if (publicationPdfUndo) {
    publicationPdfUndo.hidden = true;
  }

  const publicationPdfUploadTitle = document.querySelector(
    "#publicationPdfUploadBox .upload-title",
  );

  if (publicationPdfUploadTitle) {
    publicationPdfUploadTitle.style.display = "";
  }

  /*
   * Show publication step first.
   */
  const publicationStep = document.getElementById("publicationStep");

  const articleStep = document.getElementById("articleStep");

  if (publicationStep) {
    publicationStep.style.display = "block";
  }

  if (articleStep) {
    articleStep.style.display = "none";
  }

  renderArticleTabs();
  loadArticle(0);
}

/* =========================================================
   LOAD EXISTING DRAFT
   ========================================================= */

async function loadDraftPublication(publicationID) {
  try {
    const response = await fetch(
      `manage_journal_api.php?action=view&publicationID=${encodeURIComponent(publicationID)}`,
    );

    const result = await response.json();

    if (!result.success || !result.data) {
      console.error("Unable to load draft:", result.message);
      showMessage(
        "Unable to Load Draft",
        result.message || "The draft could not be loaded.",
      );
      return;
    }

    const issue = result.data;

    /*
     * Make sure the selected publication is actually
     * a draft.
     */
    if (!issue.is_draft) {
      showMessage(
        "Invalid Draft",
        "This publication is not a draft and cannot be continued.",
      );
      return;
    }

    /*
     * Restore publication information.
     */
    const year = document.getElementById("year");
    const volume = document.getElementById("volume");
    const number = document.getElementById("number");

    if (year) {
      year.value = issue.year ?? "";
    }

    if (volume) {
      volume.value = issue.volume ?? "";
    }

    if (number) {
      number.value = issue.number ?? "";
    }

    /*
     * Restore summary.
     */
    const displayYear = document.getElementById("displayYear");
    const displayVolume = document.getElementById("displayVolume");
    const displayNumber = document.getElementById("displayNumber");

    if (displayYear) {
      displayYear.textContent = issue.year ?? "";
    }

    if (displayVolume) {
      displayVolume.textContent = issue.volume ?? "";
    }

    if (displayNumber) {
      displayNumber.textContent = issue.number ?? "";
    }

    /*
     * Restore existing publication PDF.
     */
    existingPublicationPdf = issue.publicationPDF || null;

    if (existingPublicationPdf) {
      const fileName = getFileNameFromPath(existingPublicationPdf);

      const publicationPdfFileName = document.getElementById(
        "publicationPdfFileName",
      );

      if (publicationPdfFileName) {
        publicationPdfFileName.textContent = fileName;
      }

      const uploadTitle = document.querySelector(
        "#publicationPdfUploadBox .upload-title",
      );

      if (uploadTitle) {
        uploadTitle.style.display = "none";
      }

      const undoButton = document.getElementById("publicationPdfUndo");

      if (undoButton) {
        undoButton.hidden = false;
      }
    }

    /*
     * Restore articles.
     */
    if (Array.isArray(issue.articles) && issue.articles.length > 0) {
      articles = issue.articles.map((article) => {
        return {
          journalID: article.journalID ?? null,

          title: article.title ?? "",

          pdf: null,

          existingPdf: article.journalPDF || null,

          authors:
            Array.isArray(article.authors) && article.authors.length > 0
              ? article.authors.map((author) => ({
                  firstName: author.firstName ?? "",

                  lastName: author.lastName ?? "",
                }))
              : [
                  {
                    firstName: "",
                    lastName: "",
                  },
                ],
        };
      });
    } else {
      /*
       * If the draft currently has no articles,
       * provide one empty article slot in the UI.
       */
      articles = [createArticle()];
    }

    currentArticleIndex = 0;

    /*
     * Show article step when continuing a draft.
     */
    const publicationStep = document.getElementById("publicationStep");
    const articleStep = document.getElementById("articleStep");

    if (publicationStep) {
      publicationStep.style.display = "none";
    }

    if (articleStep) {
      articleStep.style.display = "block";
    }

    /*
     * Change page heading.
     */
    const pageTitle = document.getElementById("pageTitle");
    const pageDescription = document.getElementById("pageDescription");

    if (pageTitle) {
      pageTitle.textContent = "Continue Editing Draft";
    }

    if (pageDescription) {
      pageDescription.textContent =
        "Continue editing the saved journal issue and its articles";
    }

    renderArticleTabs();
    loadArticle(0);
  } catch (error) {
    console.error("Error loading draft:", error);

    showMessage(
      "Unable to Load Draft",
      "An error occurred while loading the draft.",
    );
  }
}

/* =========================================================
   PUBLICATION PDF UPLOAD
   ========================================================= */

function setupPublicationPdfUpload() {
  const fileInput = document.getElementById("publicationPDF");
  const uploadBox = document.getElementById("publicationPdfUploadBox");
  const undoButton = document.getElementById("publicationPdfUndo");

  if (!fileInput || !uploadBox) {
    return;
  }

  /*
   * Clicking the upload box opens file selection.
   */
  uploadBox.addEventListener("click", function (event) {
    if (event.target.closest("#publicationPdfUndo")) {
      return;
    }

    fileInput.click();
  });

  /*
   * File selected normally.
   */
  fileInput.addEventListener("change", function () {
    handlePublicationPdfFile(fileInput.files[0]);
  });

  /*
   * Drag over.
   */
  uploadBox.addEventListener("dragover", function (event) {
    event.preventDefault();

    uploadBox.classList.add("drag-over");
  });

  /*
   * Drag leave.
   */
  uploadBox.addEventListener("dragleave", function () {
    uploadBox.classList.remove("drag-over");
  });

  /*
   * Drop.
   */
  uploadBox.addEventListener("drop", function (event) {
    event.preventDefault();
    uploadBox.classList.remove("drag-over");
    const file = event.dataTransfer.files[0];
    handlePublicationPdfFile(file);
  });

  /*
   * Undo selected publication PDF.
   */
  if (undoButton) {
    undoButton.addEventListener("click", function (event) {
      event.stopPropagation();
      undoPublicationPdf();
    });
  }
}

/*
 * Validate and store publication PDF.
 */
/*function handlePublicationPdfFile(file) {


  if (file.type !== "application/pdf" &&!file.name.toLowerCase().endsWith(".pdf")) {
    const fileInput = document.getElementById("publicationPDF");

    if (fileInput) {
      fileInput.value = "";
    }

    showMessage("Invalid File", "Please select a PDF file.");
    return;
  }

  
  const maxSize = 40 * 1024 * 1024;

  
  if (file.size > maxSize) {
    const fileInput = document.getElementById("pdfFile");

    if (fileInput) {
      fileInput.value = "";
    }

    showMessage("File Too Large", "The article PDF must not exceed 40 MB.");
    return;
  }

  publicationPdfFile = file;

  
  existingPublicationPdf = null;
  const fileName = document.getElementById("publicationPdfFileName");

  if (fileName) {
    fileName.textContent = file.name;
  }

  const uploadTitle = document.querySelector(
    "#publicationPdfUploadBox .upload-title",
  );

  if (uploadTitle) {
    uploadTitle.style.display = "none";
  }

  const undoButton = document.getElementById("publicationPdfUndo");

  if (undoButton) {
    undoButton.hidden = false;
  }
}*/

//NEW HANDLE PUBLICATION PDF FILE - ADDED: SEPT 30
function handlePublicationPdfFile(file) {
  if (!file) {
    return;
  }

  if (
    file.type !== "application/pdf" &&
    !file.name.toLowerCase().endsWith(".pdf")
  ) {
    const fileInput = document.getElementById("publicationPDF");

    if (fileInput) {
      fileInput.value = "";
    }

    showMessage("Invalid File", "Please select a PDF file.");
    return;
  }

  const maxSize = 40 * 1024 * 1024;

  if (file.size > maxSize) {
    const fileInput = document.getElementById("publicationPDF");

    if (fileInput) {
      fileInput.value = "";
    }

    showMessage("File Too Large", "The publication PDF must not exceed 40 MB.");
    return;
  }

  publicationPdfFile = file;

  existingPublicationPdf = null;

  const fileName = document.getElementById("publicationPdfFileName");

  if (fileName) {
    fileName.textContent = file.name;
  }

  const uploadTitle = document.querySelector(
    "#publicationPdfUploadBox .upload-title",
  );

  if (uploadTitle) {
    uploadTitle.style.display = "none";
  }

  const undoButton = document.getElementById("publicationPdfUndo");

  if (undoButton) {
    undoButton.hidden = false;
  }
}

/*
 * Undo publication PDF selection.
 */
function undoPublicationPdf() {
  publicationPdfFile = null;
  const fileInput = document.getElementById("publicationPDF");

  if (fileInput) {
    fileInput.value = "";
  }

  const fileName = document.getElementById("publicationPdfFileName");

  /*
   * If an existing server PDF exists,
   * preserve it.
   */
  if (existingPublicationPdf) {
    if (fileName) {
      fileName.textContent = getFileNameFromPath(existingPublicationPdf);
    }
  } else {
    if (fileName) {
      fileName.textContent = "No file selected";
    }
  }

  const uploadTitle = document.querySelector(
    "#publicationPdfUploadBox .upload-title",
  );

  if (uploadTitle) {
    uploadTitle.style.display = existingPublicationPdf ? "none" : "";
  }

  const undoButton = document.getElementById("publicationPdfUndo");

  if (undoButton) {
    undoButton.hidden = !existingPublicationPdf;
  }
}

function setupArticlePdfUpload() {
  const fileInput = document.getElementById("pdfFile");
  const uploadBox = document.getElementById("articlePdfUploadBox");
  const undoButton = document.getElementById("articlePdfUndo");

  if (!fileInput || !uploadBox) {
    return;
  }

  uploadBox.addEventListener("click", function (event) {
    if (event.target.closest("#articlePdfUndo")) {
      return;
    }

    fileInput.click();
  });

  fileInput.addEventListener("change", function () {
    handleArticlePdfFile(fileInput.files[0]);
  });

  uploadBox.addEventListener("dragover", function (event) {
    event.preventDefault();

    uploadBox.classList.add("drag-over");
  });

  uploadBox.addEventListener("dragleave", function () {
    uploadBox.classList.remove("drag-over");
  });

  uploadBox.addEventListener("drop", function (event) {
    event.preventDefault();
    uploadBox.classList.remove("drag-over");
    const file = event.dataTransfer.files[0];
    handleArticlePdfFile(file);
  });

  if (undoButton) {
    undoButton.addEventListener("click", function (event) {
      event.stopPropagation();

      undoPdfFile();
    });
  }
}

/*
 * Validate and store article PDF.
 */
/*function handleArticlePdfFile(file) {
  if (!file) {
    return;
  }

  if (
    file.type !== "application/pdf" &&
    !file.name.toLowerCase().endsWith(".pdf")
  ) {
    showMessage("Invalid File", "Please select a PDF file.");
    return;
  }

  const maxSize = 40 * 1024 * 1024;

  if (file.size > maxSize) {
    showMessage("File Too Large", "The article PDF must not exceed 40 MB.");
    return;
  }

  const article = articles[currentArticleIndex];

  if (!article) {
    return;
  }

  article.pdf = file;
  article.existingPdf = null;

  const fileName = document.getElementById("pdfFileName");

  if (fileName) {
    fileName.textContent = file.name;
  }

  const uploadTitle = document.querySelector(
    "#articlePdfUploadBox .upload-title",
  );

  if (uploadTitle) {
    uploadTitle.style.display = "none";
  }

  const undoButton = document.getElementById("articlePdfUndo");

  if (undoButton) {
    undoButton.hidden = false;
  }
}*/

//NEW HANDLEARTICLEPDFFILE:
function handleArticlePdfFile(file) {
  if (!file) {
    return;
  }

  if (
    file.type !== "application/pdf" &&
    !file.name.toLowerCase().endsWith(".pdf")
  ) {
    const fileInput = document.getElementById("pdfFile");

    if (fileInput) {
      fileInput.value = "";
    }

    showMessage("Invalid File", "Please select a PDF file.");
    return;
  }

  const maxSize = 40 * 1024 * 1024;

  if (file.size > maxSize) {
    const fileInput = document.getElementById("pdfFile");

    if (fileInput) {
      fileInput.value = "";
    }

    showMessage("File Too Large", "The article PDF must not exceed 40 MB.");
    return;
  }

  const article = articles[currentArticleIndex];

  if (!article) {
    return;
  }

  article.pdf = file;
  article.existingPdf = null;

  const fileName = document.getElementById("pdfFileName");

  if (fileName) {
    fileName.textContent = file.name;
  }

  const uploadTitle = document.querySelector(
    "#articlePdfUploadBox .upload-title",
  );

  if (uploadTitle) {
    uploadTitle.style.display = "none";
  }

  const undoButton = document.getElementById("articlePdfUndo");

  if (undoButton) {
    undoButton.hidden = false;
  }
}

/*
 * Undo article PDF selection.
 */
function undoPdfFile() {
  const article = articles[currentArticleIndex];

  if (!article) {
    return;
  }

  article.pdf = null;

  const fileInput = document.getElementById("pdfFile");

  if (fileInput) {
    fileInput.value = "";
  }

  const fileName = document.getElementById("pdfFileName");

  if (article.existingPdf) {
    if (fileName) {
      fileName.textContent = getFileNameFromPath(article.existingPdf);
    }
  } else {
    if (fileName) {
      fileName.textContent = "No file selected";
    }
  }

  const uploadTitle = document.querySelector(
    "#articlePdfUploadBox .upload-title",
  );

  if (uploadTitle) {
    uploadTitle.style.display = article.existingPdf ? "none" : "";
  }

  const undoButton = document.getElementById("articlePdfUndo");

  if (undoButton) {
    undoButton.hidden = !article.existingPdf;
  }
}

/* =========================================================
   PUBLICATION STEP / ARTICLE STEP
   ========================================================= */

function goToArticles() {
  if (!validateIssueInformation()) {
    return;
  }

  /*
   * Save current article before switching.
   */
  saveCurrentArticle();

  const publicationStep = document.getElementById("publicationStep");

  const articleStep = document.getElementById("articleStep");

  if (publicationStep) {
    publicationStep.style.display = "none";
  }

  if (articleStep) {
    articleStep.style.display = "block";
  }

  /*
   * Update summary.
   */
  const year = document.getElementById("year");

  const volume = document.getElementById("volume");

  const number = document.getElementById("number");

  const displayYear = document.getElementById("displayYear");

  const displayVolume = document.getElementById("displayVolume");

  const displayNumber = document.getElementById("displayNumber");

  if (displayYear) {
    displayYear.textContent = year ? year.value : "";
  }

  if (displayVolume) {
    displayVolume.textContent = volume ? volume.value : "";
  }

  if (displayNumber) {
    displayNumber.textContent = number ? number.value : "";
  }

  renderArticleTabs();
  loadArticle(currentArticleIndex);
}

/*
 * Go back to publication information.
 */
function goToPublication() {
  saveCurrentArticle();

  const publicationStep = document.getElementById("publicationStep");

  const articleStep = document.getElementById("articleStep");

  if (publicationStep) {
    publicationStep.style.display = "block";
  }

  if (articleStep) {
    articleStep.style.display = "none";
  }

  //setupPublicationPdfUpload();
}

/* =========================================================
   ARTICLE TABS
   ========================================================= */
function renderArticleTabs() {
  const container = document.getElementById("articleTabs");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  articles.forEach(function (article, index) {
    const button = document.createElement("button");

    button.type = "button";

    button.className = "article-tab";

    if (index === currentArticleIndex) {
      button.classList.add("active");
    }

    button.textContent = `Article ${index + 1}`;

    button.addEventListener("click", function () {
      saveCurrentArticle();

      loadArticle(index);
    });

    container.appendChild(button);
  });
}

/* =========================================================
   LOAD ARTICLE
   ========================================================= */
function loadArticle(index) {
  if (!articles[index]) {
    return;
  }

  currentArticleIndex = index;

  const article = articles[currentArticleIndex];

  // Article heading
  const articleHeading = document.getElementById("articleHeading");
  if (articleHeading) {
    articleHeading.textContent = `Article ${currentArticleIndex + 1}`;
  }

  // Article title
  const articleTitle = document.getElementById("articleTitle");
  if (articleTitle) {
    articleTitle.value = article.title || "";
  }

  // Delete article button
  const articleDeleteButton = document.getElementById("deleteArticleButton");
  if (articleDeleteButton) {
    articleDeleteButton.style.display = "inline-flex";
  }

  // Render authors
  renderAuthors();

  // The actual file input cannot be repopulated by JavaScript
  // for security reasons, so we clear it when switching articles.
  const fileInput = document.getElementById("pdfFile");
  if (fileInput) {
    fileInput.value = "";
  }

  // Display the PDF state
  const fileName = document.getElementById("pdfFileName");
  const uploadTitle = document.querySelector(
    "#articlePdfUploadBox .upload-title",
  );
  const undoButton = document.getElementById("articlePdfUndo");

  /*
   * Priority:
   *
   * 1. Newly selected PDF in memory (article.pdf)
   * 2. Existing PDF already saved on the server (article.existingPdf)
   * 3. No PDF
   */

  if (article.pdf instanceof File) {
    // Newly selected PDF that has not been saved yet
    if (fileName) {
      fileName.textContent = article.pdf.name;
    }

    if (uploadTitle) {
      uploadTitle.style.display = "none";
    }

    if (undoButton) {
      undoButton.hidden = false;
    }
  } else if (article.existingPdf) {
    // Existing PDF from a saved draft
    if (fileName) {
      fileName.textContent = getFileNameFromPath(article.existingPdf);
    }

    if (uploadTitle) {
      uploadTitle.style.display = "none";
    }

    if (undoButton) {
      undoButton.hidden = false;
    }
  } else {
    // No PDF
    if (fileName) {
      fileName.textContent = "No file selected";
    }

    if (uploadTitle) {
      uploadTitle.style.display = "";
    }

    if (undoButton) {
      undoButton.hidden = true;
    }
  }

  renderArticleTabs();
}

/* =========================================================
   SAVE CURRENT ARTICLE
   ========================================================= */

function saveCurrentArticle() {
  const article = articles[currentArticleIndex];

  if (!article) {
    return;
  }

  /*
   * Save title.
   */
  const titleInput = document.getElementById("articleTitle");

  if (titleInput) {
    article.title = titleInput.value.trim();
  }

  /*
   * Save newly selected PDF.
   */
  const fileInput = document.getElementById("pdfFile");

  if (fileInput && fileInput.files && fileInput.files.length > 0) {
    article.pdf = fileInput.files[0];

    article.existingPdf = null;
  }

  /*
   * Save authors.
   */
  const authorRows = document.querySelectorAll("#authors .author-row");

  if (authorRows.length > 0) {
    article.authors = [];

    authorRows.forEach(function (row) {
      const firstNameInput = row.querySelector(".author-first-name");

      const lastNameInput = row.querySelector(".author-last-name");

      article.authors.push({
        firstName: firstNameInput ? firstNameInput.value.trim() : "",

        lastName: lastNameInput ? lastNameInput.value.trim() : "",
      });
    });
  } else {
    article.authors = [
      {
        firstName: "",
        lastName: "",
      },
    ];
  }
}

/* =========================================================
   ADD ARTICLE
   ========================================================= */

function addArticle() {
  saveCurrentArticle();

  articles.push(createArticle());

  currentArticleIndex = articles.length - 1;

  renderArticleTabs();

  loadArticle(currentArticleIndex);
}

/* =========================================================
   REMOVE ARTICLE
   ========================================================= */

function requestDeleteArticle() {
  /*
   * Do not allow the last article to be removed.
   * Show the custom message modal instead of alert().
   */
  if (articles.length <= 1) {
    showMessage("Cannot Remove Article", "At least one article is required.");

    return;
  }

  saveCurrentArticle();

  articleDeleteIndex = currentArticleIndex;

  const modal = document.getElementById("confirmationModal");

  const title = document.getElementById("confirmationTitle");

  const message = document.getElementById("confirmationMessage");

  const confirmButton = document.getElementById("confirmDeleteButton");

  if (!modal || !title || !message || !confirmButton) {
    console.error("Confirmation modal elements not found.");

    return;
  }

  title.textContent = "Remove Article?";

  message.textContent =
    "Are you sure you want to remove this article? This cannot be undone.";

  confirmButton.textContent = "Remove Article";

  confirmButton.onclick = function () {
    deleteArticle();
  };

  modal.classList.add("show");
}

/*
 * Delete the selected article from the local form.
 */
function deleteArticle() {
  if (articleDeleteIndex === null || !articles[articleDeleteIndex]) {
    closeConfirmationModal();

    return;
  }

  articles.splice(articleDeleteIndex, 1);

  /*
   * Adjust current article index.
   */
  if (currentArticleIndex >= articles.length) {
    currentArticleIndex = articles.length - 1;
  }

  if (currentArticleIndex < 0) {
    currentArticleIndex = 0;
  }

  articleDeleteIndex = null;

  closeConfirmationModal();

  renderArticleTabs();

  loadArticle(currentArticleIndex);
}

/* =========================================================
   AUTHORS
   ========================================================= */

function renderAuthors() {
  const container = document.getElementById("authors");

  if (!container) {
    return;
  }

  const article = articles[currentArticleIndex];

  if (!article) {
    return;
  }

  container.innerHTML = "";

  /*
   * Make sure at least one author row exists.
   */
  if (!Array.isArray(article.authors) || article.authors.length === 0) {
    article.authors = [
      {
        firstName: "",
        lastName: "",
      },
    ];
  }

  article.authors.forEach(function (author, index) {
    const row = document.createElement("div");

    row.className = "author-row";

    row.innerHTML = `
                <div class="author-fields">

                    <div class="form-group">

                        <label>
                            First Name
                        </label>

                        <input
                            type="text"
                            class="author-first-name"
                            placeholder="First Name"
                            value="${escapeHtml(author.firstName || "")}"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Last Name
                        </label>

                        <input
                            type="text"
                            class="author-last-name"
                            placeholder="Last Name"
                            value="${escapeHtml(author.lastName || "")}"
                        >

                    </div>

                </div>


                <button
                    type="button"
                    class="action-btn delete-author-btn"
                    title="Remove author"
                    aria-label="Remove author"
                    onclick="requestDeleteAuthor(${index})"
                >
                    <i class="fa-solid fa-trash"></i>
                </button>
            `;

    const deleteButton = row.querySelector(".delete-author-btn");

    /*
     * Do not hide the button anymore.
     *
     * Clicking it when there is only one author
     * will show the custom message modal.
     */
    if (deleteButton) {
      deleteButton.style.display = "flex";
    }

    container.appendChild(row);
  });
}

/*
 * Add another author.
 */
function addAuthor() {
  saveCurrentArticle();
  const article = articles[currentArticleIndex];

  if (!article) {
    return;
  }

  if (!Array.isArray(article.authors)) {
    article.authors = [];
  }

  article.authors.push({
    firstName: "",
    lastName: "",
  });

  renderAuthors();
}

/* =========================================================
   REMOVE AUTHOR
   ========================================================= */
function requestDeleteAuthor(index) {
  const article = articles[currentArticleIndex];

  if (!article || !Array.isArray(article.authors)) {
    return;
  }

  /*
   * Do not allow the last author to be removed.
   * Use custom message modal instead of alert().
   */
  if (article.authors.length <= 1) {
    showMessage("Cannot Remove Author", "At least one author is required.");
    return;
  }

  authorDeleteIndex = index;
  const modal = document.getElementById("confirmationModal");
  const title = document.getElementById("confirmationTitle");
  const message = document.getElementById("confirmationMessage");
  const confirmButton = document.getElementById("confirmDeleteButton");

  if (!modal || !title || !message || !confirmButton) {
    console.error("Confirmation modal elements not found.");
    return;
  }

  title.textContent = "Remove Author?";

  message.textContent =
    "Are you sure you want to remove this author? This cannot be undone.";

  confirmButton.textContent = "Remove Author";

  confirmButton.onclick = function () {
    deleteAuthor();
  };

  modal.classList.add("show");
}

/*
 * Delete the selected author.
 */
function deleteAuthor() {
  const article = articles[currentArticleIndex];

  if (!article || !Array.isArray(article.authors)) {
    closeConfirmationModal();
    return;
  }

  /*
   * Never allow zero authors.
   */
  if (article.authors.length <= 1) {
    closeConfirmationModal();
    showMessage("Cannot Remove Author", "At least one author is required.");
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

  article.authors.splice(authorDeleteIndex, 1);
  authorDeleteIndex = null;
  closeConfirmationModal();
  renderAuthors();
}

function validateIssueInformation() {
  const year = document.getElementById("year");
  const volume = document.getElementById("volume");
  const number = document.getElementById("number");

  if (!year || !year.value) {
    showMessage("Incomplete Information", "Please select a publication year.");
    return false;
  }

  if (!volume || !volume.value || Number(volume.value) < 1) {
    showMessage(
      "Incomplete Information",
      "Please enter a valid volume number.",
    );
    return false;
  }

  if (!number || !number.value || Number(number.value) < 1) {
    showMessage("Incomplete Information", "Please enter a valid issue number.");
    return false;
  }

  return true;
}

function validatePublicationPdfForPublishing() {
  /*
   * A new PDF is valid.
   */
  if (publicationPdfFile) {
    return true;
  }

  /*
   * An existing PDF is also valid when editing
   * an existing draft.
   */
  if (existingPublicationPdf) {
    return true;
  }

  showMessage(
    "Publication PDF Required",
    "Please upload the publication issue PDF before publishing.",
  );
  return false;
}

/* =========================================================
   ARTICLE VALIDATION FOR PUBLISHING
   ========================================================= */
function validateArticlesForPublishing() {
  if (!Array.isArray(articles) || articles.length === 0) {
    showMessage(
      "Article Required",
      "At least one article is required before publishing.",
    );
    return false;
  }

  for (let i = 0; i < articles.length; i++) {
    const article = articles[i];

    /*
     * Article title is required.
     */
    if (!article.title || article.title.trim() === "") {
      showMessage(
        "Article Title Required",
        `Please enter a title for Article ${i + 1}.`,
      );
      return false;
    }

    /*
     * Article PDF is required.
     */
    if (!article.pdf && !article.existingPdf) {
      showMessage(
        "Article PDF Required",
        `Please upload a PDF for Article ${i + 1}.`,
      );
      return false;
    }

    /*
     * At least one complete author is required.
     */
    if (!Array.isArray(article.authors) || article.authors.length === 0) {
      showMessage(
        "Author Required",
        `Please add at least one author for Article ${i + 1}.`,
      );
      return false;
    }

    let hasCompleteAuthor = false;

    for (let j = 0; j < article.authors.length; j++) {
      const author = article.authors[j];
      const firstName = (author.firstName || "").trim();
      const lastName = (author.lastName || "").trim();
      /*
       * Completely blank author rows are ignored.
       */
      if (firstName === "" && lastName === "") {
        continue;
      }

      /*
       * Both first and last name are required
       * for a non-empty author row.
       */
      if (firstName === "" || lastName === "") {
        showMessage(
          "Incomplete Author",
          `Please complete the first name and last name for Author ${j + 1} of Article ${i + 1}.`,
        );
        return false;
      }

      hasCompleteAuthor = true;
    }

    if (!hasCompleteAuthor) {
      showMessage(
        "Author Required",
        `Please enter at least one complete author for Article ${i + 1}.`,
      );
      return false;
    }
  }

  return true;
}

function buildArticleData() {
  return articles.map(function (article) {
    return {
      journalID: article.journalID ?? null,

      title: article.title || "",

      authors: Array.isArray(article.authors)
        ? article.authors.map(function (author) {
            return {
              firstName: author.firstName || "",

              lastName: author.lastName || "",
            };
          })
        : [],

      existingPdf: article.existingPdf || null,
    };
  });
}

function savePublication(mode) {
  /*
   * Save draft immediately.
   *
   * Publish first validates everything and then
   * opens the publish confirmation modal.
   */
  if (mode === "publish") {
    saveCurrentArticle();

    if (!validateIssueInformation()) {
      return;
    }

    if (!validatePublicationPdfForPublishing()) {
      return;
    }

    if (!validateArticlesForPublishing()) {
      return;
    }

    pendingPublicationMode = "publish";
    const modal = document.getElementById("publishConfirmationModal");

    if (!modal) {
      console.error("Publish confirmation modal not found.");
      return;
    }

    modal.classList.add("show");
    return;
  }

  /*
   * Draft mode.
   *
   * Year, volume and number are still required.
   *
   * Article title, PDF and authors may remain
   * incomplete when saving as a draft.
   */
  submitPublication("draft");
}

async function submitPublication(mode) {
  saveCurrentArticle();

  /*
   * Year, volume and number are always required,
   * including drafts.
   */
  if (!validateIssueInformation()) {
    return;
  }

  /*
   * Publishing requires complete information.
   */
  if (mode === "publish") {
    if (!validatePublicationPdfForPublishing()) {
      return;
    }

    if (!validateArticlesForPublishing()) {
      return;
    }
  }

  const year = document.getElementById("year");
  const volume = document.getElementById("volume");
  const number = document.getElementById("number");

  if (!year || !volume || !number) {
    showMessage("Form Error", "Publication information could not be read.");
    return;
  }

  const formData = new FormData();

  /*
   * Existing draft = update.
   * New journal = add.
   */
  formData.append("action", editingPublicationID ? "update" : "add");
  formData.append("mode", mode === "publish" ? "publish" : "draft");

  /*
   * Existing draft ID.
   */
  if (editingPublicationID) {
    formData.append("publicationID", String(editingPublicationID));
  }

  /*
   * Publication information.
   */
  formData.append("year", year.value);
  formData.append("volume", volume.value);
  formData.append("number", number.value);

  /*
   * Only send a publication PDF if a new one
   * was selected.
   *
   * Existing server PDF is preserved by the backend.
   */
  if (publicationPdfFile) {
    formData.append("publicationPDF", publicationPdfFile);
  }

  /*
   * Article information.
   */
  const articleData = buildArticleData();
  formData.append("articles", JSON.stringify(articleData));

  /*
   * Attach newly selected article PDFs.
   *
   * The index matches the article array index.
   */
  articles.forEach(function (article, index) {
    if (article.pdf) {
      formData.append(`pdf_${index}`, article.pdf);
    }
  });

  try {
    const response = await fetch("manage_journal_api.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (!result.success) {
      showMessage(
        "Unable to Save",
        result.message || "The journal issue could not be saved.",
      );
      return;
    }

    /*
     * Successful save.
     */
    window.location.href = "manage_journal.php";
  } catch (error) {
    console.error("Error saving publication:", error);
    showMessage(
      "Save Error",
      "An error occurred while saving the journal issue. Please try again.",
    );
  }
}

document.addEventListener("DOMContentLoaded", function () {
  initializeAddJournalPage();
});

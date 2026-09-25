const API_URL = "manage_journal_api.php";

let articles = [];
let currentArticleIndex = -1;
let articleReplacementFiles = new Map();
let publicationReplacementFile = null;
let currentIssueMode = "draft";

document.addEventListener("DOMContentLoaded", () => {
  const editForm = document.getElementById("editForm");

  if (!editForm) {
    console.error("Edit form not found.");
    return;
  }

  const publicationID =
    editForm.dataset.publicationId ||
    document.getElementById("publicationID")?.value ||
    "";

  if (!publicationID || Number(publicationID) <= 0) {
    console.error("Invalid publication ID:", publicationID);
    return;
  }

  const articleSelect = document.getElementById("articleSelect");

  if (articleSelect) {
    articleSelect.addEventListener("change", handleArticleSelection);
  }

  setupPublicationPdfUpload();

  const articlePdfInput = document.getElementById("articlePDF");

  if (articlePdfInput) {
    articlePdfInput.addEventListener("change", () => {
      if (articlePdfInput.files && articlePdfInput.files.length > 0) {
        const file = articlePdfInput.files[0];

        if (!isPdf(file)) {
          alert("Please upload a PDF file only.");
          resetArticlePdfInput();
          return;
        }

        articleReplacementFiles.set(currentArticleIndex, file);
        updateArticlePdfFileName(file.name);
      } else {
        articleReplacementFiles.delete(currentArticleIndex);
        updateArticlePdfFileName("");
      }
    });
  }

  const articlePdfUndo = document.getElementById("articlePdfUndo");

  if (articlePdfUndo) {
    articlePdfUndo.addEventListener("click", undoArticlePdf);
  }

  const publicationPdfUndo = document.getElementById("publicationPdfUndo");

  if (publicationPdfUndo) {
    publicationPdfUndo.addEventListener("click", undoPublicationPdf);
  }

  // Show confirmation modal instead of saving immediately.
  editForm.addEventListener("submit", handleSaveConfirmation);

  loadIssue(Number(publicationID));
});

/* =========================================================
BASIC HELPERS
========================================================= */

function goBack() {
  window.location.href = "manage_journal.php";
}

function isPdf(file) {
  if (!file) {
    return false;
  }

  if (file.type && file.type.toLowerCase() === "application/pdf") {
    return true;
  }

  return file.name.toLowerCase().endsWith(".pdf");
}

/* =========================================================
SAVE CONFIRMATION
========================================================= */

function handleSaveConfirmation(event) {
  event.preventDefault();

  if (typeof openSaveConfirmModal === "function") {
    openSaveConfirmModal();
  } else {
    console.error("openSaveConfirmModal() is not available.");
  }
}

/* =========================================================
AUTHORS
========================================================= */

function createAuthorRow(firstName = "", lastName = "") {
  const row = document.createElement("div");
  row.className = "author-row";

  const firstNameInput = document.createElement("input");
  firstNameInput.type = "text";
  firstNameInput.className = "author-first-name";
  firstNameInput.placeholder = "First name";
  firstNameInput.value = firstName;
  firstNameInput.required = true;

  const lastNameInput = document.createElement("input");
  lastNameInput.type = "text";
  lastNameInput.className = "author-last-name";
  lastNameInput.placeholder = "Last name";
  lastNameInput.value = lastName;
  lastNameInput.required = true;

  const removeButton = document.createElement("button");
  removeButton.type = "button";
  removeButton.className = "remove-author-button";
  removeButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
  removeButton.title = "Remove author";
  removeButton.setAttribute("aria-label", "Remove author");

  removeButton.addEventListener("click", () => {
    row.remove();

    const parent = row.parentElement;

    if (parent && parent.querySelectorAll(".author-row").length === 0) {
      parent.appendChild(createAuthorRow());
    }
  });

  row.appendChild(firstNameInput);
  row.appendChild(lastNameInput);
  row.appendChild(removeButton);

  return row;
}

function addAuthor() {
  const authorsContainer = document.getElementById("authors");

  if (!authorsContainer) {
    return;
  }

  saveCurrentArticle();
  authorsContainer.appendChild(createAuthorRow());
}

function collectAuthors(container) {
  if (!container) {
    return [];
  }

  const rows = container.querySelectorAll(".author-row");
  const authors = [];

  rows.forEach((row) => {
    const firstNameInput = row.querySelector(".author-first-name");

    const lastNameInput = row.querySelector(".author-last-name");

    if (!firstNameInput || !lastNameInput) {
      return;
    }

    const firstName = firstNameInput.value.trim();
    const lastName = lastNameInput.value.trim();

    if (firstName !== "" && lastName !== "") {
      authors.push({
        firstName,
        lastName,
      });
    }
  });

  return authors;
}

function saveCurrentArticle() {
  if (currentArticleIndex < 0 || !articles[currentArticleIndex]) {
    return;
  }

  const article = articles[currentArticleIndex];

  const titleInput = document.getElementById("articleTitle");

  const authorsContainer = document.getElementById("authors");

  if (titleInput) {
    article.title = titleInput.value.trim();
  }

  if (authorsContainer) {
    article.authors = collectAuthors(authorsContainer);
  }
}

/* =========================================================
ARTICLE DROPDOWN
========================================================= */

function populateArticleDropdown() {
  const articleSelect = document.getElementById("articleSelect");

  if (!articleSelect) {
    console.error("Article dropdown (#articleSelect) was not found.");
    return;
  }

  articleSelect.innerHTML = "";

  if (articles.length === 0) {
    const option = document.createElement("option");

    option.value = "";
    option.textContent = "No articles available";

    articleSelect.appendChild(option);

    return;
  }

  articles.forEach((article, index) => {
    const option = document.createElement("option");

    option.value = String(index);

    const title = article.title || "Untitled Article";

    const maxTitleLength = 70;

    const shortenedTitle =
      title.length > maxTitleLength
        ? title.substring(0, maxTitleLength) + "..."
        : title;

    option.textContent = `Article ${index + 1}: ${shortenedTitle}`;

    articleSelect.appendChild(option);
  });

  console.log("Article dropdown populated:", articles.length, "articles");
}

function handleArticleSelection(event) {
  const newIndex = Number(event.target.value);

  if (Number.isNaN(newIndex) || newIndex < 0 || newIndex >= articles.length) {
    return;
  }

  saveCurrentArticle();

  currentArticleIndex = newIndex;

  displaySelectedArticle();
}

function displaySelectedArticle() {
  const editor = document.getElementById("selectedArticleEditor");

  const heading = document.getElementById("articleHeading");

  const titleInput = document.getElementById("articleTitle");

  const authorsContainer = document.getElementById("authors");

  if (!editor) {
    return;
  }

  if (currentArticleIndex < 0 || !articles[currentArticleIndex]) {
    editor.style.display = "none";
    return;
  }

  const article = articles[currentArticleIndex];

  editor.style.display = "block";

  if (heading) {
    heading.textContent = `Article ${currentArticleIndex + 1}`;
  }

  if (titleInput) {
    titleInput.value = article.title || "";
  }

  if (authorsContainer) {
    authorsContainer.innerHTML = "";

    if (Array.isArray(article.authors) && article.authors.length > 0) {
      article.authors.forEach((author) => {
        authorsContainer.appendChild(
          createAuthorRow(author.firstName || "", author.lastName || ""),
        );
      });
    } else {
      authorsContainer.appendChild(createAuthorRow());
    }
  }

  showArticlePdf(article.journalPDF || "");

  const replacementFile = articleReplacementFiles.get(currentArticleIndex);

  if (replacementFile) {
    updateArticlePdfFileName(replacementFile.name);
  } else {
    resetArticlePdfInput();
  }
}

/* =========================================================
ARTICLE PDF VIEWER
========================================================= */

function showArticlePdf(pdfPath) {
  const pdfContainer = document.getElementById("currentArticlePdf");

  if (!pdfContainer) {
    return;
  }

  pdfContainer.innerHTML = "";

  if (!pdfPath) {
    pdfContainer.textContent = "No PDF uploaded.";
    return;
  }

  const link = document.createElement("a");

  link.href = "#";
  link.className = "pdf-link";

  link.innerHTML = '<i class="fa-solid fa-eye"></i> View current PDF';

  link.addEventListener("click", async function (event) {
    event.preventDefault();

    const originalText = link.innerHTML;

    link.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Opening PDF...';

    link.style.pointerEvents = "none";

    try {
      const response = await fetch(
        `${API_URL}?action=pdfUrl&type=article&storagePath=${encodeURIComponent(pdfPath)}`,
      );

      const result = await response.json();

      if (!response.ok || !result.success || !result.data?.pdfUrl) {
        throw new Error(result.message || "Unable to open PDF.");
      }

      window.open(result.data.pdfUrl, "_blank");
    } catch (error) {
      console.error("Article PDF error:", error);

      alert(error.message || "Unable to open PDF.");
    } finally {
      link.innerHTML = originalText;
      link.style.pointerEvents = "";
    }
  });

  pdfContainer.appendChild(link);
}

/* =========================================================
ARTICLE PDF UPLOAD
========================================================= */

function setupArticlePdfDropZone() {
  const dropZone = document.getElementById("articlePdfUploadBox");

  const fileInput = document.getElementById("articlePDF");

  if (!dropZone || !fileInput) {
    return;
  }

  dropZone.addEventListener("dragover", (event) => {
    event.preventDefault();
    dropZone.classList.add("drag-over");
  });

  dropZone.addEventListener("dragleave", () => {
    dropZone.classList.remove("drag-over");
  });

  dropZone.addEventListener("drop", (event) => {
    event.preventDefault();

    dropZone.classList.remove("drag-over");

    const files = event.dataTransfer.files;

    if (!files || files.length === 0) {
      return;
    }

    const file = files[0];

    if (!isPdf(file)) {
      alert("Please upload a PDF file only.");
      return;
    }

    const dataTransfer = new DataTransfer();

    dataTransfer.items.add(file);

    fileInput.files = dataTransfer.files;

    articleReplacementFiles.set(currentArticleIndex, file);

    updateArticlePdfFileName(file.name);
  });
}

/* =========================================================
PUBLICATION PDF UPLOAD
========================================================= */

function setupPublicationPdfUpload() {
  const dropZone = document.getElementById("publicationPdfUploadBox");

  const fileInput = document.getElementById("publicationPDF");

  if (!dropZone || !fileInput) {
    return;
  }

  dropZone.addEventListener("dragover", (event) => {
    event.preventDefault();

    dropZone.classList.add("drag-over");
  });

  dropZone.addEventListener("dragleave", () => {
    dropZone.classList.remove("drag-over");
  });

  dropZone.addEventListener("drop", (event) => {
    event.preventDefault();

    dropZone.classList.remove("drag-over");

    const files = event.dataTransfer.files;

    if (!files || files.length === 0) {
      return;
    }

    const file = files[0];

    if (!isPdf(file)) {
      alert("Please upload a PDF file only.");
      return;
    }

    const dataTransfer = new DataTransfer();

    dataTransfer.items.add(file);

    fileInput.files = dataTransfer.files;

    publicationReplacementFile = file;

    updatePublicationPdfFileName(file.name);
  });

  fileInput.addEventListener("change", () => {
    if (fileInput.files && fileInput.files.length > 0) {
      const file = fileInput.files[0];

      if (!isPdf(file)) {
        alert("Please upload a PDF file only.");

        resetPublicationPdfInput();

        return;
      }

      publicationReplacementFile = file;

      updatePublicationPdfFileName(file.name);
    }
  });
}

/* =========================================================
FILE NAME / UNDO
========================================================= */

function updateArticlePdfFileName(fileName) {
  const fileNameElement = document.getElementById("articlePdfFileName");

  const undoButton = document.getElementById("articlePdfUndo");

  const uploadBox = document.getElementById("articlePdfUploadBox");

  if (fileNameElement) {
    fileNameElement.textContent = fileName || "No file selected";
  }

  if (undoButton) {
    undoButton.hidden = !fileName;
  }

  if (uploadBox) {
    uploadBox.classList.toggle("has-file", Boolean(fileName));
  }
}

function updatePublicationPdfFileName(fileName) {
  const fileNameElement = document.getElementById("publicationPdfFileName");

  const undoButton = document.getElementById("publicationPdfUndo");

  const uploadBox = document.getElementById("publicationPdfUploadBox");

  if (fileNameElement) {
    fileNameElement.textContent = fileName || "No file selected";
  }

  if (undoButton) {
    undoButton.hidden = !fileName;
  }

  if (uploadBox) {
    uploadBox.classList.toggle("has-file", Boolean(fileName));
  }
}

function resetArticlePdfInput() {
  const fileInput = document.getElementById("articlePDF");

  if (fileInput) {
    fileInput.value = "";
  }

  updateArticlePdfFileName("");
}

function undoArticlePdf() {
  articleReplacementFiles.delete(currentArticleIndex);

  resetArticlePdfInput();
}

function resetPublicationPdfInput() {
  const fileInput = document.getElementById("publicationPDF");

  if (fileInput) {
    fileInput.value = "";
  }

  updatePublicationPdfFileName("");
}

function undoPublicationPdf() {
  publicationReplacementFile = null;

  resetPublicationPdfInput();
}

/* =========================================================
LOAD ISSUE
========================================================= */

async function loadIssue(publicationID) {
  const loading = document.getElementById("loading");

  const errorMessage = document.getElementById("errorMessage");

  const editForm = document.getElementById("editForm");

  try {
    const response = await fetch(
      `${API_URL}?action=view&publicationID=${encodeURIComponent(publicationID)}`,
    );

    const responseText = await response.text();

    let result;

    try {
      result = JSON.parse(responseText);
    } catch (parseError) {
      console.error("Invalid API response:", responseText);

      throw new Error("The API did not return valid JSON.");
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load publication issue.");
    }

    const issue = result.data || {};

    if (!issue || !issue.publicationID) {
      throw new Error("The API returned incomplete issue data.");
    }

    const publicationIDInput = document.getElementById("publicationID");

    if (publicationIDInput) {
      publicationIDInput.value = issue.publicationID;
    }

    const yearInput = document.getElementById("year");

    const volumeInput = document.getElementById("volume");

    const numberInput = document.getElementById("number");

    if (yearInput) {
      yearInput.value = issue.year || "";
    }

    if (volumeInput) {
      volumeInput.value = issue.volume || "";
    }

    if (numberInput) {
      numberInput.value = issue.number || "";
    }

    // Remember whether the issue is a draft
    // or the current issue.
    currentIssueMode = issue.is_draft ? "draft" : "current";

    const publicationPdfInput = document.getElementById("publicationPDF");

    if (publicationPdfInput) {
      publicationPdfInput.value = "";
    }

    publicationReplacementFile = null;

    updatePublicationPdfFileName("");

    showPublicationPdf(issue.publicationPDF || "");

    articles = Array.isArray(issue.articles)
      ? issue.articles.map((article) => ({
          journalID: article.journalID || null,

          title: article.title || "",

          journalPDF: article.journalPDF || "",

          pdfUrl: article.pdfUrl || "",

          authors: Array.isArray(article.authors)
            ? article.authors.map((author) => ({
                firstName: author.firstName || "",

                lastName: author.lastName || "",
              }))
            : [],

          replacementFile: null,
        }))
      : [];

    console.log("Loaded articles:", articles);

    console.log("Article count:", articles.length);

    currentArticleIndex = -1;

    articleReplacementFiles.clear();

    populateArticleDropdown();

    const articleSelect = document.getElementById("articleSelect");

    if (articles.length > 0) {
      currentArticleIndex = 0;

      if (articleSelect) {
        articleSelect.value = "0";
      }

      displaySelectedArticle();
    } else {
      const editor = document.getElementById("selectedArticleEditor");

      if (editor) {
        editor.style.display = "none";
      }
    }

    setupArticlePdfDropZone();

    if (loading) {
      loading.style.display = "none";
    }

    if (editForm) {
      editForm.style.display = "block";
    }
  } catch (error) {
    console.error(error);

    if (loading) {
      loading.style.display = "none";
    }

    if (errorMessage) {
      errorMessage.textContent =
        error.message || "Unable to load publication issue.";

      errorMessage.style.display = "block";
    }
  }
}

/* =========================================================
PUBLICATION / EDITORIAL PDF VIEWER
========================================================= */

function showPublicationPdf(pdfPath) {
  const container = document.getElementById("currentPublicationPdf");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  if (!pdfPath) {
    const noFile = document.createElement("span");

    noFile.className = "no-file";

    noFile.textContent = "No editorial note PDF available.";

    container.appendChild(noFile);

    return;
  }

  const link = document.createElement("a");

  link.href = "#";

  link.className = "pdf-link";

  link.innerHTML = '<i class="fa-solid fa-eye"></i> View current PDF';

  link.addEventListener("click", async (event) => {
    event.preventDefault();

    const originalText = link.innerHTML;

    link.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Opening PDF...';

    link.style.pointerEvents = "none";

    try {
      const response = await fetch(
        `${API_URL}?action=pdfUrl&type=publication&storagePath=${encodeURIComponent(pdfPath)}`,
      );

      const result = await response.json();

      if (!response.ok || !result.success || !result.data?.pdfUrl) {
        throw new Error(result.message || "Unable to generate PDF URL.");
      }

      window.open(result.data.pdfUrl, "_blank");
    } catch (error) {
      console.error("Editorial PDF error:", error);

      alert(error.message || "Unable to open the PDF.");
    } finally {
      link.innerHTML = originalText;

      link.style.pointerEvents = "";
    }
  });

  container.appendChild(link);
}

/* =========================================================
SAVE CHANGES
========================================================= */

async function saveChanges() {
  saveCurrentArticle();

  const editForm = document.getElementById("editForm");

  if (!editForm) {
    console.error("Edit form not found.");

    return;
  }

  const publicationIDInput = document.getElementById("publicationID");

  const yearInput = document.getElementById("year");

  const volumeInput = document.getElementById("volume");

  const numberInput = document.getElementById("number");

  const publicationID = Number(publicationIDInput?.value || 0);

  const year = Number(yearInput?.value || 0);

  const volume = Number(volumeInput?.value || 0);

  const number = Number(numberInput?.value || 0);

  if (!publicationID || publicationID <= 0) {
    alert("Invalid publication ID.");

    return;
  }

  if (!year || year <= 0 || !volume || volume <= 0 || !number || number <= 0) {
    alert("Please enter valid publication issue information.");

    return;
  }

  if (articles.length === 0) {
    alert("At least one article is required.");

    return;
  }

  // Validate every article before
  // sending anything to the server.
  for (let index = 0; index < articles.length; index++) {
    const article = articles[index];

    const articleNumber = index + 1;

    const title = (article.title || "").trim();

    if (!title) {
      alert(`Title for Article ${articleNumber} is required.`);

      return;
    }

    if (!Array.isArray(article.authors) || article.authors.length === 0) {
      alert(`Article ${articleNumber} must have at least one author.`);

      return;
    }

    for (const author of article.authors) {
      const firstName = (author.firstName || "").trim();

      const lastName = (author.lastName || "").trim();

      if (!firstName || !lastName) {
        alert(
          `Both first name and last name are required for every author in Article ${articleNumber}.`,
        );

        return;
      }
    }

    // Existing article may keep its
    // existing PDF.
    //
    // New article must have a PDF.
    if (!article.journalID && !articleReplacementFiles.has(index)) {
      alert(`PDF for Article ${articleNumber} is required.`);

      return;
    }
  }

  // Prevent duplicate submissions.
  const submitButton = editForm.querySelector('button[type="submit"]');

  let originalButtonText = "";

  if (submitButton) {
    originalButtonText = submitButton.innerHTML;

    submitButton.disabled = true;

    submitButton.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
  }

  try {
    const formData = new FormData();

    formData.append("action", "update");

    formData.append("publicationID", String(publicationID));

    formData.append("year", String(year));

    formData.append("volume", String(volume));

    formData.append("number", String(number));

    // Preserve whether the issue is
    // a draft or the current issue.
    formData.append("mode", currentIssueMode);

    const articleData = articles.map((article) => ({
      journalID: article.journalID ? Number(article.journalID) : null,

      title: (article.title || "").trim(),

      authors: Array.isArray(article.authors)
        ? article.authors.map((author) => ({
            firstName: (author.firstName || "").trim(),

            lastName: (author.lastName || "").trim(),
          }))
        : [],

      existingPdf: article.journalPDF || "",
    }));

    formData.append("articles", JSON.stringify(articleData));

    // Add replacement publication PDF
    // only if one was selected.
    if (publicationReplacementFile) {
      formData.append("publicationPDF", publicationReplacementFile);
    }

    articleReplacementFiles.forEach((file, index) => {
      if (file) {
        formData.append(`pdf_${index}`, file);
      }
    });

    console.log("Saving issue:", {
      publicationID,
      year,
      volume,
      number,
      mode: currentIssueMode,
      articles: articleData,
    });

    const response = await fetch(API_URL, {
      method: "POST",
      body: formData,
    });

    const responseText = await response.text();

    let result;

    try {
      result = JSON.parse(responseText);
    } catch (parseError) {
      console.error("Invalid update API response:", responseText);

      throw new Error("The server did not return valid JSON.");
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to save changes.");
    }

    console.log("Journal updated successfully.");

    // Return to Manage Journal
    // after a successful update.
    window.location.href = "manage_journal.php";
  } catch (error) {
    console.error("Save changes error:", error);

    if (typeof showErrorModal === "function") {
      showErrorModal(error.message || "Unable to save changes.");
    } else {
      alert(error.message || "Unable to save changes.");
    }
  } finally {
    if (submitButton) {
      submitButton.disabled = false;

      submitButton.innerHTML = originalButtonText;
    }
  }
}

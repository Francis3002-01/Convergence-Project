const API_URL = "manage_journal_api.php";

let articles = [];
let currentArticleIndex = -1;

let articleReplacementFiles = new Map();
let publicationReplacementFile = null;

/* ==========================================================================
   INITIALIZATION
   ========================================================================== */

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

  editForm.addEventListener("submit", saveChanges);

  loadIssue(Number(publicationID));
});

/* ==========================================================================
   BACK
   ========================================================================== */

function goBack() {
  window.location.href = "manage_journal.php";
}

/* ==========================================================================
   PDF VALIDATION
   ========================================================================== */

function isPdf(file) {
  if (!file) {
    return false;
  }

  if (file.type && file.type.toLowerCase() === "application/pdf") {
    return true;
  }

  return file.name.toLowerCase().endsWith(".pdf");
}

/* ==========================================================================
   AUTHOR ROW
   ========================================================================== */

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

/* ==========================================================================
   ADD AUTHOR
   ========================================================================== */

function addAuthor() {
  const authorsContainer = document.getElementById("authors");

  if (!authorsContainer) {
    return;
  }

  saveCurrentArticle();

  authorsContainer.appendChild(createAuthorRow());
}

/* ==========================================================================
   COLLECT AUTHORS
   ========================================================================== */

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

/* ==========================================================================
   SAVE CURRENT ARTICLE TO LOCAL STATE
   ========================================================================== */

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

/* ==========================================================================
   POPULATE ARTICLE DROPDOWN
   ========================================================================== */

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

    /*
     * Keep the native dropdown from becoming extremely wide
     * because of a very long article title.
     */
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

/* ==========================================================================
   HANDLE ARTICLE DROPDOWN SELECTION
   ========================================================================== */

function handleArticleSelection(event) {
  const newIndex = Number(event.target.value);

  if (Number.isNaN(newIndex) || newIndex < 0 || newIndex >= articles.length) {
    return;
  }

  saveCurrentArticle();

  currentArticleIndex = newIndex;

  displaySelectedArticle();
}

/* ==========================================================================
   DISPLAY SELECTED ARTICLE
   ========================================================================== */

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

  /* ----------------------------------------------------------------------
       ARTICLE HEADING
       ---------------------------------------------------------------------- */

  if (heading) {
    heading.textContent = `Article ${currentArticleIndex + 1}`;
  }

  /* ----------------------------------------------------------------------
       ARTICLE TITLE
       ---------------------------------------------------------------------- */

  if (titleInput) {
    titleInput.value = article.title || "";
  }

  /* ----------------------------------------------------------------------
       AUTHORS
       ---------------------------------------------------------------------- */

  if (authorsContainer) {
    authorsContainer.innerHTML = "";

    if (Array.isArray(article.authors) && article.authors.length > 0) {
      article.authors.forEach((author) => {
        authorsContainer.appendChild(createAuthorRow(author.firstName || "", author.lastName || ""),);
      });
    } 
    
    else {
      authorsContainer.appendChild(createAuthorRow());
    }
  }

  /* ----------------------------------------------------------------------
       CURRENT ARTICLE PDF
       ---------------------------------------------------------------------- */

  showArticlePdf(article.journalPDF || "");

  /* ----------------------------------------------------------------------
       REPLACEMENT PDF
       ---------------------------------------------------------------------- */

  const replacementFile = articleReplacementFiles.get(currentArticleIndex);

  if (replacementFile) {
    updateArticlePdfFileName(replacementFile.name);
  } else {
    resetArticlePdfInput();
  }
}

/* ==========================================================================
   SHOW CURRENT ARTICLE PDF
   ========================================================================== */

function showArticlePdf(pdfPath) {
  const container = document.getElementById("currentArticlePdf");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  if (!pdfPath) {
    const noFile = document.createElement("span");

    noFile.className = "no-file";

    noFile.textContent = "No article PDF available.";

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
        `${API_URL}?action=pdfUrl&type=article&storagePath=${encodeURIComponent(pdfPath)}`,
      );

      const result = await response.json();

      if (!response.ok || !result.success || !result.pdfUrl) {
        throw new Error(result.message || "Unable to generate PDF URL.");
      }

      window.open(result.pdfUrl, "_blank", "noopener,noreferrer");
    } catch (error) {
      console.error(error);

      alert(error.message || "Unable to open the PDF.");
    } finally {
      link.innerHTML = originalText;

      link.style.pointerEvents = "";
    }
  });

  container.appendChild(link);
}

/* ==========================================================================
   ARTICLE PDF DROP ZONE
   ========================================================================== */

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

/* ==========================================================================
   PUBLICATION PDF DROP ZONE
   ========================================================================== */

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

/* ==========================================================================
   ARTICLE PDF FILE NAME
   ========================================================================== */

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

/* ==========================================================================
   PUBLICATION PDF FILE NAME
   ========================================================================== */

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

/* ==========================================================================
   RESET ARTICLE PDF INPUT
   ========================================================================== */

function resetArticlePdfInput() {
  const fileInput = document.getElementById("articlePDF");

  if (fileInput) {
    fileInput.value = "";
  }

  updateArticlePdfFileName("");
}

/* ==========================================================================
   UNDO ARTICLE PDF
   ========================================================================== */

function undoArticlePdf() {
  articleReplacementFiles.delete(currentArticleIndex);

  resetArticlePdfInput();
}

/* ==========================================================================
   RESET PUBLICATION PDF INPUT
   ========================================================================== */

function resetPublicationPdfInput() {
  const fileInput = document.getElementById("publicationPDF");

  if (fileInput) {
    fileInput.value = "";
  }

  updatePublicationPdfFileName("");
}

/* ==========================================================================
   UNDO PUBLICATION PDF
   ========================================================================== */

function undoPublicationPdf() {
  publicationReplacementFile = null;

  resetPublicationPdfInput();
}

/* ==========================================================================
   LOAD ISSUE
   ========================================================================== */

async function loadIssue(publicationID) {
  const loading = document.getElementById("loading");

  const errorMessage = document.getElementById("errorMessage");

  const editForm = document.getElementById("editForm");

  try {
    const response = await fetch(`${API_URL}?action=view&publicationID=${encodeURIComponent(publicationID,)}`,);
    const responseText = await response.text();

    let result;

    try {
      result = JSON.parse(responseText);
    } 
    
    catch {
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
    } 
    
    else {
      const editor = document.getElementById("selectedArticleEditor");
      if (editor) {
        editor.style.display = "none";
      }
    }

    /* ------------------------------------------------------------------
           SETUP ARTICLE PDF DROP ZONE
           ------------------------------------------------------------------ */

    setupArticlePdfDropZone();

    /* ------------------------------------------------------------------
           SHOW FORM
           ------------------------------------------------------------------ */

    if (loading) {
      loading.style.display = "none";
    }

    if (editForm) {
      editForm.style.display = "block";
    }
  } 
  
  catch (error) {
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

      if (!response.ok || !result.success || !result.pdfUrl) {
        throw new Error(result.message || "Unable to generate PDF URL.");
      }

      window.open(result.pdfUrl, "_blank", "noopener,noreferrer");
    } 
    
    catch (error) {
      console.error(error);
      alert(error.message || "Unable to open the PDF.");
    } 
    
    finally {
      link.innerHTML = originalText;
      link.style.pointerEvents = "";
    }

  });

  container.appendChild(link);
}

async function saveChanges(event) {
  event.preventDefault();
  saveCurrentArticle();
  const saveButton = document.getElementById("saveButton");
  const form = document.getElementById("editForm");

  if (!form) {
    return;
  }

  const publicationID = document.getElementById("publicationID")?.value;

  if (!publicationID || Number(publicationID) <= 0) {
    alert("Invalid publication ID.");

    return;
  }

  /* ----------------------------------------------------------------------
       VALIDATE PUBLICATION INFORMATION
       ---------------------------------------------------------------------- */
  const year = document.getElementById("year")?.value.trim();
  const volume = document.getElementById("volume")?.value.trim();
  const number = document.getElementById("number")?.value.trim();

  if (!year || !volume || !number) {
    alert("Year, volume, and number are required.");
    return;
  }

  /* ----------------------------------------------------------------------
       VALIDATE ARTICLES
       ---------------------------------------------------------------------- */

  if (articles.length === 0) {
    alert("At least one article is required.");
    return;
  }

  const articlePayload = [];

  for (let index = 0; index < articles.length; index++) {
    const article = articles[index];

    const title = (article.title || "").trim();

    if (!title) {
      alert(`Title for Article ${index + 1} is required.`);
      return;
    }

    const authors = Array.isArray(article.authors)
      ? article.authors.filter((author) => author.firstName && author.lastName)
      : [];

    if (authors.length === 0) {
      alert(`At least one author is required for Article ${index + 1}.`);
      return;
    }

    articlePayload.push({
      journalID: article.journalID ? Number(article.journalID) : null,
      title,
      authors,
      existingPdf: article.journalPDF || "",
    });
  }

  /* ----------------------------------------------------------------------
       DISABLE SAVE BUTTON
       ---------------------------------------------------------------------- */

  if (saveButton) {
    saveButton.disabled = true;
    saveButton.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Confirming Changes...';
  }

  try {
    /* ------------------------------------------------------------------
           BUILD FORMDATA
           ------------------------------------------------------------------ */

    const formData = new FormData(form);
    formData.set("action", "update");
    formData.set("mode", "draft");
    formData.set("publicationID", String(publicationID));
    formData.set("year", String(year));
    formData.set("volume", String(volume));
    formData.set("number", String(number));
    formData.set("articles", JSON.stringify(articlePayload));

    /* ------------------------------------------------------------------
           PUBLICATION PDF
           ------------------------------------------------------------------ */

    if (publicationReplacementFile) {
      formData.set("publicationPDF", publicationReplacementFile);
    }

    /* ------------------------------------------------------------------
           ARTICLE REPLACEMENT PDFs
           ------------------------------------------------------------------ */

    articles.forEach((article, index) => {
      const file = articleReplacementFiles.get(index);

      if (file) {
        formData.append(`pdf_${index}`, file);
      }
    });

    /*UPDATE JOURNAL */
    const response = await fetch(API_URL, {
      method: "POST",
      body: formData,
    });

    const responseText = await response.text();
    console.log("Update API:", responseText);
    let result;

    try {
      result = JSON.parse(responseText);
    } 
    
    catch {
      throw new Error("The API did not return valid JSON.");
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Failed to update journal.");
    }

    /*SUCCESS */
    alert(result.message || "Journal updated successfully.");
    window.location.href = "manage_journal.php";
  } 
  
  catch (error) {
    console.error(error);
    alert(error.message || "An error occurred while saving.");

    if (saveButton) {
      saveButton.disabled = false;
      saveButton.innerHTML =
        '<i class="fa-solid fa-check"></i> Confirm Changes';
    }
  }
}
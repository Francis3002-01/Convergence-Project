const API_URL = "manage_journal_api.php";

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

  loadIssue(Number(publicationID));
  editForm.addEventListener("submit", saveChanges);
});

function goBack() {
  window.location.href = "manage_journal.php";
}

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

  removeButton.addEventListener("click", function () {
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

function collectAuthors(container) {
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
      authors.push({ firstName, lastName });
    }
  });

  return authors;
}

function setupPdfDropZone(dropZone, fileInput, fileNameElement) {
  if (!dropZone || !fileInput) return;

  const handleFiles = (files) => {
    if (!files || files.length === 0) return;
    const file = files[0];
    if (!file) return;

    if (file.type && file.type.toLowerCase() !== "application/pdf") {
      alert("Please upload a PDF file only.");
      return;
    }

    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    fileInput.files = dataTransfer.files;

    if (fileNameElement) {
      fileNameElement.textContent = file.name;
      dropZone.classList.add("has-file");
    }
  };

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
    handleFiles(event.dataTransfer.files);
  });

  fileInput.addEventListener("change", () => {
    if (fileInput.files && fileInput.files.length > 0) {
      const selectedFile = fileInput.files[0];
      if (fileNameElement) {
        fileNameElement.textContent = selectedFile.name;
        dropZone.classList.add("has-file");
      }
    } else if (fileNameElement) {
      fileNameElement.textContent = "No file selected";
      dropZone.classList.remove("has-file");
    }
  });
}

function renderArticleEditor(article, index) {
  const container = document.getElementById("articlesContainer");
  if (!container) return;

  const card = document.createElement("div");
  card.className = "article-card";
  card.dataset.journalId = article.journalID ?? "";
  card.dataset.existingPdf = article.journalPDF || "";

  const titleLabel = document.createElement("label");
  titleLabel.textContent = `Article ${index + 1} Title`;

  const titleInput = document.createElement("input");
  titleInput.type = "text";
  titleInput.className = "article-title-input";
  titleInput.value = article.title || "";
  titleInput.placeholder = "Enter article title";
  titleInput.required = true;

  const authorsWrapper = document.createElement("div");
  authorsWrapper.className = "authors-container";

  const authorLabel = document.createElement("div");
  authorLabel.className = "field-label-row";
  authorLabel.innerHTML = "<span>Authors</span>";

  const addAuthorButton = document.createElement("button");
  addAuthorButton.type = "button";
  addAuthorButton.className = "add-author-button";
  addAuthorButton.innerHTML = '<i class="fa-solid fa-plus"></i> Add Author';
  addAuthorButton.addEventListener("click", () => {
    authorsWrapper.appendChild(createAuthorRow());
  });
  authorLabel.appendChild(addAuthorButton);

  if (Array.isArray(article.authors) && article.authors.length > 0) {
    article.authors.forEach((author) => {
      authorsWrapper.appendChild(
        createAuthorRow(author.firstName || "", author.lastName || ""),
      );
    });
  } else {
    authorsWrapper.appendChild(createAuthorRow());
  }

  const currentPdf = document.createElement("div");
  currentPdf.className = "current-file";

  const fileIcon = document.createElement("div");
  fileIcon.className = "file-icon";
  fileIcon.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';

  const fileInfo = document.createElement("div");
  fileInfo.className = "file-information";

  const fileLabel = document.createElement("span");
  fileLabel.className = "file-label";
  fileLabel.textContent = "Current PDF";

  fileInfo.appendChild(fileLabel);

  const pdfUrl = article.pdfUrl || article.journalPDF || "";

  if (pdfUrl) {
    const pdfLink = document.createElement("a");
    pdfLink.href = pdfUrl;
    pdfLink.target = "_blank";
    pdfLink.rel = "noopener noreferrer";
    pdfLink.className = "pdf-link";
    pdfLink.innerHTML = '<i class="fa-solid fa-eye"></i> View current PDF';
    fileInfo.appendChild(pdfLink);
  } else {
    const noFile = document.createElement("span");
    noFile.className = "no-file";
    noFile.textContent = "No article PDF available.";
    fileInfo.appendChild(noFile);
  }

  currentPdf.appendChild(fileIcon);
  currentPdf.appendChild(fileInfo);

  const pdfInput = document.createElement("input");
  pdfInput.type = "file";
  pdfInput.name = `pdf_${index}`;
  pdfInput.accept = "application/pdf";

  const pdfBox = document.createElement("div");
  pdfBox.className = "pdf-upload-box article-pdf-upload-box";

  const pdfBoxIcon = document.createElement("div");
  pdfBoxIcon.className = "pdf-upload-icon";
  pdfBoxIcon.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';

  const pdfBoxText = document.createElement("div");
  pdfBoxText.className = "pdf-upload-text";

  const pdfBoxTitle = document.createElement("strong");
  pdfBoxTitle.textContent = "Drop a PDF here or choose file";

  const pdfFileName = document.createElement("span");
  pdfFileName.textContent = "No file selected";

  pdfBoxText.appendChild(pdfBoxTitle);
  pdfBoxText.appendChild(pdfFileName);

  const chooseButton = document.createElement("button");
  chooseButton.type = "button";
  chooseButton.className = "choose-pdf";
  chooseButton.innerHTML =
    '<i class="fa-solid fa-folder-open"></i> Choose File';
  chooseButton.addEventListener("click", () => pdfInput.click());

  pdfBox.appendChild(pdfBoxIcon);
  pdfBox.appendChild(pdfBoxText);
  pdfBox.appendChild(chooseButton);
  pdfBox.appendChild(pdfInput);

  setupPdfDropZone(pdfBox, pdfInput, pdfFileName);

  const pdfLabel = document.createElement("label");
  pdfLabel.textContent = "Replace Article PDF";

  card.appendChild(titleLabel);
  card.appendChild(titleInput);
  card.appendChild(authorLabel);
  card.appendChild(authorsWrapper);
  card.appendChild(pdfLabel);
  card.appendChild(pdfBox);
  card.appendChild(currentPdf);
  container.appendChild(card);
}

async function loadIssue(publicationID) {
  const loading = document.getElementById("loading");
  const errorMessage = document.getElementById("errorMessage");
  const editForm = document.getElementById("editForm");

  try {
    const response = await fetch(
      `${API_URL}?action=view&publicationID=${encodeURIComponent(publicationID)}`,
    );

    const responseText = await response.text();
    console.log("View API:", responseText);

    let result;

    try {
      result = JSON.parse(responseText);
    } catch {
      throw new Error("The API did not return valid JSON.");
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load publication issue.");
    }

    const issue = result.data || {};
    const articles = Array.isArray(issue.articles) ? issue.articles : [];

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

    if (yearInput) yearInput.value = issue.year || "";
    if (volumeInput) volumeInput.value = issue.volume || "";
    if (numberInput) numberInput.value = issue.number || "";

    const publicationPdfInput = document.getElementById("publicationPDF");
    if (publicationPdfInput) {
      publicationPdfInput.value = "";
    }

    const publicationUploadBox = document.getElementById(
      "publicationPdfUploadBox",
    );
    if (publicationUploadBox) {
      setupPdfDropZone(
        publicationUploadBox,
        publicationPdfInput,
        document.getElementById("publicationPdfFileName"),
      );
    }

    const articlesContainer = document.getElementById("articlesContainer");
    if (articlesContainer) {
      articlesContainer.innerHTML = "";

      if (articles.length === 0) {
        articlesContainer.innerHTML =
          '<div class="empty-message">No articles found in this issue.</div>';
      } else {
        articles.forEach((article, index) =>
          renderArticleEditor(article, index),
        );
      }
    }

    showPublicationPdf(
      issue.publicationPdfUrl || issue.publicationPDF || "",
      issue.publicationPdfUrl || "",
    );

    if (loading) loading.style.display = "none";
    if (editForm) editForm.style.display = "block";
  } catch (error) {
    console.error(error);
    if (loading) loading.style.display = "none";
    if (errorMessage) {
      errorMessage.textContent =
        error.message || "Unable to load publication issue.";
      errorMessage.style.display = "block";
    }
  }
}

function showArticlePdf(pdfUrl) {
  const container = document.getElementById("currentPdf");
  if (!container) return;

  container.innerHTML = "";

  if (!pdfUrl) {
    container.innerHTML =
      '<span class="no-file">No article PDF available.</span>';
    return;
  }

  const link = document.createElement("a");
  link.href = pdfUrl;
  link.target = "_blank";
  link.rel = "noopener noreferrer";
  link.className = "pdf-link";
  link.innerHTML = '<i class="fa-solid fa-eye"></i> View Current PDF';
  container.appendChild(link);
}

function showPublicationPdf(pdfPath, pdfUrl = "") {
  const container = document.getElementById("currentPublicationPdf");
  if (!container) return;

  container.innerHTML = "";

  if (!pdfPath) {
    container.innerHTML =
      '<span class="no-file">No publication PDF available.</span>';
    return;
  }

  const finalUrl = pdfUrl || pdfPath;
  const link = document.createElement("a");
  link.href = finalUrl;
  link.target = "_blank";
  link.rel = "noopener noreferrer";
  link.className = "pdf-link";
  link.innerHTML = '<i class="fa-solid fa-eye"></i> View current PDF';
  container.appendChild(link);
}

async function saveChanges(event) {
  event.preventDefault();
  const saveButton = document.getElementById("saveButton");
  const form = document.getElementById("editForm");

  if (!form) return;

  const publicationID = document.getElementById("publicationID")?.value;
  if (!publicationID || Number(publicationID) <= 0) {
    alert("Invalid publication ID.");
    return;
  }

  const cards = [...document.querySelectorAll(".article-card")];
  if (cards.length === 0) {
    alert("At least one article is required.");
    return;
  }

  const articlePayload = cards.map((card, index) => {
    const title =
      card.querySelector(".article-title-input")?.value.trim() || "";
    if (!title) {
      throw new Error(`Title for Article ${index + 1} is required.`);
    }

    const journalID = card.dataset.journalId
      ? Number(card.dataset.journalId)
      : null;
    const authors = collectAuthors(card.querySelector(".authors-container"));
    if (authors.length === 0) {
      throw new Error(
        `At least one author is required for Article ${index + 1}.`,
      );
    }

    return {
      journalID,
      title,
      authors,
      existingPdf: card.dataset.existingPdf || "",
    };
  });

  if (saveButton) {
    saveButton.disabled = true;
    saveButton.innerHTML =
      '<i class="fa-solid fa-spinner fa-spin"></i> Confirming Changes...';
  }

  try {
    const formData = new FormData(form);
    formData.set("action", "update");
    formData.set("mode", "draft");
    formData.set("publicationID", String(publicationID));
    formData.set("articles", JSON.stringify(articlePayload));

    cards.forEach((card, index) => {
      const fileInput = card.querySelector('input[type="file"]');
      if (fileInput && fileInput.files && fileInput.files[0]) {
        formData.append(`pdf_${index}`, fileInput.files[0]);
      }
    });

    const response = await fetch(API_URL, {
      method: "POST",
      body: formData,
    });

    const responseText = await response.text();
    console.log("Update API:", responseText);

    let result;
    try {
      result = JSON.parse(responseText);
    } catch {
      throw new Error("The API did not return valid JSON.");
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Failed to update journal.");
    }

    alert(result.message || "Journal updated successfully.");
    window.location.href = "manage_journal.php";
  } catch (error) {
    console.error(error);
    alert(error.message || "An error occurred while saving.");

    if (saveButton) {
      saveButton.disabled = false;
      saveButton.innerHTML =
        '<i class="fa-solid fa-check"></i> Confirm Changes';
    }
  }
}

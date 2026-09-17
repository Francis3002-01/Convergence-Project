let currentIssueData = [];
let draftIssueData = [];
let archiveIssueData = [];
let articles = [];
let currentArticleIndex = 0;
let articleDeleteIndex = null;
let authorDeleteIndex = null;
let publicationPdfFile = null;
let activeTab = "current";
let editingPublicationID = null;
let selectedDraftIssue = null;

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function createArticle() {
  return {
    journalID: null,
    title: "",
    pdf: null,
    authors: [{ firstName: "", lastName: "" }],
  };
}

function setAddJournalButtonState() {
  const button = document.getElementById("addJournalButton");
  if (!button) return;

  const hasDraft = draftIssueData.length > 0;
  button.disabled = hasDraft;
  button.title = hasDraft ? "A draft issue already exists." : "Add Journal";
}

function updateDraftBadge() {
  const badge = document.getElementById("draftCount");
  if (badge) {
    badge.textContent = String(draftIssueData.length);
  }
}

function showTab(tabName, buttonElement) {
  activeTab = tabName;

  const currentContent = document.getElementById("currentJournalContent");
  const draftContent = document.getElementById("draftJournalContent");
  const currentTabButton = document.getElementById("currentTabButton");
  const draftTabButton = document.getElementById("draftTabButton");

  if (currentContent && draftContent) {
    currentContent.style.display = tabName === "current" ? "block" : "none";
    draftContent.style.display = tabName === "draft" ? "block" : "none";
  }

  if (currentTabButton && draftTabButton) {
    currentTabButton.classList.toggle("active", tabName === "current");
    draftTabButton.classList.toggle("active", tabName === "draft");
  }

  if (buttonElement) {
    buttonElement.classList.add("active");
  }

  renderIssueLists();
}

function transformIssue(issue) {
  const cleaned = { ...issue };
  cleaned.articles = Array.isArray(cleaned.articles)
    ? cleaned.articles.map((article) => ({
        journalID: article.journalID ?? null,
        title: article.title ?? "",
        journalPDF: article.journalPDF ?? null,
        authors: Array.isArray(article.authors) ? article.authors : [],
      }))
    : [];
  return cleaned;
}

function renderIssueLists() {
  const currentList = document.getElementById("currentJournalList");
  const draftList = document.getElementById("draftJournalList");

  if (currentList) {
    renderIssueContainer(currentList, currentIssueData, "current");
  }

  if (draftList) {
    renderIssueContainer(draftList, draftIssueData, "draft");
  }

  updateDraftBadge();
  setAddJournalButtonState();
}

function renderIssueContainer(container, issueList, tabName) {
  if (!container) return;

  container.innerHTML = "";
  const cleanedList = (Array.isArray(issueList) ? issueList : []).map(
    transformIssue,
  );

  if (cleanedList.length === 0) {
    container.innerHTML = `<div class="empty-message">No ${tabName} issue found.</div>`;
    return;
  }

  cleanedList.forEach((issue) => {
    const issueSection = document.createElement("div");
    issueSection.className = "publication-issue";

    const articleHtml = (issue.articles || []).length
      ? (issue.articles || [])
          .map((article) => {
            const authors =
              (article.authors || [])
                .map((author) =>
                  `${escapeHtml(author.firstName || "")} ${escapeHtml(author.lastName || "")}`.trim(),
                )
                .filter(Boolean)
                .join(", ") || "Unknown author";

            return `
              <div class="article-item">
                <div class="article-info">
                  <div class="article-title">${escapeHtml(article.title || "Untitled Article")}</div>
                </div>
                <div class="article-authors">${escapeHtml(authors)}</div>
                <div class="article-item-actions">
                  <button type="button" class="action-btn view-btn" onclick="viewJournal(${Number(article.journalID)})" title="View article" aria-label="View article">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  <button type="button" class="action-btn delete-btn" onclick="deleteJournalArticle(${Number(article.journalID)})" title="Remove article" aria-label="Remove article">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </div>
            `;
          })
          .join("")
      : '<div class="empty-message">No articles in this issue.</div>';

    let actionsHtml = "";
    if (tabName === "draft") {
      actionsHtml = `
        <div class="issue-actions">
          <button type="button" class="action-btn edit-btn" onclick="editIssue(${Number(issue.publicationID)})" title="Continue editing" aria-label="Continue editing">
            <i class="fa-solid fa-pen"></i> Continue Editing
          </button>
          <button type="button" class="action-btn publish-btn" onclick="publishDraftIssue(${Number(issue.publicationID)})" title="Publish draft" aria-label="Publish draft">
            <i class="fa-solid fa-upload"></i> Publish
          </button>
        </div>
      `;
    } else {
      actionsHtml = `
        <div class="issue-actions">
          <button type="button" class="action-btn edit-btn" onclick="editIssue(${Number(issue.publicationID)})" title="Edit issue" aria-label="Edit issue">
            <i class="fa-solid fa-pen"></i>
          </button>
        </div>
      `;
    }

    issueSection.innerHTML = `
  <div class="issue-details">
    <div class="issue-information">
      <span>Publication Issue</span>
      <strong>${escapeHtml(issue.year || "-")}</strong>
      <span>Volume</span>
      <strong>${escapeHtml(issue.volume || "-")}</strong>
      <span>Number</span>
      <strong>${escapeHtml(issue.number || "-")}</strong>
    </div>

    ${actionsHtml}
  </div>

  <div class="issue-articles">
    ${articleHtml}
  </div>
`;

    container.appendChild(issueSection);
  });
}

async function loadJournals() {
  try {
    const response = await fetch("manage_journal_api.php?action=list");
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load issues.");
    }

    const payload = result.data || {};
    currentIssueData = Array.isArray(payload.current) ? payload.current : [];
    draftIssueData = Array.isArray(payload.draft) ? payload.draft : [];
    archiveIssueData = Array.isArray(payload.archive) ? payload.archive : [];

    renderIssueLists();
    if (draftIssueData.length > 0) {
      selectedDraftIssue = draftIssueData[0];
    }
  } catch (error) {
    console.error(error);
    const currentList = document.getElementById("currentJournalList");
    const draftList = document.getElementById("draftJournalList");

    if (currentList) {
      currentList.innerHTML =
        '<div class="empty-message">Unable to load current issue.</div>';
    }

    if (draftList) {
      draftList.innerHTML =
        '<div class="empty-message">Unable to load draft issue.</div>';
    }

    alert(error.message || "Unable to load issues.");
  }
}

function searchJournals() {
  const searchInput = document.getElementById("searchInput");
  const searchTerm = (searchInput ? searchInput.value : "")
    .trim()
    .toLowerCase();
  const issueSource = activeTab === "draft" ? draftIssueData : currentIssueData;

  const filtered = issueSource.filter((issue) => {
    const issueText = [
      issue.year,
      issue.volume,
      issue.number,
      issue.publicationPDF,
      ...(issue.articles || []).flatMap((article) => [
        article.title,
        ...(article.authors || []).flatMap((author) => [
          author.firstName,
          author.lastName,
        ]),
      ]),
    ]
      .join(" ")
      .toLowerCase();

    return issueText.includes(searchTerm);
  });

  const container =
    activeTab === "draft"
      ? document.getElementById("draftJournalList")
      : document.getElementById("currentJournalList");
  if (container) {
    renderIssueContainer(container, filtered, activeTab);
  }
}

function showForm() {
  const journalListContainer = document.getElementById("journalListContainer");
  const journalForm = document.getElementById("journalForm");
  const pageActions = document.querySelector(".page-actions");

  if (journalListContainer) journalListContainer.style.display = "none";
  if (journalForm) journalForm.style.display = "block";
  if (pageActions) pageActions.style.display = "none";

  const pageTitle = document.getElementById("pageTitle");
  if (pageTitle) {
    pageTitle.textContent = editingPublicationID
      ? "Edit Journal"
      : "Add Journal";
  }

  const publicationStep = document.getElementById("publicationStep");
  const articleStep = document.getElementById("articleStep");
  if (publicationStep) publicationStep.style.display = "block";
  if (articleStep) articleStep.style.display = "none";

  resetPublicationForm();
  if (editingPublicationID && selectedDraftIssue) {
    populateIssueForm(selectedDraftIssue);
  }
}

function hideForm() {
  const journalListContainer = document.getElementById("journalListContainer");
  const journalForm = document.getElementById("journalForm");
  const pageActions = document.querySelector(".page-actions");
  const pageTitle = document.getElementById("pageTitle");

  if (journalListContainer) journalListContainer.style.display = "block";
  if (journalForm) journalForm.style.display = "none";
  if (pageActions) pageActions.style.display = "flex";
  if (pageTitle) pageTitle.textContent = "Manage Journals";

  articles = [];
  currentArticleIndex = 0;
  publicationPdfFile = null;
  editingPublicationID = null;
  selectedDraftIssue = null;
  resetPublicationForm();
}

function populateIssueForm(issue) {
  const yearInput = document.getElementById("year");
  const volumeInput = document.getElementById("volume");
  const numberInput = document.getElementById("number");

  if (yearInput) yearInput.value = issue.year || "";
  if (volumeInput) volumeInput.value = issue.volume || "";
  if (numberInput) numberInput.value = issue.number || "";

  const fileNameNode = document.getElementById("publicationPdfFileName");
  if (fileNameNode) {
    fileNameNode.textContent = issue.publicationPDF
      ? "Existing publication PDF"
      : "No file selected";
  }

  articles =
    Array.isArray(issue.articles) && issue.articles.length
      ? issue.articles.map((article) => ({
          journalID: article.journalID ?? null,
          title: article.title ?? "",
          pdf: article.journalPDF
            ? {
                name: String(article.journalPDF).split("/").pop(),
                path: article.journalPDF,
              }
            : null,
          authors:
            Array.isArray(article.authors) && article.authors.length
              ? article.authors.map((author) => ({
                  firstName: author.firstName ?? "",
                  lastName: author.lastName ?? "",
                }))
              : [{ firstName: "", lastName: "" }],
        }))
      : [createArticle()];

  currentArticleIndex = 0;
  renderArticleTabs();
  loadArticle(0);
}

function resetPublicationForm() {
  const yearInput = document.getElementById("year");
  const volumeInput = document.getElementById("volume");
  const numberInput = document.getElementById("number");
  const currentYear = new Date().getFullYear();

  if (yearInput) {
    yearInput.innerHTML = "<option value=''>Select year</option>";
    for (let year = currentYear; year >= 2000; year -= 1) {
      const option = document.createElement("option");
      option.value = String(year);
      option.textContent = String(year);
      yearInput.appendChild(option);
    }
  }

  if (volumeInput) volumeInput.value = "";
  if (numberInput) numberInput.value = "";

  articles = [createArticle()];
  currentArticleIndex = 0;
  publicationPdfFile = null;
  resetPublicationPdfInput();
  setupPublicationPdfUpload();
  renderArticleTabs();
  loadArticle(0);
}

function goToArticles() {
  const year = document.getElementById("year")?.value;
  const volume = document.getElementById("volume")?.value;
  const number = document.getElementById("number")?.value;

  if (!year || !volume || !number) {
    alert("Please complete all publication issue fields.");
    return;
  }

  if (
    !publicationPdfFile &&
    !(
      editingPublicationID &&
      selectedDraftIssue &&
      selectedDraftIssue.publicationPDF
    )
  ) {
    alert("Please upload the publication issue PDF.");
    return;
  }

  const displayYear = document.getElementById("displayYear");
  const displayVolume = document.getElementById("displayVolume");
  const displayNumber = document.getElementById("displayNumber");
  if (displayYear) displayYear.textContent = year;
  if (displayVolume) displayVolume.textContent = volume;
  if (displayNumber) displayNumber.textContent = number;

  document.getElementById("publicationStep").style.display = "none";
  document.getElementById("articleStep").style.display = "block";

  currentArticleIndex = 0;
  renderArticleTabs();
  loadArticle(0);
}

function goToPublication() {
  saveCurrentArticle();
  document.getElementById("articleStep").style.display = "none";
  document.getElementById("publicationStep").style.display = "block";
  setupPublicationPdfUpload();
}

function renderArticleTabs() {
  const container = document.getElementById("articleTabs");
  if (!container) return;

  container.innerHTML = "";
  articles.forEach((article, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "article-tab";
    if (index === currentArticleIndex) button.classList.add("active");
    button.textContent = String(index + 1);
    button.onclick = function () {
      saveCurrentArticle();
      loadArticle(index);
    };
    container.appendChild(button);
  });
}

function loadArticle(index) {
  if (!Array.isArray(articles) || articles.length === 0) {
    articles = [createArticle()];
  }

  if (currentArticleIndex !== index && articles[currentArticleIndex]) {
    saveCurrentArticle();
  }

  currentArticleIndex = index;
  const article = articles[currentArticleIndex] || createArticle();

  const heading = document.getElementById("articleHeading");
  const titleInput = document.getElementById("articleTitle");
  const fileName = document.getElementById("pdfFileName");
  const deleteButton = document.getElementById("deleteArticleButton");

  if (heading) heading.textContent = `Article ${index + 1}`;
  if (titleInput) titleInput.value = article.title || "";

  if (fileName) {
    if (article.pdf && article.pdf.name) {
      fileName.textContent = article.pdf.name;
    } else if (article.pdf && typeof article.pdf === "string") {
      fileName.textContent = article.pdf.split("/").pop();
    } else {
      fileName.textContent = "PDF files only";
    }
  }

  renderAuthors();
  renderArticleTabs();

  if (deleteButton) {
    deleteButton.style.visibility = index === 0 ? "hidden" : "visible";
  }

  resetPdfInput();
  setupPdfUpload();
}

function saveCurrentArticle() {
  if (!articles[currentArticleIndex]) return;

  const article = articles[currentArticleIndex];
  const titleInput = document.getElementById("articleTitle");
  if (titleInput) article.title = titleInput.value.trim();

  const pdfInput = document.getElementById("pdfFile");
  if (pdfInput && pdfInput.files && pdfInput.files.length > 0) {
    article.pdf = pdfInput.files[0];
  }

  const authorRows = document.querySelectorAll(".author-row");
  if (authorRows.length > 0) {
    article.authors = [];
    authorRows.forEach((row) => {
      const firstNameInput = row.querySelector(".first-name");
      const lastNameInput = row.querySelector(".last-name");
      article.authors.push({
        firstName: firstNameInput ? firstNameInput.value.trim() : "",
        lastName: lastNameInput ? lastNameInput.value.trim() : "",
      });
    });
  }
}

function addArticle() {
  saveCurrentArticle();
  articles.push(createArticle());
  currentArticleIndex = articles.length - 1;
  renderArticleTabs();
  loadArticle(currentArticleIndex);
}

function requestDeleteArticle() {
  if (currentArticleIndex === 0) return;

  articleDeleteIndex = currentArticleIndex;
  const confirmationModal = document.getElementById("confirmationModal");
  const confirmationMessage = document.getElementById("confirmationMessage");
  if (confirmationMessage) {
    confirmationMessage.textContent = `Are you sure you want to remove Article ${currentArticleIndex + 1}?`;
  }

  const confirmDeleteButton = document.getElementById("confirmDeleteButton");
  if (confirmDeleteButton) {
    confirmDeleteButton.onclick = deleteArticle;
  }

  if (confirmationModal) {
    confirmationModal.classList.add("active");
  }
}

function deleteArticle() {
  saveCurrentArticle();
  if (articleDeleteIndex !== null && articleDeleteIndex < articles.length) {
    articles.splice(articleDeleteIndex, 1);
  }
  closeConfirmationModal();

  if (currentArticleIndex >= articles.length) {
    currentArticleIndex = Math.max(articles.length - 1, 0);
  }

  renderArticleTabs();
  loadArticle(currentArticleIndex);
}

function renderAuthors() {
  const container = document.getElementById("authors");
  if (!container) return;

  container.innerHTML = "";
  const currentAuthors = articles[currentArticleIndex]?.authors || [
    { firstName: "", lastName: "" },
  ];

  currentAuthors.forEach((author, index) => {
    const row = document.createElement("div");
    row.className = "author-row";
    row.innerHTML = `
      <div class="author-fields">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" class="first-name" value="${escapeHtml(author.firstName || "")}" placeholder="First name">
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" class="last-name" value="${escapeHtml(author.lastName || "")}" placeholder="Last name">
        </div>
      </div>
      ${index === 0 ? "" : `<button type="button" class="delete-author-btn" onclick="requestDeleteAuthor(${index})" title="Remove author" aria-label="Remove author"><i class="fa-solid fa-trash"></i></button>`}
    `;
    container.appendChild(row);
  });
}

function addAuthor() {
  saveCurrentArticle();
  articles[currentArticleIndex].authors.push({ firstName: "", lastName: "" });
  renderAuthors();
}

function requestDeleteAuthor(index) {
  saveCurrentArticle();
  const author = articles[currentArticleIndex].authors[index] || {
    firstName: "",
    lastName: "",
  };
  const fullName = `${author.firstName} ${author.lastName}`.trim();
  authorDeleteIndex = index;

  const confirmationModal = document.getElementById("confirmationModal");
  const confirmationMessage = document.getElementById("confirmationMessage");
  if (confirmationMessage) {
    confirmationMessage.textContent = fullName
      ? `Are you sure you want to remove ${fullName}?`
      : "Are you sure you want to remove this author?";
  }

  const confirmDeleteButton = document.getElementById("confirmDeleteButton");
  if (confirmDeleteButton) {
    confirmDeleteButton.onclick = deleteAuthor;
  }

  if (confirmationModal) {
    confirmationModal.classList.add("active");
  }
}

function deleteAuthor() {
  if (authorDeleteIndex !== null && articles[currentArticleIndex]) {
    articles[currentArticleIndex].authors.splice(authorDeleteIndex, 1);
  }
  closeConfirmationModal();
  renderAuthors();
}

function closeModal() {
  const confirmationModal = document.getElementById("confirmationModal");
  if (confirmationModal) {
    confirmationModal.classList.remove("active");
  }
  articleDeleteIndex = null;
  authorDeleteIndex = null;
}

function closeConfirmationModal() {
  closeModal();
}

function setupPublicationPdfUpload() {
  const uploadBox = document.getElementById("publicationPdfUploadBox");
  const fileInput = document.getElementById("publicationPDF");
  const undoButton = document.getElementById("publicationPdfUndo");

  if (!uploadBox || !fileInput) return;

  uploadBox.setAttribute("tabindex", "0");

  uploadBox.addEventListener("click", function (event) {
    if (event.target.closest(".undo-pdf-btn")) return;
    fileInput.click();
  });

  uploadBox.addEventListener("keydown", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      fileInput.click();
    }
  });

  uploadBox.ondragover = function (event) {
    event.preventDefault();
    uploadBox.classList.add("dragover");
  };
  uploadBox.ondragleave = function (event) {
    if (!uploadBox.contains(event.relatedTarget)) {
      uploadBox.classList.remove("dragover");
    }
  };
  uploadBox.ondrop = function (event) {
    event.preventDefault();
    uploadBox.classList.remove("dragover");
    const file = event.dataTransfer.files[0];
    if (file) handlePublicationPdfFile(file);
  };
  fileInput.onchange = function () {
    const file = fileInput.files[0];
    if (file) handlePublicationPdfFile(file);
  };

  if (undoButton) {
    undoButton.onclick = function () {
      resetPublicationPdfInput();
    };
  }
}

function handlePublicationPdfFile(file) {
  if (file.type !== "application/pdf") {
    alert("Please choose a PDF file.");
    resetPublicationPdfInput();
    return;
  }

  const maxFileSize = 40 * 1024 * 1024;
  if (file.size > maxFileSize) {
    alert("The publication issue PDF must not exceed 40 MB.");
    resetPublicationPdfInput();
    return;
  }

  publicationPdfFile = file;
  const fileName = document.getElementById("publicationPdfFileName");
  if (fileName) fileName.textContent = file.name;

  const undoButton = document.getElementById("publicationPdfUndo");
  if (undoButton) undoButton.hidden = false;

  const dataTransfer = new DataTransfer();
  dataTransfer.items.add(file);
  const input = document.getElementById("publicationPDF");
  if (input) input.files = dataTransfer.files;
}

function resetPublicationPdfInput() {
  const input = document.getElementById("publicationPDF");
  if (input) input.value = "";

  const fileName = document.getElementById("publicationPdfFileName");
  if (fileName) fileName.textContent = "No file selected";

  const undoButton = document.getElementById("publicationPdfUndo");
  if (undoButton) undoButton.hidden = true;

  publicationPdfFile = null;
}

function setupPdfUpload() {
  const uploadBox = document.getElementById("articlePdfUploadBox");
  const fileInput = document.getElementById("pdfFile");
  const undoButton = document.getElementById("articlePdfUndo");

  if (!uploadBox || !fileInput) return;

  uploadBox.setAttribute("tabindex", "0");

  uploadBox.addEventListener("click", function (event) {
    if (event.target.closest(".undo-pdf-btn")) return;
    fileInput.click();
  });

  uploadBox.addEventListener("keydown", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      fileInput.click();
    }
  });

  uploadBox.ondragover = function (event) {
    event.preventDefault();
    uploadBox.classList.add("dragover");
  };
  uploadBox.ondragleave = function (event) {
    if (!uploadBox.contains(event.relatedTarget)) {
      uploadBox.classList.remove("dragover");
    }
  };
  uploadBox.ondrop = function (event) {
    event.preventDefault();
    uploadBox.classList.remove("dragover");
    const file = event.dataTransfer.files[0];
    if (file) handlePdfFile(file);
  };
  fileInput.onchange = function () {
    const file = fileInput.files[0];
    if (file) handlePdfFile(file);
  };

  if (undoButton) {
    undoButton.onclick = function () {
      resetPdfInput();
    };
  }
}

function handlePdfFile(file) {
  if (file.type !== "application/pdf") {
    alert("Please choose a PDF file.");
    resetPdfInput();
    return;
  }

  const maxFileSize = 40 * 1024 * 1024;
  if (file.size > maxFileSize) {
    alert("The article PDF must not exceed 40 MB.");
    resetPdfInput();
    return;
  }

  if (!articles[currentArticleIndex]) {
    articles[currentArticleIndex] = createArticle();
  }

  articles[currentArticleIndex].pdf = file;
  const pdfName = document.getElementById("pdfFileName");
  if (pdfName) pdfName.textContent = file.name;

  const undoButton = document.getElementById("articlePdfUndo");
  if (undoButton) undoButton.hidden = false;

  const dataTransfer = new DataTransfer();
  dataTransfer.items.add(file);
  const input = document.getElementById("pdfFile");
  if (input) input.files = dataTransfer.files;
}

function resetPdfInput() {
  const input = document.getElementById("pdfFile");
  if (input) input.value = "";

  const pdfName = document.getElementById("pdfFileName");
  if (pdfName) pdfName.textContent = "No file selected";

  const undoButton = document.getElementById("articlePdfUndo");
  if (undoButton) undoButton.hidden = true;

  if (articles[currentArticleIndex]) {
    articles[currentArticleIndex].pdf = null;
  }
}

async function savePublication(mode = "draft") {
  saveCurrentArticle();

  const year = document.getElementById("year")?.value;
  const volume = document.getElementById("volume")?.value;
  const number = document.getElementById("number")?.value;

  if (!year || !volume || !number) {
    alert("Please complete the publication issue details.");
    return;
  }

  if (
    !publicationPdfFile &&
    !(
      editingPublicationID &&
      selectedDraftIssue &&
      selectedDraftIssue.publicationPDF
    )
  ) {
    alert("Please upload the publication issue PDF.");
    return;
  }

  for (let i = 0; i < articles.length; i += 1) {
    const article = articles[i];
    if (!article.title || article.title.trim() === "") {
      alert(`Please enter a title for Article ${i + 1}.`);
      loadArticle(i);
      return;
    }

    const existingPdf =
      article.pdf && typeof article.pdf === "object" && article.pdf.path
        ? article.pdf.path
        : null;
    const hasPdfFile = article.pdf instanceof File;
    const originalPdfExists =
      editingPublicationID &&
      selectedDraftIssue &&
      selectedDraftIssue.articles &&
      selectedDraftIssue.articles[i] &&
      selectedDraftIssue.articles[i].journalPDF;

    if (!hasPdfFile && !existingPdf && !originalPdfExists) {
      alert(`Please upload a PDF for Article ${i + 1}.`);
      loadArticle(i);
      return;
    }

    const authors = article.authors || [];
    for (let j = 0; j < authors.length; j += 1) {
      if (!authors[j].firstName || !authors[j].lastName) {
        alert(`Please complete Author ${j + 1} for Article ${i + 1}.`);
        loadArticle(i);
        return;
      }
    }
  }

  const formData = new FormData();

  if (mode === "publish") {
    if (editingPublicationID) {
      formData.append("action", "publish");
      formData.append("publicationID", String(editingPublicationID));
    } else {
      formData.append("action", "add");
      formData.append("mode", "publish");
    }
  } else {
    formData.append("action", editingPublicationID ? "update" : "add");
    formData.append("mode", "draft");
    if (editingPublicationID) {
      formData.append("publicationID", String(editingPublicationID));
    }
  }

  formData.append("year", year);
  formData.append("volume", volume);
  formData.append("number", number);

  if (publicationPdfFile) {
    formData.append("publicationPDF", publicationPdfFile);
  }

  const articleData = articles.map((article, index) => ({
    journalID: article.journalID || null,
    title: article.title,
    authors: article.authors,
    existingPdf:
      article.pdf && typeof article.pdf === "object" && article.pdf.path
        ? article.pdf.path
        : null,
  }));
  formData.append("articles", JSON.stringify(articleData));

  articles.forEach((article, index) => {
    if (article.pdf instanceof File) {
      formData.append(`pdf_${index}`, article.pdf);
    }
  });

  try {
    const response = await fetch("manage_journal_api.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to save the issue.");
    }

    alert(
      mode === "publish"
        ? "Draft published successfully."
        : "Issue saved successfully.",
    );
    hideForm();
    await loadJournals();
  } catch (error) {
    console.error(error);
    alert(error.message || "Unable to save the issue.");
  }
}

async function publishDraftIssue(publicationID) {
  const confirmed = confirm("Publish this draft?");
  if (!confirmed) return;

  try {
    const formData = new FormData();
    formData.append("action", "publish");
    formData.append("publicationID", String(publicationID));

    const response = await fetch("manage_journal_api.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to publish the draft.");
    }

    await loadJournals();
    showTab("current");
    alert("Draft published successfully.");
  } catch (error) {
    console.error(error);
    alert(error.message || "Unable to publish the draft.");
  }
}

/*Edit Issue*/
function editIssue(publicationID) {
  const issue = currentIssueData
    .concat(draftIssueData)
    .find((item) => Number(item.publicationID) === Number(publicationID));

  if (!issue) return;

  if (!issue.publicationID || Number(issue.publicationID) <= 0) {
    alert("Unable to load the publication for editing.");
    return;
  }

  window.location.href = `edit_journal.php?publicationID=${encodeURIComponent(issue.publicationID)}`;
}

function viewJournal(id) {
  window.location.href = `journal_details.php?journalID=${encodeURIComponent(id)}`;
}

/*Remove a single article (works on both current and draft issues, per spec — see JournalArticle::removeJournal()).*/
async function deleteJournalArticle(journalID) {
  const confirmed = confirm("Remove this article? This cannot be undone.");
  if (!confirmed) return;

  try {
    const formData = new FormData();
    formData.append("action", "deleteArticle");
    formData.append("journalID", String(journalID));

    const response = await fetch("manage_journal_api.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to remove article.");
    }

    await loadJournals();
  } catch (error) {
    console.error(error);
    alert(error.message || "Unable to remove article.");
  }
}

const searchInput = document.getElementById("searchInput");
if (searchInput) {
  searchInput.addEventListener("input", searchJournals);
}

function initializeManageJournalPage() {
  const currentTabButton = document.getElementById("currentTabButton");
  const draftTabButton = document.getElementById("draftTabButton");
  const addJournalButton = document.getElementById("addJournalButton");

  if (currentTabButton) {
    currentTabButton.onclick = function () {
      showTab("current", currentTabButton);
    };
  }

  if (draftTabButton) {
    draftTabButton.onclick = function () {
      showTab("draft", draftTabButton);
    };
  }

  if (addJournalButton) {
    addJournalButton.onclick = showForm;
  }

  setupPublicationPdfUpload();
  setupPdfUpload();
  renderIssueLists();
  updateDraftBadge();
  setAddJournalButtonState();
  loadJournals();
}

initializeManageJournalPage();
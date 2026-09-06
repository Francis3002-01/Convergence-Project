let journals = [];
let articles = [];
let currentArticleIndex = 0;
let articleDeleteIndex = null;
let authorDeleteIndex = null;
let publicationPdfFile = null;

function displayJournals(list = journals) {
  const journalList = document.getElementById("journalList");

  journalList.innerHTML = "";

  if (list.length === 0) {
    journalList.innerHTML = `
            <div class="empty-message">
                No journals found.
            </div>
        `;

    return;
  }

  journalList.innerHTML = `
        <div class="journal-list-header">
            <div>Journal Article Title</div>
            <div>Author</div>
            <div>Actions</div>
        </div>
    `;

  const issues = {};

  list.forEach((journal) => {
    const issueKey = `${journal.year}-${journal.volume}-${journal.number}`;
    if (!issues[issueKey]) {
      issues[issueKey] = {
        year: journal.year,
        volume: journal.volume,
        number: journal.number,
        journals: [],
      };
    }

    issues[issueKey].journals.push(journal);
  });

  Object.values(issues).forEach((issue) => {
    const issueSection = document.createElement("div");
    issueSection.className = "publication-issue";
    issueSection.innerHTML = `
            <div class="issue-details">
                Publication Issue
                ${escapeHtml(issue.year)}
                Volume
                ${escapeHtml(issue.volume)}
                Number
                ${escapeHtml(issue.number)}
            </div>
            <div class="issue-articles"></div>
        `;
    const articlesContainer = issueSection.querySelector(".issue-articles");

    issue.journals.forEach((journal) => {
      const article = document.createElement("div");

      article.className = "article-item";

      const authorNames = journal.authors
        .map((author) => `${author.firstName} ${author.lastName}`.trim())
        .join(", ");

      article.innerHTML = `

                <div class="article-info">
                    <div class="article-title">
                        ${escapeHtml(journal.title)}
                    </div>
                </div>


                <div class="article-authors">
                    ${escapeHtml(authorNames)}
                </div>


                <div class="actions">
                    <!-- View -->
                    <button
                        type="button"
                        class="action-btn view-btn"
                        onclick="viewJournal(${journal.id})"
                        title="View"
                        aria-label="View">

                        <i class="fa-solid fa-eye"></i>
                    </button>

                    <!-- Edit -->

                    <button type="button" class="action-btn edit-btn" onclick="editJournal(${journal.id})" title="Edit"aria-label="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </button>


                    <!-- Delete -->
                    <button type="button" class="action-btn delete-btn"onclick="deleteJournal(${journal.id})"title="Delete"aria-label="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>

                </div>
            `;

      articlesContainer.appendChild(article);
    });

    journalList.appendChild(issueSection);
  });
}

/*Load Journals From Database*/
async function loadJournals() {
  try {
    const response = await fetch("manage_journal_api.php?action=list");
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Failed to load journals.");
    }

    journals = result.journals || [];

    displayJournals();
  } catch (error) {
    console.error(error);
    document.getElementById("journalList").innerHTML = `

            <div class="empty-message">

                Unable to load journals.

            </div>

        `;

    alert("Unable to load journals.");
  }
}

/*Search*/
function searchJournals() {
  const searchInput = document.getElementById("searchInput");
  const search = searchInput.value.toLowerCase().trim();

  const filtered = journals.filter((journal) => {
    const authors = journal.authors
      .map((author) => `${author.firstName} ${author.lastName}`)
      .join(" ")
      .toLowerCase();

    return (
      journal.title.toLowerCase().includes(search) ||
      authors.includes(search) ||
      journal.year.toString().includes(search) ||
      journal.volume.toString().includes(search) ||
      journal.number.toString().includes(search)
    );
  });

  displayJournals(filtered);
}

/*Show Form*/
function showForm() {
  document.getElementById("journalListContainer").style.display = "none";
  document.getElementById("journalForm").style.display = "block";
  document.querySelector(".page-actions").style.display = "none";
  document.getElementById("pageTitle").textContent = "Add Journal";
  document.getElementById("publicationStep").style.display = "block";
  document.getElementById("articleStep").style.display = "none";
  resetPublicationForm();
}

/*Hide Form*/
function hideForm() {
  document.getElementById("journalListContainer").style.display = "block";
  document.getElementById("journalForm").style.display = "none";
  document.querySelector(".page-actions").style.display = "flex";
  document.getElementById("pageTitle").textContent = "Manage Journals";
  articles = [];
  currentArticleIndex = 0;
  publicationPdfFile = null;
}

/*Reset Publication For */
function resetPublicationForm() {
  const year = document.getElementById("year");
  const volume = document.getElementById("volume");
  const number = document.getElementById("number");
  const currentYear = new Date().getFullYear();
  year.innerHTML = "";

  for (let i = currentYear; i >= 2000; i--) {
    const option = document.createElement("option");
    option.value = i;
    option.textContent = i;

    if (i === currentYear) {
      option.selected = true;
    }

    year.appendChild(option);
  }

  volume.value = "";
  number.value = "";
  articles = [createArticle()];
  currentArticleIndex = 0;
  publicationPdfFile = null;
  resetPublicationPdfInput();
  setupPublicationPdfUpload();
}

/*Create Article */
function createArticle() {
  return {
    title: "",

    pdf: null,

    authors: [
      {
        firstName: "",

        lastName: "",
      },
    ],
  };
}

/*Go To Articles */
function goToArticles() {
  const year = document.getElementById("year").value;
  const volume = document.getElementById("volume").value;
  const number = document.getElementById("number").value;

  if (!year || !volume || !number) {
    alert("Please complete all publication issue fields.");

    return;
  }

  if (!publicationPdfFile) {
    alert("Please upload the publication issue PDF.");

    return;
  }

  document.getElementById("displayYear").textContent = year;
  document.getElementById("displayVolume").textContent = volume;
  document.getElementById("displayNumber").textContent = number;
  document.getElementById("publicationStep").style.display = "none";
  document.getElementById("articleStep").style.display = "block";
  currentArticleIndex = 0;
  renderArticleTabs();
  loadArticle(0);
}

/*Go Back To Publication*/
function goToPublication() {
  saveCurrentArticle();
  document.getElementById("articleStep").style.display = "none";
  document.getElementById("publicationStep").style.display = "block";
  setupPublicationPdfUpload();
}

/*Article Tabs */
function renderArticleTabs() {
  const container = document.getElementById("articleTabs");
  container.innerHTML = "";

  articles.forEach((article, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "article-tab";

    if (index === currentArticleIndex) {
      button.classList.add("active");
    }

    button.textContent = index + 1;
    button.onclick = function () {
      saveCurrentArticle();
      loadArticle(index);
    };

    container.appendChild(button);
  });
}

/*Load Article*/
function loadArticle(index) {
  if (currentArticleIndex !== index && articles[currentArticleIndex]) {
    saveCurrentArticle();
  }

  currentArticleIndex = index;

  const article = articles[index];

  document.getElementById("articleHeading").textContent =
    `Article ${index + 1}`;

  document.getElementById("articleTitle").value = article.title;

  const fileName = document.getElementById("pdfFileName");

  if (article.pdf) {
    fileName.textContent = article.pdf.name || article.pdf;
  } else {
    fileName.textContent = "PDF files only";
  }

  renderAuthors();
  renderArticleTabs();

  const deleteButton = document.getElementById("deleteArticleButton");

  if (index === 0) {
    deleteButton.style.visibility = "hidden";
  } else {
    deleteButton.style.visibility = "visible";
  }

  resetPdfInput();
  setupPdfUpload();
}

/*Save Current Article*/
function saveCurrentArticle() {
  if (!articles[currentArticleIndex]) {
    return;
  }

  const article = articles[currentArticleIndex];
  const title = document.getElementById("articleTitle");

  if (title) {
    article.title = title.value.trim();
  }

  const pdfInput = document.getElementById("pdfFile");

  if (pdfInput && pdfInput.files.length) {
    article.pdf = pdfInput.files[0];
  }

  const authorRows = document.querySelectorAll(".author-row");

  if (authorRows.length) {
    article.authors = [];

    authorRows.forEach((row) => {
      const firstName = row.querySelector(".first-name").value.trim();

      const lastName = row.querySelector(".last-name").value.trim();

      article.authors.push({
        firstName: firstName,

        lastName: lastName,
      });
    });
  }
}

/*Add Article*/
function addArticle() {
  saveCurrentArticle();
  articles.push(createArticle());
  currentArticleIndex = articles.length - 1;
  renderArticleTabs();
  loadArticle(currentArticleIndex);
}

/*Delete Article Request */

function requestDeleteArticle() {
  if (currentArticleIndex === 0) {
    return;
  }

  articleDeleteIndex = currentArticleIndex;
  document.getElementById("modalTitle").textContent = "Remove Article?";
  document.getElementById("modalMessage").textContent =
    m`Are you sure you want to remove Article ${currentArticleIndex + 1}?`;
  document.getElementById("confirmDeleteButton").onclick = deleteArticle;
  document.getElementById("confirmationModal").classList.add("active");
}

//Delete Article
function deleteArticle() {
  saveCurrentArticle();

  articles.splice(articleDeleteIndex, 1);

  closeModal();

  if (currentArticleIndex >= articles.length) {
    currentArticleIndex = articles.length - 1;
  }

  renderArticleTabs();
  loadArticle(currentArticleIndex);
}

//Render Authors
function renderAuthors() {
  const container = document.getElementById("authors");

  container.innerHTML = "";

  const authors = articles[currentArticleIndex].authors;

  authors.forEach((author, index) => {
    const row = document.createElement("div");

    row.className = "author-row";

    row.innerHTML = `

                <div class="author-fields">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" class="first-name"value="${escapeHtml(author.firstName)}"placeholder="First name">
                    </div>

                    <div class="form-group">
                        <label>
                            Last Name
                        </label>

                        <input type="text" class="last-name" value="${escapeHtml(author.lastName)}"placeholder="Last name">
                    </div>
                </div>


                ${
                  index === 0
                    ? ""
                    : `
                        <button type="button" class="delete-author-btn" onclick="requestDeleteAuthor(${index})"title="Remove author"aria-label="Remove author">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        `
                }

            `;

    container.appendChild(row);
  });
}

/*Add Author*/
function addAuthor() {
  saveCurrentArticle();

  articles[currentArticleIndex].authors.push({
    firstName: "",
    lastName: "",
  });

  renderAuthors();
}

/*Delete Author Request*/
function requestDeleteAuthor(index) {
  saveCurrentArticle();
  const author = articles[currentArticleIndex].authors[index];
  const fullName = `${author.firstName} ${author.lastName}`.trim();
  authorDeleteIndex = index;
  document.getElementById("modalTitle").textContent = "Remove Author?";
  document.getElementById("modalMessage").textContent = fullName
    ? `Are you sure you want to remove ${fullName}?`
    : "Are you sure you want to remove this author?";

  document.getElementById("confirmDeleteButton").onclick = deleteAuthor;
  document.getElementById("confirmationModal").classList.add("active");
}

/*Delete Author */
function deleteAuthor() {
  articles[currentArticleIndex].authors.splice(authorDeleteIndex, 1);
  closeModal();
  renderAuthors();
}

/*Close Modal*/
function closeModal() {
  document.getElementById("confirmationModal").classList.remove("active");
  articleDeleteIndex = null;
  authorDeleteIndex = null;
}

/*Publication PDF Upload*/
function setupPublicationPdfUpload() {
  const uploadBox = document.getElementById("publicationPdfUploadBox");
  const fileInput = document.getElementById("publicationPDF");

  if (!uploadBox || !fileInput) {
    return;
  }

  uploadBox.ondragover = function (e) {
    e.preventDefault();

    uploadBox.classList.add("dragover");
  };

  uploadBox.ondragleave = function (e) {
    if (!uploadBox.contains(e.relatedTarget)) {
      uploadBox.classList.remove("dragover");
    }
  };

  uploadBox.ondrop = function (e) {
    e.preventDefault();
    uploadBox.classList.remove("dragover");
    const file = e.dataTransfer.files[0];

    if (file) {
      handlePublicationPdfFile(file);
    }
  };

  fileInput.onchange = function () {
    const file = fileInput.files[0];

    if (file) {
      handlePublicationPdfFile(file);
    }
  };
}

/*Handle Publication PDF */
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
  if (fileName) {
    fileName.textContent = file.name;
  }

  const dataTransfer = new DataTransfer();
  dataTransfer.items.add(file);
  const input = document.getElementById("publicationPDF");

  if (input) {
    input.files = dataTransfer.files;
  }
}

/*Reset Publication PDF Input */
function resetPublicationPdfInput() {
  const input = document.getElementById("publicationPDF");

  if (input) {
    input.value = "";
  }

  const fileName = document.getElementById("publicationPdfFileName");

  if (fileName) {
    fileName.textContent = "PDF files only";
  }

  publicationPdfFile = null;
}

/*PDF Upload */
function setupPdfUpload() {
  const uploadBox = document.getElementById("pdfUploadBox");
  const fileInput = document.getElementById("pdfFile");
  if (!uploadBox || !fileInput) {
    return;
  }

  uploadBox.ondragover = function (e) {
    e.preventDefault();

    uploadBox.classList.add("dragover");
  };

  uploadBox.ondragleave = function (e) {
    if (!uploadBox.contains(e.relatedTarget)) {
      uploadBox.classList.remove("dragover");
    }
  };

  uploadBox.ondrop = function (e) {
    e.preventDefault();

    uploadBox.classList.remove("dragover");

    const file = e.dataTransfer.files[0];

    if (file) {
      handlePdfFile(file);
    }
  };

  fileInput.onchange = function () {
    const file = fileInput.files[0];

    if (file) {
      handlePdfFile(file);
    }
  };
}

/*Handle PDF */
function handlePdfFile(file) {
  if (file.type !== "application/pdf") {
    alert("Please choose a PDF file");
    resetPdfInput();
    return;
  }

  const maxFileSize = 40 * 1024 * 1024;

  if (file.size > maxFileSize) {
    alert("The article PDF must not exceed 40 MB");
    resetPdfInput();
    return;
  }

  articles[currentArticleIndex].pdf = file;
  document.getElementById("pdfFileName").textContent = file.name;
  const dataTransfer = new DataTransfer();
  dataTransfer.items.add(file);
  document.getElementById("pdfFile").files = dataTransfer.files;
}

/*Reset PDF Input*/
function resetPdfInput() {
  const input = document.getElementById("pdfFile");

  if (input) {
    input.value = "";
  }
}

/*Save Publication*/
async function savePublication() {
  saveCurrentArticle();
  const year = document.getElementById("year").value;
  const volume = document.getElementById("volume").value;
  const number = document.getElementById("number").value;

  /*Validate Publication Issue*/
  if (!year || !volume || !number) {
    alert("Please complete the publication issue details.");
    return;
  }

  /*Validate Publication PDF*/
  if (!publicationPdfFile) {
    alert("Please upload the publication issue PDF.");
    return;
  }

  /*Validate Articles */
  for (let i = 0; i < articles.length; i++) {
    const article = articles[i];

    if (!article.title) {
      alert(`Please enter a title for Article ${i + 1}.`);
      loadArticle(i);
      return;
    }

    if (!article.pdf) {
      alert(`Please upload a PDF for Article ${i + 1}.`);
      loadArticle(i);
      return;
    }

    for (let j = 0; j < article.authors.length; j++) {
      if (!article.authors[j].firstName || !article.authors[j].lastName) {
        alert(`Please complete Author ${j + 1} for Article ${i + 1}.`);
        loadArticle(i);
        return;
      }
    }
  }

  /*Create FormData */
  const formData = new FormData();
  formData.append("action", "add");
  formData.append("year", year);
  formData.append("volume", volume);
  formData.append("number", number);

  //Publication Issue PDF*/
  formData.append("publicationPDF", publicationPdfFile);

  //Article information
  const articleData = articles.map((article, index) => ({
    index: index,
    title: article.title,
    authors: article.authors,
  }));

  formData.append("articles", JSON.stringify(articleData));

  articles.forEach((article, index) => {
    formData.append(`pdf_${index}`, article.pdf);
  });

  /*Send To API */
  try {
    const response = await fetch("manage_journal_api.php", {
      method: "POST",

      body: formData,
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Failed to save publication.");
    }

    alert("Publication issue and articles saved successfully.");

    hideForm();

    await loadJournals();
  } catch (error) {
    console.error(error);

    alert(error.message || "Unable to save publication.");
  }
}

function viewJournal(id) {
  window.location.href = `journal_details.php?journalID=${encodeURIComponent(id)}`;
}

function editJournal(id) {
  window.location.href = `edit_journal.php?journalID=${encodeURIComponent(id)}`;
}

async function deleteJournal(id) {
  const journal = journals.find((journal) => journal.id === id);

  if (!journal) {
    return;
  }

  const confirmed = confirm(`Delete "${journal.title}"?`);

  if (!confirmed) {
    return;
  }

  try {
    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("journalID", id);
    const response = await fetch("manage_journal_api.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Failed to delete journal.");
    }

    await loadJournals();
  } catch (error) {
    console.error(error);
    alert(error.message || "Unable to delete journal.");
  }
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

const searchInput = document.getElementById("searchInput");

if (searchInput) {
  searchInput.addEventListener("input", searchJournals);
}

loadJournals();
let currentIssueData = [];
let draftIssueData = [];
let archiveIssueData = [];
let activeTab = "current";

/* -----------------------------------------
   ESCAPE HTML
   ----------------------------------------- */

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/* -----------------------------------------
   ADD JOURNAL BUTTON
   ----------------------------------------- */

function setAddJournalButtonState() {
  const button = document.getElementById("addJournalButton");
  if (!button) return;
  const hasDraft = draftIssueData.length > 0;
  button.disabled = hasDraft;
  button.title = hasDraft ? "A draft issue already exists." : "Add Journal";
}

/* -----------------------------------------
   DRAFT BADGE
   ----------------------------------------- */

function updateDraftBadge() {
  const badge = document.getElementById("draftCount");

  if (badge) {
    badge.textContent = String(draftIssueData.length);
  }
}

/* -----------------------------------------
   TABS
   ----------------------------------------- */

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

/* -----------------------------------------
   TRANSFORM ISSUE
   ----------------------------------------- */
function transformIssue(issue) {
  const cleaned = {
    ...issue,
  };

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

/* -----------------------------------------
   RENDER ISSUE LISTS
   ----------------------------------------- */

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

/* -----------------------------------------
   RENDER ISSUE CONTAINER
   ----------------------------------------- */

function renderIssueContainer(container, issueList, tabName) {
  if (!container) return;

  container.innerHTML = "";

  const cleanedList = (Array.isArray(issueList) ? issueList : []).map(
    transformIssue,
  );

  if (cleanedList.length === 0) {
    container.innerHTML = `
      <div class="empty-message">
        No ${tabName} issue found.
      </div>
    `;

    return;
  }

  cleanedList.forEach((issue) => {
    const issueSection = document.createElement("div");

    issueSection.className = "publication-issue";

    /* -----------------------------------------
       EDITORIAL NOTE
       ----------------------------------------- */

    let editorialNoteHtml = "";

    if (
      issue.publicationID &&
      Number(issue.publicationID) > 0 &&
      issue.publicationPDF
    ) {
      editorialNoteHtml = `
        <div class="editorial-note-action">

          <button
            type="button"
            class="action-btn editorial-note-btn"
            data-storage-path="${escapeHtml(issue.publicationPDF)}"
            onclick="viewEditorNote(this)"
            title="Read editorial note"
            aria-label="Read editorial note"
          >
            <i class="fa-solid fa-file-pdf"></i>
            Read Editorial Note
          </button>

          <button
            type="button"
            class="action-btn editorial-note-btn"
            data-storage-path="${escapeHtml(issue.publicationPDF)}"
            onclick="downloadEditorNote(this)"
            title="Download editorial note"
            aria-label="Download editorial note"
          >
            <i class="fa-solid fa-download"></i>
            Download Editorial Note
          </button>

        </div>
      `;
    }

    /* -----------------------------------------
       ARTICLES
       ----------------------------------------- */

    const articleHtml = (issue.articles || []).length
      ? issue.articles
          .map((article) => {
            const authors =
              (article.authors || [])
                .map((author) =>
                  `${escapeHtml(author.firstName || "")} ${escapeHtml(
                    author.lastName || "",
                  )}`.trim(),
                )
                .filter(Boolean)
                .join(", ") || "Unknown author";

            return `
                <div class="article-item">

                  <div class="article-info">
                    <div class="article-title">
                      ${escapeHtml(article.title || "Untitled Article")}
                    </div>
                  </div>

                  <div class="article-authors">
                    ${escapeHtml(authors)}
                  </div>

                  <div class="article-item-actions">

                    <button
                      type="button"
                      class="action-btn view-btn"
                      onclick="viewJournal(${Number(article.journalID)})"
                      title="View article"
                      aria-label="View article"
                    >
                      <i class="fa-solid fa-eye"></i>
                    </button>

                    <button
                      type="button"
                      class="action-btn delete-btn"
                      onclick="deleteJournalArticle(${Number(
                        article.journalID,
                      )})"
                      title="Remove article"
                      aria-label="Remove article"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>

                  </div>

                </div>
              `;
          })
          .join("")
      : `
          <div class="empty-message">
            No articles in this issue.
          </div>
        `;

    /* -----------------------------------------
       ISSUE ACTIONS
       ----------------------------------------- */

    let actionsHtml = "";

    if (tabName === "draft") {
      actionsHtml = `
        <div class="issue-actions">

          <button
            type="button"
            class="action-btn edit-btn"
            onclick="editIssue(${Number(issue.publicationID)})"
            title="Continue editing"
            aria-label="Continue editing"
          >
            <i class="fa-solid fa-pen"></i>
            Continue Editing
          </button>

          <button
            type="button"
            class="action-btn publish-btn"
            onclick="publishDraftIssue(${Number(issue.publicationID)})"
            title="Publish draft"
            aria-label="Publish draft"
          >
            <i class="fa-solid fa-upload"></i>
            Publish
          </button>

        </div>
      `;
    } else {
      actionsHtml = `
        <div class="issue-actions">

          <button
            type="button"
            class="action-btn edit-btn"
            onclick="editIssue(${Number(issue.publicationID)})"
            title="Edit issue"
            aria-label="Edit issue"
          >
            <i class="fa-solid fa-pen"></i>
          </button>

        </div>
      `;
    }

    /* -----------------------------------------
       ISSUE HTML
       ----------------------------------------- */

    issueSection.innerHTML = `
      <div class="issue-details">

        <div class="issue-information">

          <span>Publication Issue</span>

          <strong>
            ${escapeHtml(issue.year || "-")}
          </strong>

          <span>Volume</span>

          <strong>
            ${escapeHtml(issue.volume || "-")}
          </strong>

          <span>Number</span>

          <strong>
            ${escapeHtml(issue.number || "-")}
          </strong>

        </div>

        ${actionsHtml}

      </div>

      ${editorialNoteHtml}

      <div class="issue-articles">
        ${articleHtml}
      </div>
    `;

    container.appendChild(issueSection);
  });
}

/* -----------------------------------------
   LOAD JOURNALS
   ----------------------------------------- */

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
  } catch (error) {
    console.error(error);

    const currentList = document.getElementById("currentJournalList");

    const draftList = document.getElementById("draftJournalList");

    if (currentList) {
      currentList.innerHTML = `
        <div class="empty-message">
          Unable to load current issue.
        </div>
      `;
    }

    if (draftList) {
      draftList.innerHTML = `
        <div class="empty-message">
          Unable to load draft issue.
        </div>
      `;
    }

    alert(error.message || "Unable to load issues.");
  }
}

/* -----------------------------------------
   SEARCH JOURNALS
   ----------------------------------------- */

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

/* -----------------------------------------
   OPEN ADD JOURNAL PAGE
   ----------------------------------------- */

function openAddJournalPage() {
  const button = document.getElementById("addJournalButton");

  if (button && button.disabled) {
    return;
  }

  window.location.href = "add_journal.php";
}

/* -----------------------------------------
   PUBLISH DRAFT
   ----------------------------------------- */

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

/* -----------------------------------------
   EDIT ISSUE
   ----------------------------------------- */

function editIssue(publicationID) {
  const issue = currentIssueData
    .concat(draftIssueData)
    .find((item) => Number(item.publicationID) === Number(publicationID));

  if (!issue) return;

  if (!issue.publicationID || Number(issue.publicationID) <= 0) {
    alert("Unable to load the publication for editing.");

    return;
  }

  window.location.href = `edit_journal.php?publicationID=${encodeURIComponent(
    issue.publicationID,
  )}`;
}

/* -----------------------------------------
   VIEW ARTICLE
   ----------------------------------------- */

function viewJournal(id) {
  window.location.href = `journal_details.php?journalID=${encodeURIComponent(
    id,
  )}`;
}

/* -----------------------------------------
   VIEW EDITORIAL NOTE
   ----------------------------------------- */

async function viewEditorNote(button) {
  const storagePath = button?.dataset?.storagePath || "";

  if (!storagePath) {
    alert("Editorial note PDF is not available.");

    return;
  }

  try {
    const response = await fetch(
      `manage_journal_api.php?action=pdfUrl&type=publication&storagePath=${encodeURIComponent(
        storagePath,
      )}`,
    );

    const result = await response.json();

    if (!response.ok || !result.success || !result.data?.pdfUrl) {
      throw new Error(result.message || "Unable to open the editorial note.");
    }

    window.open(result.data.pdfUrl, "_blank");
  } catch (error) {
    console.error(error);

    alert(error.message || "Unable to open the editorial note.");
  }
}

/* -----------------------------------------
   DOWNLOAD EDITORIAL NOTE
   ----------------------------------------- */

async function downloadEditorNote(button) {
  const storagePath = button?.dataset?.storagePath || "";

  if (!storagePath) {
    alert("Editorial note PDF is not available.");

    return;
  }

  try {
    /* Get the signed Supabase URL */

    const response = await fetch(
      `manage_journal_api.php?action=pdfUrl&type=publication&storagePath=${encodeURIComponent(
        storagePath,
      )}`,
    );

    const result = await response.json();

    if (!response.ok || !result.success || !result.data?.pdfUrl) {
      throw new Error(result.message || "Unable to get the editorial note.");
    }

    /* Fetch the actual PDF */

    const pdfResponse = await fetch(result.data.pdfUrl);

    if (!pdfResponse.ok) {
      throw new Error("Unable to download the editorial note PDF.");
    }

    /* Convert PDF to Blob */

    const pdfBlob = await pdfResponse.blob();

    /* Create temporary local URL */

    const blobUrl = window.URL.createObjectURL(pdfBlob);

    /* Create download link */

    const link = document.createElement("a");

    link.href = blobUrl;

    link.download = "Editorial_Note.pdf";

    document.body.appendChild(link);

    /* Trigger download */

    link.click();

    /* Clean up */

    document.body.removeChild(link);

    window.URL.revokeObjectURL(blobUrl);
  } catch (error) {
    console.error(error);

    alert(error.message || "Unable to download the editorial note.");
  }
}

/* =========================================================
   DELETE JOURNAL ARTICLE
   ========================================================= */

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

    alert(error.message || "Unable to remove article");
  }
}

/* -----------------------------------------
   INITIALIZE MANAGE JOURNAL PAGE
   ----------------------------------------- */
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
    addJournalButton.onclick = openAddJournalPage;
  }

  const searchInput = document.getElementById("searchInput");

  if (searchInput) {
    searchInput.addEventListener("input", searchJournals);
  }

  renderIssueLists();
  updateDraftBadge();
  setAddJournalButtonState();

  loadJournals();
}

initializeManageJournalPage();
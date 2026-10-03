let currentIssueData = [];
let draftIssueData = [];
let archiveIssueData = [];
let activeTab = "current";
let pendingAction = null;

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function openConfirmationModal(title, message, buttonText, action) {

  const modal = document.getElementById("confirmationModal");
  const titleElement = document.getElementById("confirmationTitle");
  const messageElement = document.getElementById("confirmationMessage");
  const confirmButton = document.getElementById("confirmDeleteButton");

  if (!modal || !titleElement || !messageElement || !confirmButton) {
    console.error("Confirmation modal elements not found.");
    return;
  }

  titleElement.textContent = title;
  messageElement.textContent = message;
  confirmButton.textContent = buttonText;

  pendingAction = action;

  confirmButton.onclick = async function (event) {

    event.preventDefault();
    event.stopPropagation();

    const actionToRun = pendingAction;

    closeConfirmationModal();

    if (typeof actionToRun === "function") {
      await actionToRun();
    }
  };

  modal.classList.add("show");
}

function closeConfirmationModal() {
  const modal = document.getElementById("confirmationModal");

  if (modal) {
    modal.classList.remove("show");
  }

  pendingAction = null;
}

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

/* -----------------------------------------
   CLOSE MESSAGE MODAL
   ----------------------------------------- */

function closeMessageModal() {
  const modal = document.getElementById("messageModal");

  if (modal) {
    modal.classList.remove("show");
  }
}

function setAddJournalButtonState() {
  const button = document.getElementById("addJournalButton");

  if (!button) {
    return;
  }

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
    currentTabButton?.classList.toggle("active", tabName === "current");
    draftTabButton?.classList.toggle("active", tabName === "draft");
  }

  renderIssueLists();
}

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
  if (!container) {
    return;
  }

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
      issue.publicationPDF &&
      Array.isArray(issue.articles) &&
      issue.articles.length > 0
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
                                        ${escapeHtml(
                                          article.title || "Untitled Article",
                                        )}
                                    </div>

                                </div>

                                <div class="article-authors">
                                    ${escapeHtml(authors)}
                                </div>

                                <div class="article-item-actions">

                                    <button
                                        type="button"
                                        class="action-btn view-btn"
                                        onclick="viewJournal(${Number(
                                          article.journalID,
                                        )})"
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
                        class="continue-edit-btn"
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
                        onclick="publishDraftIssue(${Number(
                          issue.publicationID,
                        )})"
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

/*async function loadJournals() {
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

    showMessage(
      "Unable to Load Journals",
      error.message || "Unable to load issues.",
    );
  }
}*/

async function loadJournals() {
  const currentList = document.getElementById("currentJournalList");
  const draftList = document.getElementById("draftJournalList");

  // Show loading bars
  if (currentList) {
    currentList.innerHTML = `
      <div class="journal-loading">
        <div class="loading-bar"></div>
        <p>Loading current articles...</p>
      </div>
    `;
  }

  if (draftList) {
    draftList.innerHTML = `
      <div class="journal-loading">
        <div class="loading-bar"></div>
        <p>Loading draft articles...</p>
      </div>
    `;
  }

  try {
    const response = await fetch("manage_journal_api.php?action=list");

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load issues.");
    }

    const payload = result.data || {};

    currentIssueData = Array.isArray(payload.current)
      ? payload.current
      : [];

    draftIssueData = Array.isArray(payload.draft)
      ? payload.draft
      : [];

    archiveIssueData = Array.isArray(payload.archive)
      ? payload.archive
      : [];

    // Replace loading bars with the actual articles
    renderIssueLists();

  } catch (error) {
    console.error(error);

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

    showMessage(
      "Unable to Load Journals",
      error.message || "Unable to load issues.",
    );
  }
}

function searchJournals() {
  const searchInput = document.getElementById("searchInput");

  const searchTerm = (searchInput ? searchInput.value : "")
    .trim()
    .toLowerCase();

  /*
   * If the search box is empty,
   * return to the normal tab view.
   */
  if (searchTerm === "") {
    renderIssueLists();
    showTab(activeTab);
    return;
  }

  /*
   * Search ONLY Current and Draft issues.
   * Archive issues are intentionally excluded.
   */
  const searchableIssues = [
    ...(Array.isArray(currentIssueData)
      ? currentIssueData.map((issue) => ({
          ...issue,
          searchTab: "current",
        }))
      : []),

    ...(Array.isArray(draftIssueData)
      ? draftIssueData.map((issue) => ({
          ...issue,
          searchTab: "draft",
        }))
      : []),
  ];

  const searchResults = [];

  searchableIssues.forEach((issue) => {
    /*
     * Search article TITLE only.
     * Authors, year, volume, and number are not searched.
     */
    const matchingArticles = (issue.articles || []).filter((article) => {
      const title = String(article.title || "").toLowerCase();

      return title.includes(searchTerm);
    });

    if (matchingArticles.length > 0) {
      searchResults.push({
        ...issue,
        articles: matchingArticles,
      });
    }
  });

  /*
   * Hide the normal Current/Draft tab contents
   * while displaying search results.
   */
  const currentContent = document.getElementById("currentJournalContent");

  const draftContent = document.getElementById("draftJournalContent");

  if (currentContent) {
    currentContent.style.display = "none";
  }

  if (draftContent) {
    draftContent.style.display = "none";
  }

  /*
   * Use the Current list as the search-results area.
   */
  const currentList = document.getElementById("currentJournalList");

  if (!currentList) {
    return;
  }

  /*
   * No matching articles.
   */
  if (searchResults.length === 0) {
    currentList.innerHTML = `
            <div class="empty-message">
                No articles found matching "${escapeHtml(searchTerm)}".
            </div>
        `;

    if (currentContent) {
      currentContent.style.display = "block";
    }

    return;
  }

  /*
   * Display all matching Current and Draft articles.
   */
  currentList.innerHTML = "";

  searchResults.forEach((issue) => {
    renderIssueContainer(currentList, [issue], issue.searchTab);
  });

  if (currentContent) {
    currentContent.style.display = "block";
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
function publishDraftIssue(publicationID) {
  const modal = document.getElementById("publishConfirmationModal");
  const confirmButton = document.getElementById("confirmPublishButton");

  if (!modal || !confirmButton) {
    console.error("Publish confirmation modal elements not found.");
    return;
  }

  // Remove any previous click handler
  confirmButton.onclick = null;

  // Set the publish action
  confirmButton.onclick = async function (event) {
    event.preventDefault();
    event.stopPropagation();

    closePublishConfirmationModal();

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
        throw new Error(
          result.message || "Unable to publish the draft."
        );
      }

      await loadJournals();

      showTab("current");

      showMessage(
        "Published Successfully",
        "The draft journal issue has been published successfully."
      );

    } catch (error) {
      console.error(error);

      showMessage(
        "Unable to Publish",
        error.message || "Unable to publish the draft."
      );
    }
  };

  // Open the dedicated Publish modal
  modal.classList.add("show");
}

function closePublishConfirmationModal() {
    const modal = document.getElementById("publishConfirmationModal");

    if (modal) {
        modal.classList.remove("show");
    }
}

/* -----------------------------------------
   EDIT ISSUE
   ----------------------------------------- */

function editIssue(publicationID) {
  /*
   * Check if the selected issue is a draft.
   */
  const draftIssue = draftIssueData.find(
    (issue) => Number(issue.publicationID) === Number(publicationID),
  );

  if (draftIssue) {
    /*
     * Draft issues are continued through
     * the Add Journal form.
     */
    window.location.href = `add_journal.php?publicationID=${encodeURIComponent(
      publicationID,
    )}`;

    return;
  }

  /*
   * Check if the selected issue is the
   * current published issue.
   */
  const currentIssue = currentIssueData.find(
    (issue) => Number(issue.publicationID) === Number(publicationID),
  );

  if (currentIssue) {
    /*
     * Current issues are edited through
     * the Update Journal page.
     */
    window.location.href = `edit_journal.php?publicationID=${encodeURIComponent(
      publicationID,
    )}`;

    return;
  }

  /*
   * Issue was not found.
   */
  showMessage(
    "Unable to Edit",
    "The selected journal issue could not be found.",
  );
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
    showMessage(
      "Editorial Note Unavailable",
      "The editorial note PDF is not available.",
    );

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

    showMessage(
      "Unable to Open Editorial Note",
      error.message || "Unable to open the editorial note.",
    );
  }
}

/* -----------------------------------------
   DOWNLOAD EDITORIAL NOTE
   ----------------------------------------- */

async function downloadEditorNote(button) {
  const storagePath = button?.dataset?.storagePath || "";

  if (!storagePath) {
    showMessage(
      "Editorial Note Unavailable",
      "The editorial note PDF is not available.",
    );

    return;
  }

  try {
    /*
     * Get the signed Supabase URL.
     */

    const response = await fetch(
      `manage_journal_api.php?action=pdfUrl&type=publication&storagePath=${encodeURIComponent(
        storagePath,
      )}`,
    );

    const result = await response.json();

    if (!response.ok || !result.success || !result.data?.pdfUrl) {
      throw new Error(result.message || "Unable to get the editorial note.");
    }

    /*
     * Fetch the actual PDF.
     */

    const pdfResponse = await fetch(result.data.pdfUrl);

    if (!pdfResponse.ok) {
      throw new Error("Unable to download the editorial note PDF.");
    }

    /*
     * Convert PDF to Blob.
     */

    const pdfBlob = await pdfResponse.blob();

    /*
     * Create temporary local URL.
     */

    const blobUrl = window.URL.createObjectURL(pdfBlob);

    /*
     * Create download link.
     */

    const link = document.createElement("a");

    link.href = blobUrl;

    link.download = "Editorial_Note.pdf";

    document.body.appendChild(link);

    /*
     * Trigger download.
     */

    link.click();

    /*
     * Clean up.
     */

    document.body.removeChild(link);

    window.URL.revokeObjectURL(blobUrl);
  } catch (error) {
    console.error(error);
    showMessage(
      "Unable to Download Editorial Note",
      error.message || "Unable to download the editorial note.",
    );
  }
}

/* -----------------------------------------
   DELETE JOURNAL ARTICLE
   ----------------------------------------- */

function deleteJournalArticle(journalID) {
  openConfirmationModal(
    "Remove Article?",
    "Are you sure you want to remove this article? This cannot be undone.",
    "Remove Article",
    async function () {
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

        /*
         * If the backend deleted the entire
         * empty draft issue, inform the admin.
         */

        if (result.data && result.data.draftIssueDeleted) {
          showMessage(
            "Article Removed",
            "The article was removed. Since no articles remained, the empty draft issue was also removed.",
          );
        } else {
          showMessage(
            "Article Removed",
            "The article was removed successfully.",
          );
        }
      } catch (error) {
        console.error(error);

        showMessage(
          "Unable to Remove Article",
          error.message || "Unable to remove article.",
        );
      }
    },
  );
}

/* -----------------------------------------
   INITIALIZE MANAGE JOURNAL PAGE
   ----------------------------------------- */

function initializeManageJournalPage() {
  const currentTabButton = document.getElementById("currentTabButton");
  const draftTabButton = document.getElementById("draftTabButton");
  const addJournalButton = document.getElementById("addJournalButton");

  /*
   * Current tab.
   */

  if (currentTabButton) {
    currentTabButton.onclick = function () {
      showTab("current", currentTabButton);
    };
  }

  /*
   * Draft tab.
   */

  if (draftTabButton) {
    draftTabButton.onclick = function () {
      showTab("draft", draftTabButton);
    };
  }

  /*
   * Add Journal button.
   */

  if (addJournalButton) {
    addJournalButton.onclick = openAddJournalPage;
  }

  /*
   * Search.
   */

  /*const searchInput = document.getElementById("searchInput");

  if (searchInput) {
    searchInput.addEventListener("input", searchJournals);
  }*/
 const searchInput = document.getElementById("searchInput");
const searchButton = document.getElementById("searchButton");

/*
 * Magnifying glass performs the search.
 */
if (searchButton) {
    searchButton.addEventListener("click", searchJournals);
}

/*
 * Pressing Enter also performs the search.
 */
if (searchInput) {
    searchInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
            searchJournals();
        }
    });

    /*
     * When the search input becomes empty,
     * immediately restore all Current/Draft articles.
     */
    searchInput.addEventListener("input", function () {
        if (searchInput.value.trim() === "") {
            renderIssueLists();
            showTab(activeTab);
        }
    });
}

  /*
   * Initial rendering.
   */
  renderIssueLists();
  updateDraftBadge();
  setAddJournalButtonState();
  loadJournals();
}

/*START PAGE*/
initializeManageJournalPage();

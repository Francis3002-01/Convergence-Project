/* =========================================================
   DOM ELEMENTS
   ========================================================= */
const archiveList =document.getElementById("archiveList");
const archiveListPage =document.getElementById("archiveListPage");
const archiveDetailsPage =document.getElementById("archiveDetailsPage");
const searchInput =document.getElementById("searchInput");
const searchButton =document.getElementById("searchButton");
const backToArchivesButton =document.getElementById("backToArchivesButton");
const archiveYear =document.getElementById("archiveYear");
const archiveVolume =document.getElementById("archiveVolume");
const archiveNumber =document.getElementById("archiveNumber");
const archiveIssueTitle =document.getElementById("archiveIssueTitle");
const archiveArticlesList =document.getElementById("archiveArticlesList");
const readEditorialNoteButton =document.getElementById("readEditorialNoteButton");
/* =========================================================
   CURRENTLY SELECTED ISSUE
   ========================================================= */
let selectedArchiveIssue = null;

/* =========================================================
   INITIALIZE
   ========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {
        loadArchiveIssues();
        setupSearch();
        setupNavigation();
    }
);


/* =========================================================
   LOAD ARCHIVE ISSUES
   ========================================================= */
function loadArchiveIssues() {
    /*
     * STATIC FOR NOW
     *
     * Later this can become:
     *
     * fetch("archive_api.php?action=list")
     *
     * and then renderArchiveIssues(data)
     */
    renderArchiveIssues(archiveIssues);
}


/* =========================================================
   RENDER ARCHIVE ISSUES
   ========================================================= */
function renderArchiveIssues(issues) {

    archiveList.innerHTML = "";


    /* No results */

    if (issues.length === 0) {

        archiveList.innerHTML = `
            <div class="empty-message">
                No archive issues found.
            </div>
        `;

        return;
    }


    /* Render each issue */

    issues.forEach(
        function (issue) {

            const issueItem =
                document.createElement("div");

            issueItem.className =
                "archive-issue-item";


            issueItem.innerHTML = `

                <div class="archive-year">
                    ${escapeHtml(issue.year)}
                </div>

                <div class="archive-volume">
                    Volume ${escapeHtml(issue.volume)}
                </div>

                <div class="archive-number">
                    Number ${escapeHtml(issue.number)}
                </div>

                <div class="archive-actions">

                    <button
                        type="button"
                        class="view-archive-btn"
                        title="View Issue"
                        aria-label="View archive issue"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>

            `;


            /* View button */
            const viewButton =issueItem.querySelector(".view-archive-btn");
            viewButton.addEventListener(
                "click",
                function () {
                    openArchiveIssue(issue.id);
                }
            );


            archiveList.appendChild(issueItem);

        }
    );

}

/* =========================================================
   OPEN ARCHIVE ISSUE
   ========================================================= */
function openArchiveIssue(issueId) {
    const issue =
        archiveIssues.find(
            function (item) {
                return item.id === issueId;
            }
        );

    if (!issue) {
        return;
    }


    selectedArchiveIssue = issue;

    /* Issue information */
    archiveYear.textContent =issue.year;
    archiveVolume.textContent = `Volume ${issue.volume}`;
    archiveNumber.textContent =`Number ${issue.number}`;
    archiveIssueTitle.textContent =`Volume ${issue.volume}, Number ${issue.number}`;


    /* Editorial Note */

    readEditorialNoteButton.onclick =
        function () {
            openPdf(issue.editorialNote);
        };


    /* Articles */

    renderArchiveArticles(issue.articles);

    /* Switch to details page */
    archiveListPage.style.display ="none";
    archiveDetailsPage.style.display ="block";

    /* Scroll to top */
    window.scrollTo({top: 0,behavior: "smooth"});
}

/*RENDER ARTICLES */
function renderArchiveArticles(articles) {

    archiveArticlesList.innerHTML = "";


    /* No articles */

    if (!articles || articles.length === 0) {

        archiveArticlesList.innerHTML = `
            <div class="empty-message">
                No articles found for this issue.
            </div>
        `;

        return;
    }


    /* Render articles */

    articles.forEach(
        function (article) {

            const articleItem =
                document.createElement("div");

            articleItem.className =
                "archive-article-item";


            /* Authors */

            const authors =
                article.authors &&
                article.authors.length > 0

                    ? article.authors.join(", ")

                    : "No authors listed";


            articleItem.innerHTML = `

                <div class="archive-article-info">

                    <div class="archive-article-title">
                        ${escapeHtml(article.title)}
                    </div>

                </div>


                <div class="archive-article-authors">
                    ${escapeHtml(authors)}
                </div>


                <div class="archive-article-actions">

                    <button
                        type="button"
                        class="article-view-btn"
                        title="Read Article"
                        aria-label="Read article"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>

            `;

            /* Article view button */
            const viewButton = articleItem.querySelector(".article-view-btn");
            viewButton.addEventListener("click",function () {openPdf(article.pdf);});
            archiveArticlesList.appendChild(articleItem);
        }
    );

}


/*SEARCH SETUP*/
function setupSearch() {

    /* Search button */
    searchButton.addEventListener(
        "click",
        function () {
            performSearch();

        }
    );

    /* Search while typing */
    searchInput.addEventListener(
        "input",
        function () {
            performSearch();
        }
    );


    /* Enter key */
    searchInput.addEventListener(
        "keydown",
        function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                performSearch();
            }

        }
    );

}

/*SEARCH ARCHIVE ISSUES */
function performSearch() {
    const searchTerm =searchInput.value.trim().toLowerCase();

    /* Empty search */
    if (searchTerm === "") {
        renderArchiveIssues(archiveIssues);
        return;
    }

    /*
     * Search only:
     *
     * Year
     * Volume
     * Number
     */
    const filteredIssues =
        archiveIssues.filter(
            function (issue) {
                const year =String(issue.year).toLowerCase();
                const volume =String(issue.volume).toLowerCase();
                const number =String(issue.number).toLowerCase();
                const volumeText =`volume ${issue.volume}`.toLowerCase();
                const numberText =`number ${issue.number}`.toLowerCase();
                return (year.includes(searchTerm) ||volume.includes(searchTerm) ||number.includes(searchTerm) ||volumeText.includes(searchTerm) ||numberText.includes(searchTerm));
            }
        );

    renderArchiveIssues(filteredIssues);

}

function setupNavigation() {
    backToArchivesButton.addEventListener(
        "click",
        function () {
            closeArchiveIssue();
        }
    );

}


function closeArchiveIssue() {
    selectedArchiveIssue = null;
    archiveDetailsPage.style.display ="none";
    archiveListPage.style.display ="block";
    window.scrollTo({top: 0,behavior: "smooth"});
}


/*OPEN PDF*/
function openPdf(pdfUrl) {

    /*
     * The sample data currently uses "#"
     * because there are no real archive PDFs yet.
     */
    if (!pdfUrl || pdfUrl === "#") {
        alert("The PDF file is not available yet.");
        return;
    }

    /*
     * Later, this can receive your
     * Supabase PDF URL.
     */
    window.open(pdfUrl,"_blank","noopener,noreferrer");
}

/* =========================================================
   ESCAPE HTML
   =========================================================

   This is useful once the static data is replaced
   with database/API data.
   ========================================================= */
function escapeHtml(value) {
    if (value === null ||value === undefined) {
        return "";
    }

    return String(value)
        .replace(/&/g,"&amp;")
        .replace(/</g,"&lt;")
        .replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;")
        .replace( /'/g,"&#039;");
}
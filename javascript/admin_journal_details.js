/*GET JOURNAL ID */
const params = new URLSearchParams(window.location.search);
const journalID = params.get("journalID");

/*GET HTML ELEMENT */
const loading = document.getElementById("loading");
const error = document.getElementById("error");
const errorMessage = document.getElementById("errorMessage");
const detailsCard = document.getElementById("detailsCard");
const articleTitle = document.getElementById("articleTitle");
const publicationInfo = document.getElementById("publicationInfo");
const authors = document.getElementById("authors");
const citation = document.getElementById("citation");
const copyCitation = document.getElementById("copyCitation");
const pdfSection = document.getElementById("pdfSection");
const readOnline = document.getElementById("readOnline");
const downloadPdf = document.getElementById("downloadPdf");
const pdfViewer = document.getElementById("pdfViewer");
const pdfViewerContainer = document.getElementById("pdfViewerContainer");

function showError(message) {
  loading.style.display = "none";
  detailsCard.style.display = "none";
  errorMessage.textContent = message;
  error.style.display = "block";
}

function displayJournal(data) {
  const article = data.article || {};
  const publication = data.publication || null;
  const articleAuthors = data.authors || [];

  articleTitle.textContent = article.title || "Untitled Article";

  if (publication) {
    const year = publication.year ?? "";
    const volume = publication.volume ?? "";
    const number = publication.number ?? "";
    publicationInfo.textContent = `Volume ${volume}, Number ${number}, ${year}`;
  } 
  
  else 
    publicationInfo.textContent = "Publication information unavailable.";

  authors.innerHTML = "";

  if (articleAuthors.length === 0) {
    const authorItem = document.createElement("div");
    authorItem.className = "author-item";
    authorItem.textContent = "Unknown Author";
    authors.appendChild(authorItem);
  } 
  
  else {
    articleAuthors.forEach((author) => {
      const authorItem = document.createElement("div");
      authorItem.className = "author-item";
      const firstName = author.firstName || "";
      const lastName = author.lastName || "";
      authorItem.textContent = `${firstName} ${lastName}`.trim();
      authors.appendChild(authorItem);
    });
  }

  citation.textContent = data.citation || "Citation unavailable.";

  if (data.pdfUrl && typeof data.pdfUrl === "string") {
    const pdfUrl = data.pdfUrl.trim();

    if (pdfUrl !== "") {
      /* -----------------------------
                       DOWNLOAD PDF
                    ----------------------------- */
      downloadPdf.href = pdfUrl.includes("?")
        ? `${pdfUrl}&download`
        : `${pdfUrl}?download`;

      /*READ ONLINE */
      readOnline.onclick = function () {
        /*
         * IMPORTANT:
         *
         * The API returns a PUBLIC
         * Supabase Storage URL.
         *
         * We load that URL directly
         * into the iframe.
         */

        pdfViewer.src = pdfUrl;
        pdfViewerContainer.style.display = "block";

        /*
         * Scroll to PDF viewer.
         */

        pdfViewerContainer.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      };

      /* -----------------------------
                       SHOW PDF SECTION
                    ----------------------------- */
      pdfSection.style.display = "block";
    } else {
      pdfSection.style.display = "none";
    }
  } else {
    pdfSection.style.display = "none";
  }

  /* =====================================
               SHOW DETAILS
            ====================================== */
  loading.style.display = "none";
  error.style.display = "none";
  detailsCard.style.display = "block";
}

/* =========================================
           LOAD JOURNAL DETAILS
        ========================================= */
async function loadJournalDetails() {
  /* -------------------------------------
               CHECK JOURNAL ID
            ------------------------------------- */
  if (!journalID) {
    showError("No journal article was specified.");
    return;
  }

  try {
    /* ---------------------------------
                   REQUEST API
                --------------------------------- */
    const response = await fetch(
      `manage_journal_api.php?action=view&journalID=${encodeURIComponent(journalID)}`,
      {
        method: "GET",

        headers: {
          Accept: "application/json",
        },
      },
    );

    /* ---------------------------------
                   CHECK HTTP RESPONSE
                --------------------------------- */
    if (!response.ok) {
      throw new Error(`Server returned HTTP ${response.status}.`);
    }

    /* ---------------------------------
                   CHECK CONTENT TYPE
                --------------------------------- */
    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
      throw new Error("The server returned an invalid response.");
    }

    /* ---------------------------------
                   READ JSON
                --------------------------------- */
    const data = await response.json();
    /* ---------------------------------
                   DEBUG
                --------------------------------- */
    console.log("Journal API response:", data);

    /* ---------------------------------
                   CHECK API RESULT
                --------------------------------- */
    if (!data.success) {
      throw new Error(data.message || "Unable to load journal details.");
    }

    /* ---------------------------------
                   DISPLAY JOURNAL
                --------------------------------- */
    displayJournal(data);
  } catch (err) {
    console.error("Journal details error:", err);

    showError(err.message || "An error occurred while loading the journal.");
  }
}

/* =========================================
           COPY APA CITATION
        ========================================= */

copyCitation.addEventListener("click", async function () {
  const citationText = citation.textContent.trim();

  if (!citationText) {
    return;
  }

  /* ---------------------------------
                   TRY MODERN CLIPBOARD API
                --------------------------------- */
  try {
    await navigator.clipboard.writeText(citationText);
    const originalText = copyCitation.textContent;
    copyCitation.textContent = "Copied!";

    setTimeout(() => {
      copyCitation.textContent = originalText;
    }, 1500);
  } catch (err) {
    console.error("Copy citation error:", err);

    /* FALLBACK*/
    const textArea = document.createElement("textarea");
    textArea.value = citationText;
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.select();

    try {
      document.execCommand("copy");
      const originalText = copyCitation.textContent;
      copyCitation.textContent = "Copied!";

      setTimeout(() => {
        copyCitation.textContent = originalText;
      }, 1500);
    } catch (fallbackError) {
      console.error("Fallback copy failed:", fallbackError);
      alert("Unable to copy the citation.");
    } finally {
      document.body.removeChild(textArea);
    }
  }
});

loadJournalDetails();
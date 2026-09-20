/*GET JOURNAL ID */
const params = new URLSearchParams(window.location.search);
const journalID = params.get("journalID");

/*GET HTML ELEMENTS*/
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

/*SHOW ERROR */
function showError(message) {
  if (loading) {
    loading.style.display = "none";
  }

  if (detailsCard) {
    detailsCard.style.display = "none";
  }

  if (errorMessage) {
    errorMessage.textContent = message;
  }

  if (error) {
    error.style.display = "block";
  }
}

/* DISPLAY JOURNAL DETAILS*/
function displayJournal(data) {
  const article = data.article || {};
  const publication = data.publication || {};
  const articleAuthors = Array.isArray(data.authors) ? data.authors : [];

  if (articleTitle) {
    articleTitle.textContent = article.title || "Untitled Article";
  }

  if (publicationInfo) {
    const year =
      publication.year !== undefined &&
      publication.year !== null &&
      publication.year !== 0
        ? publication.year
        : "";

    const volume =
      publication.volume !== undefined &&
      publication.volume !== null &&
      publication.volume !== 0
        ? publication.volume
        : "";

    const number =
      publication.number !== undefined &&
      publication.number !== null &&
      publication.number !== 0
        ? publication.number
        : "";

    let publicationText = "";

    if (volume !== "") {
      publicationText += `Volume ${volume}`;
    }

    if (number !== "") {
      if (publicationText !== "") {
        publicationText += ", ";
      }

      publicationText += `Number ${number}`;
    }

    if (year !== "") {
      if (publicationText !== "") {
        publicationText += ", ";
      }

      publicationText += year;
    }

    publicationInfo.textContent =
      publicationText || "Publication information unavailable.";
  }

  if (authors) {
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
        const firstName = (author.firstName || "").trim();
        const lastName = (author.lastName || "").trim();

        authorItem.textContent =
          `${firstName} ${lastName}`.trim() || "Unknown Author";

        authors.appendChild(authorItem);
      });
    }
  }

  if (citation) {
    citation.textContent = data.citation || "Citation unavailable.";
  }

  const pdfUrl = typeof data.pdfUrl === "string" ? data.pdfUrl.trim() : "";

  if (pdfUrl !== "" && downloadPdf && readOnline && pdfSection) {
    downloadPdf.href = pdfUrl.includes("?")
      ? `${pdfUrl}&download`
      : `${pdfUrl}?download`;

    downloadPdf.target = "_blank";
    downloadPdf.rel = "noopener";
    readOnline.onclick = function () {
      if (!pdfViewer) {
        return;
      }

      pdfViewer.src = pdfUrl;

      if (pdfViewerContainer) {
        pdfViewerContainer.style.display = "block";

        pdfViewerContainer.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    };

    pdfSection.style.display = "block";
  } 
  
  else {
    if (pdfSection) {
      pdfSection.style.display = "none";
    }

    if (pdfViewer) {
      pdfViewer.src = "";
    }

    if (pdfViewerContainer) {
      pdfViewerContainer.style.display = "none";
    }
  }

  if (loading) {
    loading.style.display = "none";
  }

  if (error) {
    error.style.display = "none";
  }

  if (detailsCard) {
    detailsCard.style.display = "block";
  }
}

async function loadJournalDetails() {
  if (!journalID) {
    showError("No journal article was specified.");

    return;
  }

  try {
    const response = await fetch(
      `manage_journal_api.php?action=view&journalID=${encodeURIComponent(journalID)}`,
      {
        method: "GET",

        headers: {
          Accept: "application/json",
        },
      },
    );

    if (!response.ok) {
      throw new Error(`Server returned HTTP ${response.status}.`);
    }

    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
      throw new Error("The server returned an invalid response.");
    }

    const result = await response.json();
    console.log("Journal API response:", result);

  
    if (!result.success) {
      throw new Error(result.message || "Unable to load journal details.");
    }

    displayJournal(result.data || {});
  } 
  
  catch (err) {
    console.error("Journal details error:", err);

    showError(err.message || "An error occurred while loading the journal.");
  }
}

if (copyCitation) {
  copyCitation.addEventListener("click", async function () {
    const citationText = citation ? citation.textContent.trim() : "";

    if (!citationText) {
      return;
    }

    try {
      await navigator.clipboard.writeText(citationText);
      const originalText = copyCitation.textContent;
      copyCitation.textContent = "Copied!";
      setTimeout(() => {
        copyCitation.textContent = originalText;
      }, 1500);
    } 
    
    catch (err) {
      console.error("Copy citation error:", err);

      const textArea = document.createElement("textarea");
      textArea.value = citationText;
      textArea.style.position = "fixed";
      textArea.style.opacity = "0";
      textArea.style.pointerEvents = "none";
      document.body.appendChild(textArea);
      textArea.select();

      try {
        document.execCommand("copy");
        const originalText = copyCitation.textContent;
        copyCitation.textContent = "Copied!";

        setTimeout(() => {
          copyCitation.textContent = originalText;
        }, 1500);
      } 
      
      catch (fallbackError) {
        console.error("Fallback copy failed:", fallbackError);
        alert("Unable to copy the citation.");
      } 
      
      finally {
        document.body.removeChild(textArea);
      }
    }

  });

}

loadJournalDetails();

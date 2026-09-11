const API_URL = "manage_journal_api.php";

document.addEventListener("DOMContentLoaded", () => {
  const editForm = document.getElementById("editForm");

  if (!editForm) {
    console.error("Edit form not found.");
    return;
  }

  //Get journal ID from the form's data attribute
  const journalID = editForm.dataset.journalId;

  if (!journalID || Number(journalID) <= 0) {
    console.error("Invalid journal ID:", journalID);
    return;
  }

  loadJournal(journalID);
  const addAuthorButton = document.getElementById("addAuthorButton");

  if (addAuthorButton) {
    addAuthorButton.addEventListener("click", function () {
      addAuthor();
    });
  }

  initializePdfInputs();
  editForm.addEventListener("submit", saveChanges);
});

function goBack() {
  window.location.href = "manage_journal.php";
}

async function loadJournal(journalID) {
  const loading = document.getElementById("loading");
  const errorMessage = document.getElementById("errorMessage");
  const editForm = document.getElementById("editForm");

  try {
    //CALL API
    const response = await fetch(
      `${API_URL}?action=view&journalID=${encodeURIComponent(journalID)}`,
    );

    const responseText = await response.text();
    console.log("View API:", responseText);

    let result;

    try {
      result = JSON.parse(responseText);
    } catch {
      throw new Error("The API did not return valid JSON.");
    }

    //CHECK API RESULT
    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to load journal.");
    }

    //API RETURNS DATA DIRECTLY
    const article = result.article;
    const publication = result.publication;
    const authors = result.authors || [];

    //CHECK JOURNAL DATA
    if (!article || !publication) {
      throw new Error("The API returned incomplete journal data.");
    }

    //FILL JOURNAL ID
    const journalIDInput = document.getElementById("journalID");

    if (journalIDInput) {
      journalIDInput.value = article.journalID || journalID;
    }

    //FILL ARTICLE TITLE
    const titleInput = document.getElementById("title");

    if (titleInput) {
      titleInput.value = article.title || "";
    }

    //FILL PUBLICATION ISSUE
    const yearInput = document.getElementById("year");
    const volumeInput = document.getElementById("volume");
    const numberInput = document.getElementById("number");

    if (yearInput) {
      yearInput.value = publication.year || "";
    }

    if (volumeInput) {
      volumeInput.value = publication.volume || "";
    }

    if (numberInput) {
      numberInput.value = publication.number || "";
    }

    // FILL AUTHORS
    const authorsContainer = document.getElementById("authorsContainer");

    if (authorsContainer) {
      authorsContainer.innerHTML = "";

      if (Array.isArray(authors) && authors.length > 0) {
        authors.forEach((author) => {
          addAuthor(author.firstName || "", author.lastName || "");
        });
      } else {
        addAuthor();
      }
    }

    // CURRENT ARTICLE PDF
    showArticlePdf(result.pdfUrl);
    showPublicationPdf(publication.publicationPDF);
    

    // SHOW FORM
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
      errorMessage.textContent = error.message || "Unable to load journal.";

      errorMessage.style.display = "block";
    }
  }
}

//ADD AUTHOR ROW
function addAuthor(firstName = "", lastName = "") {
  const container = document.getElementById("authorsContainer");

  if (!container) {
    return;
  }

  //AUTHOR ROW
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

    //Always keep at least one author input
    if (document.querySelectorAll(".author-row").length === 0) {
      addAuthor();
    }
  });

  //ADD ELEMENTS TO ROW
  row.appendChild(firstNameInput);
  row.appendChild(lastNameInput);
  row.appendChild(removeButton);

  // ADD ROW TO CONTAINER
  container.appendChild(row);
}

function getAuthors() {
  const rows = document.querySelectorAll(".author-row");
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
        firstName: firstName,
        lastName: lastName,
      });
    }
  });

  return authors;
}

function showArticlePdf(pdfUrl) {
  const container = document.getElementById("currentPdf");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  if (!pdfUrl) {
    container.innerHTML = '<span class="no-file">No article PDF available.</span>';
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

function showPublicationPdf(pdfPath) {
  const container = document.getElementById("currentPublicationPdf");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  if (!pdfPath) {
    container.innerHTML =
      '<span class="no-file">No publication PDF available.</span>';
    return;
  }

  const text = document.createElement("span");
  text.className = "file-path";
  text.textContent = pdfPath;
  container.appendChild(text);
}

//INITIALIZE PDF FILE INPUTS
function initializePdfInputs() {
  const journalPDFInput = document.getElementById("journalPDF");
  const publicationPDFInput = document.getElementById("publicationPDF");
  const articlePdfFileName = document.getElementById("articlePdfFileName");
  const publicationPdfFileName = document.getElementById(
    "publicationPdfFileName",
  );
  const articlePdfUploadBox = document.getElementById("articlePdfUploadBox");
  const publicationPdfUploadBox = document.getElementById(
    "publicationPdfUploadBox",
  );


  //ARTICLE PDF FILE SELECTION
  if (journalPDFInput && articlePdfFileName && articlePdfUploadBox) {
    journalPDFInput.addEventListener("change", function () {
      if (this.files && this.files.length > 0) {
        articlePdfFileName.textContent = this.files[0].name;

        articlePdfUploadBox.classList.add("has-file");
      } else {
        articlePdfFileName.textContent = "No file selected";

        articlePdfUploadBox.classList.remove("has-file");
      }
    });
  }

  //PUBLICATION PDF FILE SELECTION
  if (publicationPDFInput && publicationPdfFileName && publicationPdfUploadBox) {
    publicationPDFInput.addEventListener("change", function () {
      if (this.files && this.files.length > 0) {
        publicationPdfFileName.textContent = this.files[0].name;

        publicationPdfUploadBox.classList.add("has-file");
      } else {
        publicationPdfFileName.textContent = "No file selected";

        publicationPdfUploadBox.classList.remove("has-file");
      }
    });
  }
}

async function saveChanges(event) {
  event.preventDefault();
  const saveButton = document.getElementById("saveButton");
  const form = document.getElementById("editForm");

  if (!form) {
    return;
  }

  const authors = getAuthors();
  if (authors.length === 0) {
    alert("Please add at least one author.");
    return;
  }

  if (saveButton) {
    saveButton.disabled = true;
    saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
  }

  try {

    const formData = new FormData(form);
    formData.set("action", "update");
    formData.set("authors", JSON.stringify(authors));
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

    alert(result.message || "Journal updated successfully.");

    window.location.href = "manage_journal.php";
  } catch (error) {
    console.error(error);

    alert(error.message || "An error occurred while saving.");

    if (saveButton) {
      saveButton.disabled = false;

      saveButton.innerHTML = '<i class="fa-solid fa-check"></i> Save Changes';
    }
  }
}
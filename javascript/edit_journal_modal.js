document.addEventListener("DOMContentLoaded", () => {
    setupModalEvents();
});

/* =========================================================
   MODAL ELEMENTS
========================================================= */

function getModal(id) {
    return document.getElementById(id);
}

/* =========================================================
   SAVE CONFIRMATION MODAL
========================================================= */

function openSaveConfirmModal() {
    const modal = getModal("saveConfirmModal");

    if (!modal) {
        console.error("saveConfirmModal was not found.");
        return;
    }

    modal.style.display = "flex";
}

function closeSaveConfirmModal() {
    const modal = getModal("saveConfirmModal");

    if (!modal) {
        return;
    }

    modal.style.display = "none";
}

/* =========================================================
   CANCEL / DISCARD MODAL
========================================================= */

function openCancelModal() {
    const modal = getModal("cancelConfirmModal");

    if (!modal) {
        console.error("cancelConfirmModal was not found.");
        return;
    }

    modal.style.display = "flex";
}

function closeCancelModal() {
    const modal = getModal("cancelConfirmModal");

    if (!modal) {
        return;
    }

    modal.style.display = "none";
}

function discardChanges() {
    window.location.href = "manage_journal.php";
}

/* =========================================================
   ERROR MODAL
========================================================= */

function showErrorModal(message) {
    const modal = getModal("errorModal");
    const messageElement = document.getElementById("errorModalMessage");

    if (!modal) {
        console.error("errorModal was not found.");
        alert(message);
        return;
    }

    if (messageElement) {
        messageElement.textContent =
            message || "An error occurred.";
    }

    modal.style.display = "flex";
}

function closeErrorModal() {
    const modal = getModal("errorModal");

    if (!modal) {
        return;
    }

    modal.style.display = "none";
}

/* =========================================================
   MODAL BUTTON EVENTS
========================================================= */

function setupModalEvents() {

    /* -----------------------------------------------------
       Confirm Changes modal
    ----------------------------------------------------- */

const confirmSaveButton =
    document.getElementById("confirmSaveButton");

if (confirmSaveButton) {
    confirmSaveButton.addEventListener("click", async () => {

        console.log("CONFIRM CHANGES BUTTON CLICKED");

        closeSaveConfirmModal();

        console.log("SAVE MODAL CLOSED");

        if (typeof saveChanges === "function") {
            console.log("saveChanges() FOUND");
            await saveChanges();
        } else {
            console.error("saveChanges() NOT FOUND");
        }
    });
} else {
    console.error("confirmSaveButton was not found.");
}

    /* -----------------------------------------------------
       Cancel / Discard modal
    ----------------------------------------------------- */

    const discardButton =
        document.getElementById("discardChangesButton");

    if (discardButton) {
        discardButton.addEventListener(
            "click",
            discardChanges
        );
    }

    /* -----------------------------------------------------
       Close save confirmation modal
    ----------------------------------------------------- */

    const closeSaveButton =
        document.getElementById("closeSaveModalButton");

    if (closeSaveButton) {
        closeSaveButton.addEventListener(
            "click",
            closeSaveConfirmModal
        );
    }

    /* -----------------------------------------------------
       Close cancel modal
    ----------------------------------------------------- */

    const closeCancelButton =
        document.getElementById("closeCancelModalButton");

    if (closeCancelButton) {
        closeCancelButton.addEventListener(
            "click",
            closeCancelModal
        );
    }

    /* -----------------------------------------------------
       Close error modal
    ----------------------------------------------------- */

    const closeErrorButton =
        document.getElementById("closeErrorModalButton");

    if (closeErrorButton) {
        closeErrorButton.addEventListener(
            "click",
            closeErrorModal
        );
    }

    /* -----------------------------------------------------
       Clicking outside a modal closes it
    ----------------------------------------------------- */

    document.addEventListener("click", (event) => {

        if (
            event.target.classList.contains("modal-overlay")
        ) {
            event.target.style.display = "none";
        }

    });

    /* -----------------------------------------------------
       ESC closes any open modal
    ----------------------------------------------------- */

    document.addEventListener("keydown", (event) => {

        if (event.key !== "Escape") {
            return;
        }

        const modals =
            document.querySelectorAll(".modal-overlay");

        modals.forEach((modal) => {
            modal.style.display = "none";
        });

    });
}
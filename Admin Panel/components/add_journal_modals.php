<!-- CANCEL JOURNAL CONFIRMATION MODAL -->
<div class="confirmation-modal" id="cancelConfirmationModal">

    <div
        class="confirmation-overlay"
        onclick="closeCancelConfirmationModal()"></div>

    <div class="confirmation-dialog">

        <div class="confirmation-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h3>
            Cancel Journal Creation?
        </h3>

        <p>
            Are you sure you want to cancel?
            Any information you entered will be lost.
        </p>

        <div class="confirmation-actions">

            <button
                type="button"
                class="modal-cancel-btn"
                onclick="closeCancelConfirmationModal()">
                Keep Editing
            </button>

            <button
                type="button"
                class="modal-confirm-btn"
                onclick="confirmCancelAddJournal()">
                Cancel
            </button>

        </div>

    </div>

</div>


<!-- PUBLISH CONFIRMATION MODAL -->
<div class="confirmation-modal" id="publishConfirmationModal">

    <div
        class="confirmation-overlay"
        onclick="closePublishConfirmationModal()"></div>

    <div class="confirmation-dialog">

        <div class="confirmation-icon">
            <i class="fa-solid fa-upload"></i>
        </div>

        <h3>
            Publish Journal Issue?
        </h3>

        <p id="publishConfirmationMessage">
            Are you sure you want to publish this journal issue?
            The current issue will be moved to the archive.
        </p>

        <div class="confirmation-actions">

            <button
                type="button"
                class="modal-cancel-btn"
                onclick="closePublishConfirmationModal()">
                Cancel
            </button>

            <button
                type="button"
                class="modal-confirm-btn"
                id="confirmPublishButton">
                Publish
            </button>

        </div>

    </div>

</div>


<!-- REMOVE ARTICLE / AUTHOR CONFIRMATION MODAL -->
<div class="confirmation-modal" id="confirmationModal">

    <div
        class="confirmation-overlay"
        onclick="closeConfirmationModal()"></div>

    <div class="confirmation-dialog">

        <div class="confirmation-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h3 id="confirmationTitle">
            Confirm Action
        </h3>

        <p id="confirmationMessage">
            Are you sure you want to continue?
        </p>

        <div class="confirmation-actions">

            <button
                type="button"
                class="modal-cancel-btn"
                onclick="closeConfirmationModal()">
                Cancel
            </button>

            <button
                type="button"
                class="modal-confirm-btn"
                id="confirmDeleteButton">
                Confirm
            </button>

        </div>

    </div>

</div>


<!-- MESSAGE MODAL -->
<div class="confirmation-modal" id="messageModal">

    <div class="confirmation-overlay" onclick="closeMessageModal()"></div>

    <div class="confirmation-dialog">

        <div class="confirmation-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>

        <h3 id="messageTitle">Notice</h3>

        <p id="messageText">Something happened.</p>

        <div class="confirmation-actions">

            <button type="button" class="modal-confirm-btn" onclick="closeMessageModal()">OK</button>

        </div>

    </div>

</div>
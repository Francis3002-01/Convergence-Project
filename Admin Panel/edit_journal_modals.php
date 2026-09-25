<!-- =====================================================
     CONFIRM CHANGES MODAL
===================================================== -->
<div id="saveConfirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fa-solid fa-circle-question"></i>
        </div>
        <h2>Confirm Changes</h2>
        <p>Are you sure you want to save these changes?</p>
        <div class="modal-actions">
            <button type="button" class="modal-cancel-btn" id="closeSaveModalButton">Cancel</button>
            <button type="button" class="modal-confirm-btn"id="confirmSaveButton">Confirm Changes</button>
        </div>
    </div>
</div>

<!-- =====================================================
     CANCEL / DISCARD MODAL
===================================================== -->
<div id="cancelConfirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h2>Discard Changes?</h2>
        <p>Are you sure you want to leave? Any unsaved changes will be lost.</p>
        
        <div class="modal-actions">

            <button
                type="button"
                class="modal-cancel-btn"
                id="closeCancelModalButton">
                Keep Editing
            </button>

            <button
                type="button"
                class="modal-danger-btn"
                id="discardChangesButton">
                Discard Changes
            </button>

        </div>

    </div>


</div>

<!-- =====================================================
     ERROR MODAL
===================================================== -->

<div
    id="errorModal"
    class="modal-overlay"
    style="display: none;">
    <div class="modal-box">


        <div class="modal-icon">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>

        <h2>Unable to Save</h2>

        <p id="errorModalMessage">
            An error occurred while saving the journal.
        </p>

        <div class="modal-actions">

            <button
                type="button"
                class="modal-confirm-btn"
                id="closeErrorModalButton">
                OK
            </button>

        </div>

    </div>


</div>
import * as bootstrap from 'bootstrap';

// Initializes Bootstrap modals
document.addEventListener('DOMContentLoaded', function() {
    let versionModal;
    const modalElement = document.getElementById('versionModal');
    if (modalElement) {
        try {
            versionModal = new bootstrap.Modal(modalElement, {
                backdrop: true,
                keyboard: false,
                focus: true
            });
            window.versionModal = versionModal;
        } catch (error) {
            console.error('Error initializing Bootstrap modal:' + error);
        }
    }

    // Event listener for the "90min" duration option
    const duration90 = document.getElementById('duration90');
    const durationErrorMessage = document.getElementById('durationErrorMessage');
    duration90?.addEventListener('click', function(e) {
        durationErrorMessage.style.display = 'none';
    })

    // Event listener for the "custom" duration option
    const customDuration = document.getElementById('customDuration');
    customDuration?.addEventListener('click', function(e) {
        durationErrorMessage.style.display = 'none';
    })

    // Event listener for the "Next" button
    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'nextBtn') {
            let duration = document.querySelector('input[name="duration"]:checked').value;
            let customDurationValue = parseInt(document.getElementById('customDuration').value) * 60;

            if (duration === 'custom') {
                if (customDurationValue.toString() === 'NaN' || customDurationValue < 1 || customDurationValue > 89 * 60) {
                    durationErrorMessage.style.display = 'block';

                    return;
                } else {
                    durationErrorMessage.style.display = 'none';
                }
            }

            versionModal = document.getElementById('versionModal');
            if (typeof bootstrap !== 'undefined') {
                versionModal = new bootstrap.Modal(document.getElementById('versionModal'));
            }

            // Hide the first modal
            const trainingModalElement = document.getElementById('trainingModal');
            if (trainingModalElement) {
                const trainingModal = bootstrap.Modal.getInstance(trainingModalElement);
                trainingModal.hide();
            }

            if (versionModal) {
                versionModal.show();
            }
        }
    })
});

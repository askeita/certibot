document.addEventListener('DOMContentLoaded', function () {
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    const dropdownButton = document.getElementById('symfonyVersionDropdown');
    const versionModalElement = document.getElementById('versionModal');
    const confirmFetchModalElement = document.getElementById('confirmFetchModal');
    const progressModalElement = document.getElementById('progressModal');
    const fetchYesButton = document.getElementById('fetchYesButton');

    let versionModal, confirmFetchModal, progressModal;
    // Initializes Bootstrap modals
    if (typeof bootstrap !== 'undefined') {
        if (versionModalElement) versionModal = new bootstrap.Modal(versionModalElement);
        if (confirmFetchModalElement) confirmFetchModal = new bootstrap.Modal(confirmFetchModalElement);
        if (progressModalElement) progressModal = new bootstrap.Modal(progressModalElement);
    } else {
        console.error('Bootstrap is not defined. Ensure Bootstrap JS is included.');
    }

    let selectedVersion;
    const hiddenInput = document.getElementById('selectedSymfonyVersion');
    const versionErrorMessage = document.getElementById('versionErrorMessage');
    dropdownItems.forEach(item => {
        // Add click event listener to each dropdown item
        item.addEventListener('click', function (e) {
            e.preventDefault();

            selectedVersion = this.getAttribute('data-value');
            dropdownButton.textContent = `Symfony ${selectedVersion}`;
            hiddenInput.value = selectedVersion;
            versionErrorMessage.style.display = 'none';
        });
    });

    const actionButton = document.getElementById('modalActionButton');
    // Event listener for the action ('See exam topics' OR 'Start training') button in the version modal
    actionButton?.addEventListener('click', function (e) {
        e.preventDefault();

        if (!hiddenInput.value) {
            versionErrorMessage.style.display = 'block';

            return;
        } else {
            versionErrorMessage.style.display = 'none';
        }

        const action = this.getAttribute('data-action');
        // Check if exam topics are available for the selected version
        fetch(`/symfony/${parseInt(selectedVersion, 10)}/check-exam-topics`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    if (action === 'exam-topics') {
                        window.location.href = `/symfony/${parseInt(selectedVersion, 10)}/exam-topics`;
                    }
                } else {
                    document.getElementById('versionPlaceholder').textContent = selectedVersion;
                    versionModal.hide();
                    confirmFetchModal.show();
                }
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation: ' + error);
            });
    })

    // Event listener for the "Yes" button in the confirm fetch modal
    fetchYesButton?.addEventListener('click', function () {
        const version = hiddenInput.value;
        confirmFetchModal.hide();

        document.getElementById('progressVersionPlaceholder').textContent = version;
        progressModal.show();

        const progressCircle = document.getElementById('progressCircle');
        const progressTime = document.getElementById('progressTime');
        const totalTime = 180; // Total time in seconds
        let timeLeft = totalTime;
        const circumference = 2 * Math.PI * 65;
        progressCircle.style.strokeDasharray = circumference;

        const timer = setInterval(() => {
            timeLeft--;
            progressTime.textContent = timeLeft;

            const offset = (timeLeft / totalTime) * circumference;
            progressCircle.style.strokeDashoffset = circumference - offset;

            if (timeLeft <= 0) {
                clearInterval(timer);
            }
        }, 1000);

        // Fetch the command to crawl exam topics for the selected version
        fetch(`/symfony/${parseInt(version, 10)}/execute-crawl-topics-command`)
            .then(response => response.json())
            .then(data => {
                clearInterval(timer);
                progressModal.hide();

                if (data.success) {
                    window.location.href = `/symfony/${parseInt(version, 10)}/exam-topics`;
                } else {
                    alert('An error occurred while retrieving the exam topics.');
                    versionModal.show();
                }
            })
            .catch(error => {
                clearInterval(timer);
                progressModal.hide();
                console.error('There was a problem with the fetch operation: ' + error);
                versionModal.show();
            });
    })

    // Event listener for the version modal
    versionModalElement?.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        let action = null;
        if (typeof button !== 'undefined') {
            action = button.getAttribute('data-action');
        }

        const modalActionButton = document.getElementById('modalActionButton');
        if (action === 'exam-topics') {
            // Set the button text and action for exam topics
            modalActionButton.textContent = 'See Exam Topics';
            modalActionButton.setAttribute('data-action', 'exam-topics');
        } else if (action == null || action === 'start-training') {
            // Set the button text and action for start training
            modalActionButton.textContent = 'Start training';
            modalActionButton.setAttribute('data-action', 'start-training');
            modalActionButton.onclick = function (e) {
                e.preventDefault();

                let customDurationValue = parseInt(document.getElementById('customDuration').value) * 60;
                let duration = document.querySelector('input[name="duration"]:checked').value;

                if (duration === '90') {
                    customDurationValue = 90 * 60;
                }

                if (typeof customDurationValue !== 'undefined' && hiddenInput.value === '') {
                    document.getElementById('versionErrorMessage').style.display = 'block';

                    return;
                }

                window.location.href = `/symfony/${parseInt(selectedVersion, 10)}/quiz?duration=` + customDurationValue.toString();
            }
        }
    })
})

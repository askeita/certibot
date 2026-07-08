document.addEventListener('DOMContentLoaded', function() {
    /**
     * Generation of quiz questions
     * URLs are read from data attributes on #generateYesButton so this function
     * works for any technology (Symfony, PHP, …) without modification.
     */
    const generateYesButton = document.getElementById('generateYesButton');
    const progressCard = document.getElementById('progressCard');
    const progressSteps = document.getElementById('progressSteps');
    const timer = document.getElementById('timer');

    // Read pipeline URLs from data attributes (set by the server-side controller)
    const checkTopicsUrl   = generateYesButton?.getAttribute('data-check-topics-url');
    const crawlTopicsUrl   = generateYesButton?.getAttribute('data-crawl-topics-url');
    const checkLinksUrl    = generateYesButton?.getAttribute('data-check-links-url');
    const crawlDocUrl      = generateYesButton?.getAttribute('data-crawl-doc-url');
    const mcqUrl           = generateYesButton?.getAttribute('data-mcq-url');
    const quizUrl          = generateYesButton?.getAttribute('data-quiz-url');
    const technologyLabel  = generateYesButton?.getAttribute('data-label') || 'the quiz';

    // Adds a step to the progress list
    function addStep(text, status = 'pending') {
        const step = document.createElement('li');
        step.className = 'list-group-item';

        let statusBadge = '';
        if (status === 'pending') {
            statusBadge = '<span class="badge bg-info">In Progress</span>';
        } else if (status === 'success') {
            statusBadge = '<span class="badge bg-success">Completed</span>';
        } else if (status === 'error') {
            statusBadge = '<span class="badge bg-danger">Error</span>';
        }

        step.innerHTML = statusBadge + ' ' + text;
        progressSteps.appendChild(step);
        return step;
    }

    // Updates the status of a step
    function updateStep(stepElement, status) {
        const badges = stepElement.querySelectorAll('.badge');
        badges.forEach(badge => {
            badge.remove();
        });

        let statusBadge = document.createElement('span');
        statusBadge.className = 'badge me-2 ';
        if (status === 'success') {
            statusBadge.className += 'bg-success';
            statusBadge.textContent = 'Completed';
        } else if (status === 'error') {
            statusBadge.className += 'bg-danger';
            statusBadge.textContent = 'Error';
        }

        stepElement.insertBefore(statusBadge, stepElement.firstChild);
    }

    let timerInterval;
    // Starts the timer
    function startGenerationTimer() {
        clearInterval(timerInterval);
        let elapsedTime = 0;

        document.querySelector('#progressCard .text-center span.mt-2').textContent = 'Elapsed time: ';
        timer.textContent = '0:00';

        timerInterval = setInterval(() => {
            elapsedTime++;
            const minutes = Math.floor(elapsedTime / 60);
            const seconds = elapsedTime % 60;

            timer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    }

    // Function to stop the timer
    function stopGenerationTimer() {
        clearInterval(timerInterval);
    }

    // Function to show error message and hide spinner
    function showErrorMessage(errorText) {
        // Make sure progressCard is visible
        if (progressCard) {
            progressCard.classList.remove('d-none');
        }

        // Stop the timer
        stopGenerationTimer();

        // Hide the spinner
        const progressSpinner = document.getElementById('progressSpinner');
        if (progressSpinner) {
            progressSpinner.classList.add('d-none');
        } else {
            // Fallback: try to find spinner by class if ID doesn't exist
            const spinnerByClass = document.querySelector('.spinner-border');
            if (spinnerByClass) {
                spinnerByClass.closest('.text-center').classList.add('d-none');
            }
        }

        // Show the error alert
        const errorAlert = document.getElementById('errorAlert');
        const errorMessage = document.getElementById('errorMessage');

        if (errorAlert && errorMessage) {
            errorMessage.textContent = errorText || 'An unexpected error occurred while processing your request.';
            errorAlert.classList.remove('d-none');
        } else {
            console.error('Could not find errorAlert or errorMessage elements');
        }

        console.error('Quiz generation error:', errorText);
    }

    // Make showErrorMessage globally available for debugging
    window.testErrorMessage = showErrorMessage;

    // Function to generate quiz data
    async function generateQuizData(fetchOnlyTopics) {
        progressCard.classList.remove('d-none');
        startGenerationTimer();

        // Step 1: Checking that topics exist
        const step1 = addStep(`Checking that ${technologyLabel} topics are present in database...`);

        try {
            const topicsResponse = await fetch(checkTopicsUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!topicsResponse.ok) throw new Error(`HTTP error! status: ${topicsResponse.status}`);
            const topicsData = await topicsResponse.json();

            if (!topicsData.exists) {
                step1.innerHTML += ' <span class="text-muted">Not found, crawling topics...</span>';

                const crawlResponse = await fetch(crawlTopicsUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!crawlResponse.ok) throw new Error(`HTTP error! status: ${crawlResponse.status}`);
                const crawlData = await crawlResponse.json();
                if (!crawlData.success) throw new Error(crawlData.error || 'Failed to crawl topics');
                updateStep(step1, 'success');
            } else {
                updateStep(step1, 'success');
                step1.innerHTML += ' <span class="text-muted">Found!</span>';
            }
        } catch (error) {
            updateStep(step1, 'error');
            showErrorMessage(`Failed to check or crawl topics: ${error.message}`);
            return;
        }

        if (fetchOnlyTopics) return { topicsFetched: true };

        // Step 2: Crawl documentation links
        const step2 = addStep(`Exploring the ${technologyLabel} documentation to collect data on topics...`);
        try {
            const linksResponse = await fetch(checkLinksUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!linksResponse.ok) throw new Error(`HTTP error! status: ${linksResponse.status}`);
            const linksData = await linksResponse.json();

            if (!linksData.exists) {
                const docResponse = await fetch(crawlDocUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!docResponse.ok) throw new Error(`HTTP error! status: ${docResponse.status}`);
                const docData = await docResponse.json();
                if (!docData.success) throw new Error(docData.error || 'Failed to crawl documentation');
                updateStep(step2, 'success');
            } else {
                updateStep(step2, 'success');
                step2.innerHTML += ' <span class="text-muted">Found!</span>';
            }
        } catch (error) {
            updateStep(step2, 'error');
            showErrorMessage(`Failed to crawl documentation: ${error.message}`);
            return;
        }

        // Step 3: Generate MCQ questions
        const step3 = addStep(`Preparing the quiz questions...`);
        try {
            const mcqResponse = await fetch(mcqUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!mcqResponse.ok) throw new Error(`HTTP error! status: ${mcqResponse.status}`);
            const mcqData = await mcqResponse.json();
            if (!mcqData.success) throw new Error(mcqData.error || 'Failed to generate quiz questions');

            updateStep(step3, 'success');
            stopGenerationTimer();
            setTimeout(() => { window.location.href = quizUrl; }, 3000);
        } catch (error) {
            updateStep(step3, 'error');
            showErrorMessage(`Failed to generate quiz questions: ${error.message}`);
        }
    }

    // Generate quiz data when the user clicks "Yes"
    generateYesButton?.addEventListener('click', function () {
        generateYesButton.disabled = true;
        generateQuizData(false).catch(error => {
            console.error('Error in generateQuizData:', error);
            showErrorMessage(`Network error: ${error.message}`);
        });
        startGenerationTimer();
    })

    // Fetches only topics (legacy support)
    const fetchTopicsYesButton = document.getElementById('fetchTopicsYesButton');

    fetchTopicsYesButton?.addEventListener('click', function () {
        try {
            fetchTopicsYesButton.disabled = true;
            generateQuizData(true).catch(error => {
                console.error('Error in generateQuizData promise:', error);
                showErrorMessage(`Network error: ${error.message}`);
            });
        } catch (error) {
            console.error('Error in fetchTopicsYesButton listener:', error);
            showErrorMessage(`Error: ${error.message}`);
        }
    });

    /**
     * Quiz:
     *      - timer: display countdown and save
     *      - previous button
     *      - next button
     *      - save responses
     *      - finish button
     */
    let timerElement = document.getElementById('timer');
    if (!timerElement) return;

    // Read endpoint URLs from the hidden config element injected by the controller
    const quizConfig = document.getElementById('quiz-config');
    const saveTimerUrl    = quizConfig?.getAttribute('data-save-timer-url');
    const saveResponseUrl = quizConfig?.getAttribute('data-save-response-url');

    let timeLeft = parseInt(timerElement.textContent, 10);
    timerElement.textContent = formatTime(timeLeft);

    // Formats time in HH:MM:SS
    function formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    // Quiz timer interval
    const quizTimerInterval = setInterval(function () {
        if (timeLeft <= 0) {
            clearInterval(quizTimerInterval);
            timerElement.textContent = '00:00:00';
            timerElement.parentElement.classList.add('text-danger');
        } else {
            timeLeft--;
            timerElement.textContent = formatTime(timeLeft);
        }
        saveQuizTimer();
    }, 1000)


    // saves periodically remaining time
    function saveQuizTimer() {
        if (!saveTimerUrl) return;
        fetch(saveTimerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'timeLeft=' + timeLeft
        }).then(response => response.json())
            .catch(error => console.error('Error saving timer:', error));
    }

    // Saves user's responses
    function saveResponses() {
        if (!saveResponseUrl) return;
        const form = document.getElementById('quizForm');
        const formData = new FormData(form);
        let postData = {};
        for (let pair of formData.entries()) {
            postData[pair[0]] = pair[1];
        }

        fetch(saveResponseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'x-csrf-token': document.getElementById('quiz__token').value,
            },
            credentials: "include",
            body: 'formData=' + JSON.stringify(postData)
        }).then(response => response.json())
            .catch(error => console.error('Error saving response:', error));
    }

    /**
     * Safely redirects to a URL only if it resolves to a same-origin path.
     * Prevents DOM-based XSS/open-redirect behavior by rejecting javascript: URIs,
     * external URLs, protocol-relative URLs and malformed inputs.
     *
     * @param {string|null} url - The URL to redirect to.
     */
    function safeRedirect(url) {
        if (typeof url !== 'string' || url.trim() === '') {
            return;
        }

        try {
            const parsed = new URL(url, window.location.origin);

            // Only allow same-origin destinations.
            if (parsed.origin !== window.location.origin) {
                return;
            }

            // Enforce application-relative navigation and reject backslash tricks.
            if (!parsed.pathname.startsWith('/') || /\\/.test(url)) {
                return;
            }

            // Strictly validate URL components before composing a relative redirect target.
            if (!/^\/[A-Za-z0-9\-._~!$&'()*+,;=:@/%]*$/.test(parsed.pathname)) {
                return;
            }
            if (parsed.search && !/^\?[A-Za-z0-9\-._~!$&'()*+,;=:@/?%]*$/.test(parsed.search)) {
                return;
            }
            if (parsed.hash && !/^#[A-Za-z0-9\-._~!$&'()*+,;=:@/?%]*$/.test(parsed.hash)) {
                return;
            }

            const safePath = `${parsed.pathname}${parsed.search}${parsed.hash}`;
            window.location.assign(safePath);
        } catch (e) {
            // Ignore invalid URLs.
        }
    }

    // Previous and next navigation with timer consideration
    document.getElementById('prevButton')?.addEventListener('click', function (e) {
        if (this.classList.contains('disabled')) {
            return;
        }
        e.preventDefault();

        saveQuizTimer();
        saveResponses();
        safeRedirect(this.getAttribute('data-url'));
    });

    // Next button to save responses and redirect to the next question
    document.getElementById('nextButton')?.addEventListener('click', function (e) {
        e.preventDefault();

        saveQuizTimer();
        saveResponses();
        safeRedirect(this.getAttribute('data-url'));
    })

    // Finish button to save responses and redirect to result page
    document.getElementById('finishButton')?.addEventListener('click', function (e) {
        e.preventDefault();

        saveResponses();
        safeRedirect(this.getAttribute('data-url'));
    })

    // Confirmation before leaving the page
    window.addEventListener('beforeunload', function (e) {
        //e.preventDefault();
        // ask confirmation when quiz generation is running
    })

});

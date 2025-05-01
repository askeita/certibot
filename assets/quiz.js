document.addEventListener('DOMContentLoaded', function() {
    /**
     * Generation of quiz questions
     */
    const generateYesButton = document.getElementById('generateYesButton');
    const progressCard = document.getElementById('progressCard');
    const progressSteps = document.getElementById('progressSteps');
    const timer = document.getElementById('timer');
    const selectedVersion = generateYesButton?.getAttribute('data-version');

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

    // Function to generate quiz data
    async function generateQuizData(fetchOnlyExamTopics, selectedVersion) {
        progressCard.classList.remove('d-none');
        startGenerationTimer();

        // Step 1: Checking that exam topics exist
        const step1 = addStep(`Checking that Symfony ${parseInt(selectedVersion, 10)} exam topics are present in database...`);

        try {
            const examTopicsResponse = await fetch(`/symfony/${parseInt(selectedVersion, 10)}/check-exam-topics`);
            const examTopicsData = await examTopicsResponse.json();

            if (!examTopicsData.exists) {
                //updateStep(step1, 'success');
                step1.innerHTML += ' <span class="text-muted">No exam topics found, crawling topics...</span>';

                const crawlResponse = await fetch(`/symfony/${parseInt(selectedVersion, 10)}/execute-crawl-topics-command`);
                const crawlData = await crawlResponse.json();

                if (!crawlData.success) {
                    throw new Error(crawlData.error || 'Failed to crawl exam topics');
                }
                updateStep(step1, 'success');
            } else {
                updateStep(step1, 'success');
                step1.innerHTML += ' <span class="text-muted">Found!</span>';
            }
        } catch (error) {
            console.error('Error when checking exam topics: ' + error);
            updateStep(step1, 'error');

            return;
        }

        // Case when the user only wants to fetch exam topics
        if (fetchOnlyExamTopics) return new JSON.parse('{ "topicsFetched": "true" }');

        // Step 2: Crawl Symfony documentation
        const step2 = addStep(`Exploring the Symfony ${selectedVersion} documentation to collect data on exam topics...`);
        try {
            const topicsLinksResponse = await fetch(`/symfony/${parseInt(selectedVersion, 10)}/check-topics-links`);
            const topicsLinksData = await topicsLinksResponse.json();

            if (!topicsLinksData.exists) {
                const docResponse = await fetch(`/symfony/${parseInt(selectedVersion, 10)}/execute-crawl-doc-command`);
                const docData = await docResponse.json();

                if (!docData.success) {
                    throw new Error(docData.error || 'Failed to crawl Symfony documentation');
                }

                updateStep(step2, 'success');
            } else {
                updateStep(step2, 'success');
                step2.innerHTML += ' <span class="text-muted">Found!</span>';
            }
        } catch (error) {
            console.error('Error when crawling Symfony documentation: ' + error);
            updateStep(step2, 'error');
            return;
        }

        // Step 3: Preparation of the quiz questions
        const step3 = addStep(`Preparing the quiz questions...`);
        try {
            const mcqResponse = await fetch(`/symfony/${parseInt(selectedVersion, 10)}/execute-mcq-command`);
            const mcqData = await mcqResponse.json();

            if (!mcqData.success) {
                throw new Error(mcqData.error || 'Failed to generate quiz questions');
            }

            updateStep(step3, 'success');

            // Redirection to quiz interface
            stopGenerationTimer();
            setTimeout(() => {
                window.location.href = `/symfony/${parseInt(selectedVersion, 10)}/quiz`
            }, 10000)
        } catch (error) {
            console.error('Error when generating quiz: ' + error);
            addStep(`Error: ${error.message}`, 'error');
            stopGenerationTimer();
        }
    }

    // Generate quiz data when the user clicks "Yes" in the modal
    generateYesButton?.addEventListener('click', function () {
        generateYesButton.disabled = true;
        generateQuizData(false, generateYesButton.getAttribute('data-version'))
            .then(response => response.json())
            .catch(error => console.error('Error in sending the response:', error));
        startGenerationTimer();
    })

    // Fetches only exam topics
    const fetchTopicsYesButton = document.getElementById('fetchTopicsYesButton');
    fetchTopicsYesButton?.addEventListener('click', function () {
        fetchTopicsYesButton.disabled = true;
        generateQuizData(true, fetchTopicsYesButton.getAttribute('data-version'))
            .then(response => response.json())
            .catch(error => console.error('Error in sending the response:', error));
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
        fetch(`/symfony/${parseInt(selectedVersion, 10)}/quiz/save-timer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'timeLeft=' + timeLeft
        }).then(response => response.json())
            .catch(error => console.error('Error in sending the response:', error));
    }

    // Saves user's responses
    function saveResponses() {
        const form = document.getElementById('quizForm');
        const formData = new FormData(form);
        let postData = {};
        for (let pair of formData.entries()) {
            postData[pair[0]] = pair[1];
        }

        fetch(`/symfony/${parseInt(selectedVersion, 10)}/quiz/save-response`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'x-csrf-token': document.getElementById('quiz__token').value,
            },
            credentials: "include",
            body: 'formData=' + JSON.stringify(postData)
        }).then(response => response.json())
            .catch(error => console.error('Error in sending the response:', error));
    }

    // Previous and next navigation with timer consideration
    document.getElementById('prevButton')?.addEventListener('click', function (e) {
        if (this.classList.contains('disabled')) {
            return;
        }
        e.preventDefault();

        saveQuizTimer();
        saveResponses();
        window.location.href = this.getAttribute('data-url');
    });

    // Next button to save responses and redirect to the next question
    document.getElementById('nextButton')?.addEventListener('click', function (e) {
        e.preventDefault();

        saveQuizTimer();
        saveResponses();
        window.location.href = this.getAttribute('data-url');
    })

    // Finish button to save responses and redirect to result page
    document.getElementById('finishButton')?.addEventListener('click', function (e) {
        e.preventDefault();

        saveResponses();
        window.location.href = this.getAttribute('data-url');
    })

    // Confirmation before leaving the page
    window.addEventListener('beforeunload', function (e) {
        //e.preventDefault();
        // ask confirmation when quiz generation is running
    })

});
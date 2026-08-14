/**
 * php-index.js — Handles the PHP quiz training modal.
 *
 * Unlike the Symfony flow (duration → version → quiz), PHP has no version.
 * This script simply picks a duration and navigates directly to /php/quiz.
 */
const bootstrap = window.bootstrap;

document.addEventListener('DOMContentLoaded', function () {
    const phpStartBtn = document.getElementById('phpStartQuizBtn');
    const phpDurationError = document.getElementById('phpDurationErrorMessage');

    phpStartBtn?.addEventListener('click', function () {
        const durationRadio = document.querySelector('input[name="phpDuration"]:checked');
        if (!durationRadio) {
            phpDurationError.style.display = 'block';
            return;
        }

        let durationSeconds;
        if (durationRadio.value === 'custom') {
            const customMinutes = parseInt(document.getElementById('phpCustomDuration').value, 10);
            if (isNaN(customMinutes) || customMinutes < 1 || customMinutes > 89) {
                phpDurationError.style.display = 'block';
                return;
            }
            durationSeconds = customMinutes * 60;
        } else {
            durationSeconds = parseInt(durationRadio.value, 10) * 60;
        }

        phpDurationError.style.display = 'none';

        // Close the modal before navigating
        const modalEl = document.getElementById('phpTrainingModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        window.location.href = `/php/quiz?duration=${durationSeconds}`;
    });
});


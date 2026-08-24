<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * QuizSessionService — Single Responsibility: quiz session logic.
 *
 * Extracted from QuizController to respect SRP. This service handles all
 * quiz state management (navigation, timer, scoring, session cleanup) and
 * is shared by both SymfonyQuizController and PhpQuizController, avoiding duplication.
 */
class QuizSessionService
{
    /**
     * Handles previous/next navigation and updates the current question index in session.
     *
     * @return int The (potentially updated) question index.
     */
    public function handleNavigation(Request $request, SessionInterface $session): int
    {
        $next = $request->query->get('next', false);
        $prev = $request->query->get('prev', false);
        $questionIndex = $session->get('questionIndex', 0);

        if ($next) {
            $questionIndex++;
        } elseif ($prev) {
            $questionIndex = max(0, $questionIndex - 1);
        }

        $session->set('questionIndex', $questionIndex);

        return $questionIndex;
    }

    /**
     * Selects and shuffles a subset of questions proportional to the session duration.
     *
     * The formula mirrors the real Symfony certification: 90 questions in 90 minutes.
     * For shorter durations, fewer questions are selected proportionally.
     *
     * @param array $allQuestions Full question pool from the database.
     * @param int   $duration     Quiz duration in seconds.
     * @return array              Reduced and shuffled question set.
     */
    public function prepareQuestions(array $allQuestions, int $duration): array
    {
        $totalAvailable = count($allQuestions);
        if ($totalAvailable === 0) {
            return [];
        }

        $totalQuestions = max(1, (int) round($duration / 60, 0, PHP_ROUND_HALF_UP));
        $totalQuestions = min($totalQuestions, $totalAvailable);
        shuffle($allQuestions);
        $questions = array_slice($allQuestions, 0, $totalQuestions);
        return $questions;
    }

    /**
     * Compares user responses against correct answers and builds the results array.
     *
     * @param array $questions     Questions shown during the quiz.
     * @param array $userResponses Answers submitted by the user (indexed by question position).
     * @return array               Tuple [results[], correctAnswerCount].
     */
    public function calculateResults(array $questions, array $userResponses): array
    {
        $correctAnswers = 0;
        $results = [];

        foreach ($questions as $index => $question) {
            $correctOption = null;
            if (preg_match('/[A-D]/', $question['answer'], $match)) {
                $correctOption = $match[0];
            }

            $userChoice = $userResponses[$index] ?? [];
            $isCorrect = $correctOption === $userChoice;
            if ($isCorrect) {
                $correctAnswers++;
            }

            $results[] = [
                'question'      => $question['question'],
                'userChoice'    => $userChoice,
                'correctAnswer' => $correctOption,
                'isCorrect'     => $isCorrect,
                'explanation'   => $question['link'],
            ];
        }

        return [$results, $correctAnswers];
    }

    /**
     * Returns the remaining time for the current question, persisting timer state.
     *
     * @param SessionInterface $session
     * @param int              $questionIndex Current question index.
     * @param int              $duration      Default duration when timer is not yet initialized.
     * @return int                            Remaining time in seconds.
     */
    public function handleTimer(SessionInterface $session, int $questionIndex, int $duration): int
    {
        $questionTimers = $session->get('questionTimers', []);
        $questionTimers[$questionIndex] = $questionTimers[$questionIndex] ?? $duration;
        $session->set('questionTimers', $questionTimers);

        return (int) $questionTimers[$questionIndex];
    }

    /**
     * Clears quiz-related session data at the end of a quiz.
     */
    public function clearSession(SessionInterface $session): void
    {
        $session->remove('questionIndex');
        $session->remove('questionTimers');
        $session->remove('questionTimer');
        $session->remove('timeLeft');
        $session->remove('duration');
        $session->remove('userResponses');
        $session->remove('quizQuestions');
    }

    /**
     * Normalises choices from a question, handling both array and legacy string formats.
     *
     * @param array $currentQuestion
     * @return string[]
     */
    public function prepareChoices(array $currentQuestion): array
    {
        if (is_array($currentQuestion['choices'])) {
            return $currentQuestion['choices'];
        }

        if (is_string($currentQuestion['choices'])) {
            return explode('?', $currentQuestion['choices'], 4);
        }

        return [];
    }

    /**
     * Converts raw choices into the format expected by the QuizType form.
     *
     * @param string[] $choices
     * @return array<array{text: string}>
     */
    public function prepareFormChoices(array $choices): array
    {
        $formChoices = [];
        foreach ($choices as $choice) {
            $formChoices[] = ['text' => preg_replace('/[A-D]\)\s*/', '', $choice)];
        }

        return $formChoices;
    }
}


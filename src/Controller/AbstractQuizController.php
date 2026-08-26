<?php

namespace App\Controller;

use App\Form\QuizType;
use App\Service\QuizSessionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * AbstractQuizController centralises the full quiz flow (navigation, timer,
 * scoring, session persistence) shared by every technology-specific quiz
 * controller (SymfonyQuizController, PhpQuizController, ...).
 *
 * Concrete controllers only provide the question data source and the route
 * names/params specific to their technology (e.g. the {version} segment for
 * Symfony). All business logic lives here and in QuizSessionService, so
 * adding a new technology quiz never requires duplicating this flow.
 */
abstract class AbstractQuizController extends AbstractController
{
    protected const int DEFAULT_DURATION = 5400; // 90 minutes

    /** Extra route parameters (e.g. ['version' => 7]) shared by every generated URL. */
    protected array $routeParams = [];

    public function __construct(
        protected readonly QuizSessionService $quizSessionService,
    ) {
    }

    /** Full question pool for the current technology. */
    abstract protected function getQuizData(): array;

    /** Human readable technology name shown on the "no quiz found" page. */
    abstract protected function getTechnologyLabel(): string;

    abstract protected function getQuizRouteName(): string;
    abstract protected function getSaveTimerRouteName(): string;
    abstract protected function getSaveResponseRouteName(): string;
    abstract protected function getFinishRouteName(): string;
    abstract protected function getCheckTopicsRouteName(): string;
    abstract protected function getCrawlTopicsRouteName(): string;
    abstract protected function getCheckLinksRouteName(): string;
    abstract protected function getCrawlDocRouteName(): string;
    abstract protected function getMcqRouteName(): string;

    /**
     * Renders the current question, preparing (once) and persisting the
     * question set in session so "previous"/"next" never reshuffle it.
     */
    protected function doIndex(Request $request, SessionInterface $session): Response
    {
        $questionIndex = $this->quizSessionService->handleNavigation($request, $session);

        if (!$session->has('userResponses')) {
            $session->set('userResponses', []);
        }

        $quizData = $this->getQuizData();

        $duration = static::DEFAULT_DURATION;
        if ($session->has('duration')) {
            $duration = $session->get('duration');
        }
        if ($request->query->has('duration')) {
            $duration = (int) $request->query->get('duration');
            $session->set('duration', $duration);
        }

        if (empty($quizData) || !isset($quizData[0]['mcq'])) {
            return $this->render('quiz/no_quiz_found.html.twig', [
                'technologyLabel' => $this->getTechnologyLabel(),
                'checkTopicsUrl'  => $this->generateUrl($this->getCheckTopicsRouteName(), $this->routeParams),
                'crawlTopicsUrl'  => $this->generateUrl($this->getCrawlTopicsRouteName(), $this->routeParams),
                'checkLinksUrl'   => $this->generateUrl($this->getCheckLinksRouteName(), $this->routeParams),
                'crawlDocUrl'     => $this->generateUrl($this->getCrawlDocRouteName(), $this->routeParams),
                'mcqUrl'          => $this->generateUrl($this->getMcqRouteName(), $this->routeParams),
                'quizUrl'         => $this->generateUrl($this->getQuizRouteName(), $this->routeParams),
            ]);
        }

        // Prepare the question set only once per quiz attempt and reuse it
        // for every "previous"/"next" navigation (stored in session).
        if (!$session->has('quizQuestions')) {
            $questions = $this->quizSessionService->prepareQuestions($quizData[0]['mcq'], $duration);
            $session->set('quizQuestions', $questions);
        } else {
            $questions = $session->get('quizQuestions');
        }

        $totalQuestions = count($questions);
        if ($questionIndex >= $totalQuestions) {
            $questionIndex = 0;
            $session->set('questionIndex', $questionIndex);
        }

        $timerDuration = $this->quizSessionService->handleTimer($session, $questionIndex, $duration);
        $currentQuestion = $questions[$questionIndex];
        $choices = $this->quizSessionService->prepareChoices($currentQuestion);
        $formChoices = $this->quizSessionService->prepareFormChoices($choices);

        $userResponses = $session->get('userResponses', []);
        $userResponse = $userResponses[$questionIndex] ?? [];
        $form = $this->createForm(QuizType::class, null, ['choices' => $formChoices]);
        $progressPercentage = ($questionIndex / max(1, $totalQuestions - 1)) * 100;

        return $this->render('quiz/quiz.html.twig', array_merge($this->routeParams, [
            'form'               => $form->createView(),
            'question'           => $currentQuestion['question'],
            'answer'             => $currentQuestion['answer'],
            'link'               => $currentQuestion['link'],
            'choices'            => $choices,
            'userResponse'       => $userResponse,
            'questionIndex'      => $questionIndex,
            'totalQuestions'     => $totalQuestions,
            'timerDuration'      => $timerDuration,
            'progressPercentage' => $progressPercentage,
            'isLastQuestion'     => ($questionIndex == $totalQuestions - 1),
            'saveTimerUrl'       => $this->generateUrl($this->getSaveTimerRouteName(), $this->routeParams),
            'saveResponseUrl'    => $this->generateUrl($this->getSaveResponseRouteName(), $this->routeParams),
            'prevUrl'            => $this->generateUrl($this->getQuizRouteName(), array_merge($this->routeParams, ['prev' => 1])),
            'nextUrl'            => $this->generateUrl($this->getQuizRouteName(), array_merge($this->routeParams, ['next' => 1])),
            'finishUrl'          => $this->generateUrl($this->getFinishRouteName(), $this->routeParams),
        ]));
    }

    /** Persists the remaining time in session. */
    protected function doSaveTimer(Request $request, SessionInterface $session): JsonResponse
    {
        $timeLeft = (int) $request->request->get('timeLeft');
        $questionIndex = $session->get('questionIndex', 0);

        $questionTimers = $session->get('questionTimers', []);
        $questionTimers[$questionIndex] = $timeLeft;
        $session->set('questionTimers', $questionTimers);
        $session->set('timeLeft', $timeLeft);

        return new JsonResponse(['success' => true]);
    }

    /** Persists the user's selected answer in session. */
    protected function doSaveResponse(Request $request, SessionInterface $session): JsonResponse
    {
        $formDataString = $request->request->get('formData');
        $formData = json_decode($formDataString, true);
        $questionIndex = $session->get('questionIndex', 0);
        $selectedChoice = $formData['quiz[selectChoices]'] ?? null;

        $userResponses = $session->get('userResponses', []);
        $userResponses[$questionIndex] = $selectedChoice;
        $session->set('userResponses', $userResponses);

        return new JsonResponse(['success' => true]);
    }

    /** Scores the quiz, using the question set stored in session, then renders the results page. */
    protected function doFinishQuiz(SessionInterface $session): Response
    {
        $questions = $session->get('quizQuestions', []);
        $totalQuestions = count($questions);

        $userResponses = $session->get('userResponses', []);
        [$results, $correctAnswers] = $this->quizSessionService->calculateResults($questions, $userResponses);
        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        $this->quizSessionService->clearSession($session);

        return $this->render('quiz/results.html.twig', array_merge($this->routeParams, [
            'score'          => $score,
            'correctAnswers' => $correctAnswers,
            'totalQuestions' => $totalQuestions,
            'results'        => $results,
            'restartUrl'     => $this->generateUrl($this->getQuizRouteName(), $this->routeParams),
        ]));
    }

    /** Checks whether a given MongoDB collection exists in a database. */
    protected function checkCollectionExists(DocumentManager $dm, string $dbName, string $collectionName): JsonResponse
    {
        try {
            $database = $dm->getClient()->selectDatabase($dbName);
            $exists = false;
            foreach ($database->listCollections() as $col) {
                if ($col->getName() === $collectionName) {
                    $exists = true;
                    break;
                }
            }

            return $this->json(['exists' => $exists]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Database error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}


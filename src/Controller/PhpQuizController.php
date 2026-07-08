<?php

namespace App\Controller;

use App\Form\QuizType;
use App\Repository\MongoDBQueryBuilder;
use App\Service\QuizSessionService;
use App\Technology\Php\PhpManualDocSource;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


/**
 * PhpQuizController — quiz for the PHP manual.
 *
 * Shares all session/quiz logic with QuizController via QuizSessionService.
 * Uses a dedicated MongoDBQueryBuilder pointing to the 'php_manual' database,
 * showing that adding a new technology only requires a new controller + DocSource,
 * with zero changes to the core quiz engine.
 *
 * PHP has no certification version, so routes do not include a version segment.
 */
#[Route('/php')]
class PhpQuizController extends AbstractController
{
    private const DEFAULT_DURATION = 5400; // 90 minutes

    public function __construct(
        #[Autowire(service: 'App\Repository\MongoDBQueryBuilder.php_mcq_gpt-4o')]
        private readonly MongoDBQueryBuilder $mcqQueryBuilder,
        private readonly QuizSessionService $quizSessionService,
        private readonly PhpManualDocSource $phpDocSource,
    ) {
    }

    /**
     * Main quiz page.
     */
    #[Route('/quiz', name: 'app_php_quiz')]
    public function index(Request $request, SessionInterface $session): Response
    {
        $questionIndex = $this->quizSessionService->handleNavigation($request, $session);

        if (!$session->has('userResponses')) {
            $session->set('userResponses', []);
        }

        $quizData = $this->getQuizData();

        $duration = self::DEFAULT_DURATION;
        if ($session->has('duration')) {
            $duration = $session->get('duration');
        }
        if ($request->query->has('duration')) {
            $duration = (int) $request->query->get('duration');
            $session->set('duration', $duration);
        }

        if (empty($quizData) || !isset($quizData[0]['mcq'])) {
            return $this->render('quiz/no_quiz_found.html.twig', [
                'technologyLabel' => 'PHP',
                'checkTopicsUrl'  => $this->generateUrl('app_php_check_topics_collection'),
                'crawlTopicsUrl'  => $this->generateUrl('app_php_execute_crawl_topics_command'),
                'checkLinksUrl'   => $this->generateUrl('app_php_check_topics_links'),
                'crawlDocUrl'     => $this->generateUrl('app_php_execute_crawl_doc_command'),
                'mcqUrl'          => $this->generateUrl('app_php_execute_mcq_command'),
                'quizUrl'         => $this->generateUrl('app_php_quiz'),
            ]);
        }

        $questions = $this->quizSessionService->prepareQuestions($quizData[0]['mcq'], $duration);
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

        return $this->render('quiz/quiz.html.twig', [
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
            'saveTimerUrl'       => $this->generateUrl('app_php_quiz_save_timer'),
            'saveResponseUrl'    => $this->generateUrl('app_php_quiz_save_response'),
            'prevUrl'            => $this->generateUrl('app_php_quiz', ['prev' => 1]),
            'nextUrl'            => $this->generateUrl('app_php_quiz', ['next' => 1]),
            'finishUrl'          => $this->generateUrl('app_php_quiz_finish'),
        ]);
    }

    /**
     * Persists the remaining time in session.
     */
    #[Route('/quiz/save-timer', name: 'app_php_quiz_save_timer', methods: ['POST'])]
    public function saveTimer(Request $request, SessionInterface $session): JsonResponse
    {
        $timeLeft = (int) $request->request->get('timeLeft');
        $questionIndex = $session->get('questionIndex', 0);

        $questionTimers = $session->get('questionTimers', []);
        $questionTimers[$questionIndex] = $timeLeft;
        $session->set('questionTimers', $questionTimers);
        $session->set('timeLeft', $timeLeft);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Persists user's selected answer in session.
     */
    #[Route('/quiz/save-response', name: 'app_php_quiz_save_response', methods: ['POST'])]
    public function saveResponse(Request $request, SessionInterface $session): JsonResponse
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

    /**
     * Scores the quiz and renders the results page.
     */
    #[Route('/quiz/finish', name: 'app_php_quiz_finish')]
    public function finishQuiz(SessionInterface $session): Response
    {
        $allQuestions = $this->getQuizData()[0]['mcq'] ?? [];
        $duration = $session->get('duration', self::DEFAULT_DURATION);
        $questions = $this->quizSessionService->prepareQuestions($allQuestions, $duration);
        $totalQuestions = count($questions);

        $userResponses = $session->get('userResponses', []);
        [$results, $correctAnswers] = $this->quizSessionService->calculateResults($questions, $userResponses);
        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        $this->quizSessionService->clearSession($session);

        return $this->render('quiz/results.html.twig', [
            'score'          => $score,
            'correctAnswers' => $correctAnswers,
            'totalQuestions' => $totalQuestions,
            'results'        => $results,
            'restartUrl'     => $this->generateUrl('app_php_quiz'),
        ]);
    }

    /**
     * Checks if the PHP topics collection exists.
     */
    #[Route('/check-topics-collection', name: 'app_php_check_topics_collection', methods: ['GET'])]
    public function checkTopicsCollection(DocumentManager $documentManager): JsonResponse
    {
        return $this->checkCollectionExists(
            $documentManager,
            $this->phpDocSource->getDatabaseName(),
            $this->phpDocSource->getTopicsCollectionName()
        );
    }

    /**
     * Checks if the PHP topics-links collection exists.
     */
    #[Route('/check-topics-links', name: 'app_php_check_topics_links', methods: ['GET'])]
    public function checkTopicsLinks(DocumentManager $documentManager): JsonResponse
    {
        return $this->checkCollectionExists(
            $documentManager,
            $this->phpDocSource->getDatabaseName(),
            $this->phpDocSource->getLinksCollectionName()
        );
    }

    /**
     * Checks if the PHP MCQ collection exists.
     */
    #[Route('/check-mcq-collection', name: 'app_php_check_mcq_collection', methods: ['GET'])]
    public function checkMcqCollection(DocumentManager $documentManager): JsonResponse
    {
        return $this->checkCollectionExists(
            $documentManager,
            $this->phpDocSource->getDatabaseName(),
            $this->phpDocSource->getMcqCollectionName()
        );
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function getQuizData(): array
    {
        return json_decode(json_encode(
            $this->mcqQueryBuilder
                ->selectCollection($this->phpDocSource->getMcqCollectionName())
                ->find(null)
                ->toArray()
        ), true);
    }

    private function checkCollectionExists(DocumentManager $dm, string $dbName, string $collectionName): JsonResponse
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


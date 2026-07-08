<?php

namespace App\Controller;

use App\Form\QuizType;
use App\Repository\MongoDBQueryBuilder;
use App\Service\QuizSessionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


/**
 * QuizController handles the Symfony certification quiz.
 *
 * Business logic (navigation, scoring, timer, session) is delegated to QuizSessionService
 * following the Single Responsibility Principle. Templates receive computed URLs so they
 * remain technology-agnostic and reusable by PhpQuizController.
 */
#[Route('/symfony')]
class QuizController extends AbstractController
{
    /**
     * @var MongoDBQueryBuilder     MongoDB query builder
     */
    private MongoDBQueryBuilder $mcqQueryBuilder;

    /**
     * Constructor
     *
     * @param MongoDBQueryBuilder $mcqQueryBuilder
     */
    public function __construct(
        #[Autowire(service: 'App\Repository\MongoDBQueryBuilder.mcq_gpt-4o')]
        MongoDBQueryBuilder $mcqQueryBuilder,
        private readonly LoggerInterface $logger,
        private readonly QuizSessionService $quizSessionService
    )
    {
        $this->mcqQueryBuilder = $mcqQueryBuilder;
    }

    /**
     * Index
     *
     * @param Request $request
     * @param SessionInterface $session
     * @param int $version
     * @return Response
     */
    #[Route('/{version}/quiz', name: 'app_quiz')]
    public function index(Request $request, SessionInterface $session, int $version): Response
    {
        $questionIndex = $this->quizSessionService->handleNavigation($request, $session);

        if (!$session->has('userResponses')) {
            $session->set('userResponses', []);
        }

        $quizData = $this->getQuizData($version);

        $duration = 5400;
        if ($session->has('duration')) {
            $duration = $session->get('duration');
        }
        if ($request->query->has('duration')) {
            $duration = (int) $request->query->get('duration');
            $session->set('duration', $duration);
        }

        if (empty($quizData) || !isset($quizData[0]['mcq'])) {
            return $this->render('quiz/no_quiz_found.html.twig', [
                'technologyLabel'  => "Symfony {$version}",
                'checkTopicsUrl'   => $this->generateUrl('app_check_exam_topics', ['version' => $version]),
                'crawlTopicsUrl'   => $this->generateUrl('app_execute_crawl_topics_command', ['version' => $version]),
                'checkLinksUrl'    => $this->generateUrl('app_check_topics_links', ['version' => $version]),
                'crawlDocUrl'      => $this->generateUrl('app_execute_crawl_doc_command', ['version' => $version]),
                'mcqUrl'           => $this->generateUrl('app_execute_mcq_command', ['version' => $version]),
                'quizUrl'          => $this->generateUrl('app_quiz', ['version' => $version]),
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
            'version'            => $version,
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
            'saveTimerUrl'       => $this->generateUrl('app_quiz_save_timer', ['version' => $version]),
            'saveResponseUrl'    => $this->generateUrl('app_quiz_save_response', ['version' => $version]),
            'prevUrl'            => $this->generateUrl('app_quiz', ['prev' => 1, 'version' => $version]),
            'nextUrl'            => $this->generateUrl('app_quiz', ['next' => 1, 'version' => $version]),
            'finishUrl'          => $this->generateUrl('app_quiz_finish', ['version' => $version]),
        ]);
    }

    /**
     * Handles navigation between questions
     *
     * @param Request $request Request object
     * @param SessionInterface $session Session object
     * @return int                          question index
     */
    private function handleNavigation(Request $request, SessionInterface $session): int
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
     * Retrieves quiz data
     *
     * @param int $version Symfony version
     * @return array        array of quiz data
     */
    private function getQuizData(int $version): array
    {
        return json_decode(json_encode(
            $this->mcqQueryBuilder
                ->selectCollection("sf{$version}_mcq_gpt-4o")
                ->find(null)
                ->toArray()
        ), true);
    }

    /**
     * Prepares questions for the quiz
     *
     * @param array $allQuestions array of all questions
     * @param int $duration duration in seconds
     * @return array                reduced and shuffled array of all questions
     */
    private function prepareQuestions(array $allQuestions, int $duration): array
    {
        $totalQuestions = round(($duration/60) * 90 / count($allQuestions), 0, PHP_ROUND_HALF_UP);
        $questions = array_splice($allQuestions, 0, $totalQuestions);
        shuffle($questions);

        return $questions;
    }

    /**
     * Handles timer for questions
     *
     * @param SessionInterface $session Session object
     * @param int $questionIndex question index
     * @param int|null $duration duration in seconds
     * @return int                          timer duration
     */
    private function handleTimer(SessionInterface $session, int $questionIndex, ?int $duration): int
    {
        $questionTimer = $session->get('questionTimer', []);
        if (!isset($questionTimer[$questionIndex])) {
            $questionTimer[$questionIndex] = $duration;
        }

        $session->set('questionTimer', $questionTimer);
        if ($session->get('timeLeft') !== null) {
            return $session->get('timeLeft');
        }

        return $questionTimer[$questionIndex];
    }

    /**
     * Prepares choices from current question
     *
     * @param array $currentQuestion    current question data
     * @return array                    current question choices
     */
    private function prepareChoices(array $currentQuestion): array
    {
        if (is_array($currentQuestion["choices"])) {
            return $currentQuestion["choices"];
        }

        if (is_string($currentQuestion["choices"])) {
            return explode("?", $currentQuestion["choices"], 4);
        }

        return [];
    }

    /**
     * Prepares form choices
     *
     * @param array $choices choices from current question
     * @return array         choices for form
     */
    private function prepareFormChoices(array $choices): array
    {
        $formChoices = [];

        foreach ($choices as $choice) {
            $formChoices[] = ["text" => preg_replace("/[A-D]\)\s*/", "", $choice)];
        }

        return $formChoices;
    }

    /**
     * Saves timer
     *
     * @param Request $request          Request object
     * @param SessionInterface $session Session object
     * @return JsonResponse             JSON response
     */
    #[Route('/{version}/quiz/save-timer', name: 'app_quiz_save_timer', methods: ['POST'])]
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
     * Saves user's responses
     *
     * @param Request $request          Request object
     * @param SessionInterface $session Session object
     * @return JsonResponse             Json response
     */
    #[Route('/{version}/quiz/save-response', name: 'app_quiz_save_response', methods: ['POST'])]
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
     * Completes quiz and displays results
     *
     * @param SessionInterface $session Session object
     * @param int $version              Symfony version
     * @return Response                 Response object
     */
    #[Route('/{version}/quiz/finish', name: 'app_quiz_finish')]
    public function finishQuiz(SessionInterface $session, int $version): Response
    {
        $allQuestions = $this->getQuizData($version)[0]['mcq'] ?? [];
        $duration = $session->get('duration', 5400);
        $questions = $this->quizSessionService->prepareQuestions($allQuestions, $duration);
        $totalQuestions = count($questions);

        $userResponses = $session->get('userResponses', []);
        [$results, $correctAnswers] = $this->quizSessionService->calculateResults($questions, $userResponses);
        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        $this->quizSessionService->clearSession($session);

        return $this->render('quiz/results.html.twig', [
            'version'        => $version,
            'score'          => $score,
            'correctAnswers' => $correctAnswers,
            'totalQuestions' => $totalQuestions,
            'results'        => $results,
            'restartUrl'     => $this->generateUrl('app_quiz', ['version' => $version]),
        ]);
    }

    /**
     * Clears session data
     *
     * @param SessionInterface $session Session object
     * @return void
     */
    private function clearSession(SessionInterface $session): void
    {
        $session->remove('questionIndex');
        $session->remove('questionTimers');
//        $session->remove('timeLeft');
        $session->remove('userResponses');

    }

    /**
     * Displays exam topics for a given Symfony version
     *
     * @param int $version                          Symfony version
     * @param DocumentManager $documentManager      Document manager
     * @return Response                             Response with exam topics page
     */
    #[Route('/{version}/exam-topics', name: 'app_exam_topics', methods: ['GET'])]
    public function examTopics(int $version, DocumentManager $documentManager): Response
    {
        try {
            $database = $documentManager->getClient()->selectDatabase('symfony_certification');
            $collection = $database->selectCollection("sf{$version}_exam_topics");
            $cursor = $collection->find([], ['limit' => 1]);
            $examTopicsData = iterator_to_array($cursor);

            if (empty($examTopicsData)) {
                return $this->render('symfony/no_exam_topics_found.html.twig', ['version' => $version]);
            }

            // Extract the exam topics array from the document
            $examTopics = [];
            if (isset($examTopicsData[0])) {
                $firstDoc = $examTopicsData[0];
                $docArray = is_object($firstDoc)
                    ? (method_exists($firstDoc, 'getArrayCopy') ? $firstDoc->getArrayCopy() : (array) $firstDoc)
                    : $firstDoc;

                if (isset($docArray['exam_topics'])) {
                    $examTopics = $docArray['exam_topics'];
                } elseif (isset($docArray['topics'])) {
                    $examTopics = $docArray['topics'];
                } else {
                    $examTopics = array_values(array_filter($docArray, fn($k) => is_string($k) && !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY));
                }
            }

            return $this->render('symfony/exam-topics.html.twig', ['version' => $version, 'examTopics' => $examTopics]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load exam topics.', [
                'version' => $version,
                'exception' => $e,
            ]);

            return $this->render('symfony/no_exam_topics_found.html.twig', [
                'version' => $version,
                'error' => 'Unable to load exam topics at the moment.'
            ]);
        }
    }

    /**
     * Check for an existing exam topic corresponding to a given Symfony version
     *
     * @param int $version                          Symfony version
     * @param DocumentManager $documentManager      Document manager
     * @return JsonResponse                         Json response
     */
    #[Route('/{version}/check-exam-topics', name: 'app_check_exam_topics', methods: ['GET'])]
    public function checkExamTopics(int $version, DocumentManager $documentManager): JsonResponse
    {
        try {
            $database = $documentManager->getClient()->selectDatabase('symfony_certification');
            $collectionExists = false;
            foreach ($database->listCollections() as $collection) {
                if ($collection->getName() === "sf{$version}_exam_topics") {
                    $collectionExists = true;
                    break;
                }
            }

            return $this->json(['exists' => $collectionExists]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Database error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Checks if the topics links collection exists for a given Symfony version
     *
     * @param int $version                          Symfony version
     * @param DocumentManager $documentManager      Document manager
     * @return JsonResponse                         JSON response indicating whether the collection exists
     */
    #[Route('/{version}/check-topics-links', name: 'app_check_topics_links', methods: ['GET'])]
    public function checkTopicsLinks(int $version, DocumentManager $documentManager): JsonResponse
    {
        try {
            $database = $documentManager->getClient()->selectDatabase('symfony_certification');
            $collectionExists = false;
            $collectionNames = [];
            foreach ($database->listCollections() as $collection) {
                $collectionNames[] = $collection->getName();
                if ($collection->getName() === "sf{$version}_topics_links") {
                    $collectionExists = true;
                }
            }

            return $this->json([
                'exists' => $collectionExists,
                'searchedFor' => "sf{$version}_topics_links",
                'foundCollections' => $collectionNames
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Database error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Checks if the multiple choice questions (mcq) collection exists for a given Symfony version
     *
     * @param int $version                          Symfony version
     * @param DocumentManager $documentManager      Document manager
     * @return JsonResponse                         JSON response indicating whether the collection exists
     */
    #[Route('/{version}/check-mcq-collection', name: 'app_check_mcq_collection', methods: ['GET'])]
    public function checkMcqCollection(int $version, DocumentManager $documentManager): JsonResponse
    {
        try {
            $database = $documentManager->getClient()->selectDatabase('symfony_certification');
            $collectionExists = false;
            foreach ($database->listCollections() as $collection) {
                if ($collection->getName() === "sf{$version}_mcq_gpt-4o") {
                    $collectionExists = true;
                    break;
                }
            }

            return $this->json(['exists' => $collectionExists]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Database error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

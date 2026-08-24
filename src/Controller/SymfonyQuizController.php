<?php

namespace App\Controller;

use App\Repository\MongoDBQueryBuilder;
use App\Service\QuizSessionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * SymfonyQuizController handles the Symfony certification quiz.
 *
 * Business logic (navigation, scoring, timer, session) is delegated to
 * AbstractQuizController + QuizSessionService following the Single
 * Responsibility Principle, and is shared with PhpQuizController to avoid
 * duplication.
 */
#[Route('/symfony')]
class SymfonyQuizController extends AbstractQuizController
{
    public function __construct(
        #[Autowire(service: 'App\Repository\MongoDBQueryBuilder.mcq_gpt-4o')]
        private readonly MongoDBQueryBuilder $mcqQueryBuilder,
        private readonly LoggerInterface $logger,
        QuizSessionService $quizSessionService,
    ) {
        parent::__construct($quizSessionService);
    }

    #[Route('/{version}/quiz', name: 'app_quiz')]
    public function index(Request $request, SessionInterface $session, int $version): Response
    {
        $this->routeParams = ['version' => $version];

        return $this->doIndex($request, $session);
    }

    #[Route('/{version}/quiz/save-timer', name: 'app_quiz_save_timer', methods: ['POST'])]
    public function saveTimer(Request $request, SessionInterface $session, int $version): JsonResponse
    {
        return $this->doSaveTimer($request, $session);
    }

    #[Route('/{version}/quiz/save-response', name: 'app_quiz_save_response', methods: ['POST'])]
    public function saveResponse(Request $request, SessionInterface $session, int $version): JsonResponse
    {
        return $this->doSaveResponse($request, $session);
    }

    #[Route('/{version}/quiz/finish', name: 'app_quiz_finish')]
    public function finishQuiz(SessionInterface $session, int $version): Response
    {
        $this->routeParams = ['version' => $version];

        return $this->doFinishQuiz($session);
    }

    /**
     * Displays exam topics for a given Symfony version.
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
     * Checks for an existing exam topic corresponding to a given Symfony version.
     */
    #[Route('/{version}/check-exam-topics', name: 'app_check_exam_topics', methods: ['GET'])]
    public function checkExamTopics(int $version, DocumentManager $documentManager): JsonResponse
    {
        return $this->checkCollectionExists($documentManager, 'symfony_certification', "sf{$version}_exam_topics");
    }

    /**
     * Checks if the topics links collection exists for a given Symfony version.
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
     * Checks if the multiple choice questions (mcq) collection exists for a given Symfony version.
     */
    #[Route('/{version}/check-mcq-collection', name: 'app_check_mcq_collection', methods: ['GET'])]
    public function checkMcqCollection(int $version, DocumentManager $documentManager): JsonResponse
    {
        return $this->checkCollectionExists($documentManager, 'symfony_certification', "sf{$version}_mcq_gpt-4o");
    }

    protected function getQuizData(): array
    {
        $version = $this->routeParams['version'];

        return json_decode(json_encode(
            $this->mcqQueryBuilder
                ->selectCollection("sf{$version}_mcq_gpt-4o")
                ->find(null)
                ->toArray()
        ), true);
    }

    protected function getTechnologyLabel(): string
    {
        return "Symfony {$this->routeParams['version']}";
    }

    protected function getQuizRouteName(): string
    {
        return 'app_quiz';
    }

    protected function getSaveTimerRouteName(): string
    {
        return 'app_quiz_save_timer';
    }

    protected function getSaveResponseRouteName(): string
    {
        return 'app_quiz_save_response';
    }

    protected function getFinishRouteName(): string
    {
        return 'app_quiz_finish';
    }

    protected function getCheckTopicsRouteName(): string
    {
        return 'app_check_exam_topics';
    }

    protected function getCrawlTopicsRouteName(): string
    {
        return 'app_execute_crawl_topics_command';
    }

    protected function getCheckLinksRouteName(): string
    {
        return 'app_check_topics_links';
    }

    protected function getCrawlDocRouteName(): string
    {
        return 'app_execute_crawl_doc_command';
    }

    protected function getMcqRouteName(): string
    {
        return 'app_execute_mcq_command';
    }
}


<?php

namespace App\Controller;

use App\Repository\MongoDBQueryBuilder;
use App\Service\QuizSessionService;
use App\Technology\Php\PhpManualDocSource;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


/**
 * PhpQuizController — quiz for the PHP manual.
 *
 * Shares all quiz flow logic with SymfonyQuizController via
 * AbstractQuizController + QuizSessionService. Uses a dedicated
 * MongoDBQueryBuilder pointing to the 'php_manual' database, showing that
 * adding a new technology only requires a new controller + DocSource, with
 * zero changes to the core quiz engine.
 *
 * PHP has no certification version, so routes do not include a version segment.
 */
#[Route('/php')]
class PhpQuizController extends AbstractQuizController
{
    public function __construct(
        #[Autowire(service: 'App\Repository\MongoDBQueryBuilder.php_mcq_gpt-4o')]
        private readonly MongoDBQueryBuilder $mcqQueryBuilder,
        private readonly PhpManualDocSource $phpDocSource,
        QuizSessionService $quizSessionService,
    ) {
        parent::__construct($quizSessionService);
    }

    /**
     * Main quiz page.
     */
    #[Route('/quiz', name: 'app_php_quiz')]
    public function index(Request $request, SessionInterface $session): Response
    {
        return $this->doIndex($request, $session);
    }

    /**
     * Persists the remaining time in session.
     */
    #[Route('/quiz/save-timer', name: 'app_php_quiz_save_timer', methods: ['POST'])]
    public function saveTimer(Request $request, SessionInterface $session): JsonResponse
    {
        return $this->doSaveTimer($request, $session);
    }

    /**
     * Persists user's selected answer in session.
     */
    #[Route('/quiz/save-response', name: 'app_php_quiz_save_response', methods: ['POST'])]
    public function saveResponse(Request $request, SessionInterface $session): JsonResponse
    {
        return $this->doSaveResponse($request, $session);
    }

    /**
     * Scores the quiz and renders the results page.
     */
    #[Route('/quiz/finish', name: 'app_php_quiz_finish')]
    public function finishQuiz(SessionInterface $session): Response
    {
        return $this->doFinishQuiz($session);
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

    protected function getQuizData(): array
    {
        return json_decode(json_encode(
            $this->mcqQueryBuilder
                ->selectCollection($this->phpDocSource->getMcqCollectionName())
                ->find(null)
                ->toArray()
        ), true);
    }

    protected function getTechnologyLabel(): string
    {
        return 'PHP';
    }

    protected function getQuizRouteName(): string
    {
        return 'app_php_quiz';
    }

    protected function getSaveTimerRouteName(): string
    {
        return 'app_php_quiz_save_timer';
    }

    protected function getSaveResponseRouteName(): string
    {
        return 'app_php_quiz_save_response';
    }

    protected function getFinishRouteName(): string
    {
        return 'app_php_quiz_finish';
    }

    protected function getCheckTopicsRouteName(): string
    {
        return 'app_php_check_topics_collection';
    }

    protected function getCrawlTopicsRouteName(): string
    {
        return 'app_php_execute_crawl_topics_command';
    }

    protected function getCheckLinksRouteName(): string
    {
        return 'app_php_check_topics_links';
    }

    protected function getCrawlDocRouteName(): string
    {
        return 'app_php_execute_crawl_doc_command';
    }

    protected function getMcqRouteName(): string
    {
        return 'app_php_execute_mcq_command';
    }
}


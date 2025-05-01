<?php

namespace App\Controller;

use App\Repository\MongoDBQueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * MainController
 */
class MainController extends AbstractController
{
    /**
     * @var MongoDBQueryBuilder MongoDB query builder
     */
    private MongoDBQueryBuilder $examTopicsQueryBuilder;

    /**
     * Constructor
     */
    public function __construct(
        #[Autowire(service: 'App\Repository\MongoDBQueryBuilder.exam_topics')]
        MongoDBQueryBuilder $examTopicsQueryBuilder,
    ) {
        $this->examTopicsQueryBuilder = $examTopicsQueryBuilder;
    }

    /**
     * Index
     *
     * @return Response
     */
    #[Route("/", name:"app_index")]
    public function index(): Response
    {

        return $this->render('index.html.twig');
    }

    /**
     * Retrieves the exam topics for a specific Symfony version.
     *
     * @param int $version
     * @return Response
     */
    #[Route("/symfony/{version}/exam-topics", name: "symfony_exam_topics", methods: ['GET'])]
    public function symfonyExamTopics(int $version): Response
    {
        $examTopics = json_decode(json_encode(
            $this->examTopicsQueryBuilder
                ->selectCollection("sf{$version}_exam_topics")
                ->find(null)
                ->toArray()
        ), true);

        if (empty($examTopics) || !isset($examTopics[0])) {
            return $this->render('symfony/no_exam_topics_found.html.twig', ['version' => $version]);
        }

        return $this->render('symfony/exam-topics.html.twig', [
            'examTopics' => $examTopics[0]["topics"],
            'version' => $version,
        ]);
    }

}

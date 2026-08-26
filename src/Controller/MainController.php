<?php

namespace App\Controller;

use App\Repository\MongoDBQueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * MainController
 */
class MainController extends AbstractController
{
    /**
     * @var MongoDBQueryBuilder MongoDB query builder
     */
    private MongoDBQueryBuilder $examTopicsQueryBuilder {
        get {
            return $this->examTopicsQueryBuilder;
        }
        set {
            $this->examTopicsQueryBuilder = $value;
        }
    }

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
}

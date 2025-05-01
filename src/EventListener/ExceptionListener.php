<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * ExceptionListener
 *
 * Handles exceptions in the application.
 */
class ExceptionListener
{
    private Environment $twig;

    /**
     * Constructor
     *
     * @param Environment $twig Twig environment
     */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Handles exceptions
     *
     * @param ExceptionEvent $event
     * @return string Rendered HTML response
     * @throws RuntimeError
     * @throws SyntaxError|LoaderError
     */
    public function onKernelException(ExceptionEvent $event): string
    {
        // Log the exception (not implemented in this example)
        $exception = $event->getThrowable();

        if ($exception instanceof NotFoundHttpException) {
            // Handle 404 Not Found
            $content = $this->twig->render('bundles/TwigBundle/Exception/error/404.html.twig');
            $event->setResponse(new Response($content, 404));
        } elseif ($exception instanceof AccessDeniedHttpException) {
            // Handle 403 Forbidden
            $event->setResponse(new Response('Access denied', 403));
        } else {
            // Handle other exceptions
            $event->setResponse(new Response('An error occurred', 500));
        }

        // Prevent the default error handling
        $event->stopPropagation();
        return $event->getResponse()->getContent();
    }
}
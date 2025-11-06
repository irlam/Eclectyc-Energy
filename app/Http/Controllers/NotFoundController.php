<?php
/**
 * eclectyc-energy/app/Http/Controllers/NotFoundController.php
 * Handles 404 Not Found errors
 * Last updated: 06/11/2025
 */

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class NotFoundController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    /**
     * Handle 404 Not Found errors
     */
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->view->render($response->withStatus(404), 'error.twig', [
            'page_title' => 'Page Not Found',
            'error_code' => 404,
            'error_message' => 'The requested page could not be found.'
        ]);
    }
}

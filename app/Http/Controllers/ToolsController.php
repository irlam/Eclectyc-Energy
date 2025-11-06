<?php
/**
 * eclectyc-energy/app/Http/Controllers/ToolsController.php
 * Bridges CLI tooling output into the dashboard.
 */

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ToolsController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function checkStructure(Request $request, Response $response): Response
    {
        $output = $this->runTool('tools/check-structure.php');

        return $this->view->render($response, 'tools/check.twig', [
            'title' => 'Structure Check',
            'output' => $output,
        ]);
    }

    public function showStructure(Request $request, Response $response): Response
    {
        $output = $this->runTool('tools/show-structure.php');

        return $this->view->render($response, 'tools/show.twig', [
            'title' => 'Structure Overview',
            'output' => $output,
        ]);
    }

    private function runTool(string $script): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $path = $basePath . '/' . ltrim($script, '/');

        if (!is_file($path)) {
            return 'Unable to locate script: ' . $script;
        }

        $result = shell_exec('php ' . escapeshellarg($path));
        return $result !== null ? trim($result) : 'No output available';
    }
}

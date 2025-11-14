<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/DocsController.php
 * Documentation viewer controller
 */

namespace App\Http\Controllers\Admin;

use Parsedown;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DocsController
{
    private Twig $view;
    private ?PDO $pdo;
    private string $docsPath;
    private string $rootPath;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
        $this->docsPath = BASE_PATH . '/docs';
        $this->rootPath = BASE_PATH;
    }

    /**
     * List all documentation files
     */
    public function index(Request $request, Response $response): Response
    {
        $docs = [];
        
        // Get all .md files from docs directory
        if (is_dir($this->docsPath)) {
            $files = scandir($this->docsPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                    $docs[] = [
                        'filename' => $file,
                        'title' => $this->formatTitle($file),
                        'path' => 'docs/' . $file,
                        'size' => filesize($this->docsPath . '/' . $file),
                        'modified' => filemtime($this->docsPath . '/' . $file),
                    ];
                }
            }
        }
        
        // Add root-level markdown files (README.md, STATUS.md, etc.)
        $rootFiles = ['README.md', 'STATUS.md', 'SEED_README.md'];
        foreach ($rootFiles as $file) {
            $fullPath = $this->rootPath . '/' . $file;
            if (file_exists($fullPath)) {
                $docs[] = [
                    'filename' => $file,
                    'title' => $this->formatTitle($file),
                    'path' => $file,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath),
                    'is_root' => true,
                ];
            }
        }
        
        // Sort by title
        usort($docs, function($a, $b) {
            return strcmp($a['title'], $b['title']);
        });

        return $this->view->render($response, 'admin/docs.twig', [
            'page_title' => 'Documentation',
            'docs' => $docs,
        ]);
    }

    /**
     * View a specific documentation file
     */
    public function view(Request $request, Response $response, array $args): Response
    {
        $filename = $args['filename'] ?? '';
        
        // Security: prevent directory traversal
        $filename = str_replace(['..', '\\', '/'], '', $filename);
        
        // Check if it's a root file or in docs directory
        $isRootFile = in_array($filename, ['README.md', 'STATUS.md', 'SEED_README.md']);
        
        if ($isRootFile) {
            $filePath = $this->rootPath . '/' . $filename;
        } else {
            $filePath = $this->docsPath . '/' . $filename;
        }
        
        // Verify file exists and is a markdown file
        if (!file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'md') {
            $body = $response->getBody();
            $body->write('Documentation file not found.');
            return $response->withStatus(404)->withBody($body);
        }
        
        // Read and parse markdown
        $content = file_get_contents($filePath);
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true); // Enable safe mode for security
        $html = $parsedown->text($content);
        
        return $this->view->render($response, 'admin/docs_view.twig', [
            'page_title' => $this->formatTitle($filename),
            'filename' => $filename,
            'title' => $this->formatTitle($filename),
            'content' => $html,
            'modified' => date('d/m/Y H:i:s', filemtime($filePath)),
        ]);
    }

    /**
     * Format filename into readable title
     */
    private function formatTitle(string $filename): string
    {
        // Remove .md extension
        $title = preg_replace('/\.md$/i', '', $filename);
        
        // Handle special cases
        if ($title === 'README') return 'README';
        if ($title === 'STATUS') return 'Status';
        if ($title === 'SEED_README') return 'Database Seed Guide';
        if ($title === 'COMPLETE_GUIDE') return 'Complete Guide';
        
        // Replace underscores and hyphens with spaces
        $title = str_replace(['_', '-'], ' ', $title);
        
        // Capitalize words
        $title = ucwords($title);
        
        return $title;
    }
}

 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;
use App\Domain\Aggregation\DailyAggregator;
use DateTimeImmutable;

use App\Config\Database;
use App\Domain\Aggregation\DailyAggregator;
use DateTimeImmutable;

// Check if running from CLI

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Parse arguments
$args = getopt('d:v', ['date:', 'verbose']);
$verbose = isset($args['v']) || isset($args['verbose']);
$targetDate = $args['d'] ?? $args['date'] ?? date('Y-m-d', strtotime('-1 day'));
$targetDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $targetDate);

// Validate date
if (!$targetDateObj) {
    echo "Error: Invalid date format. Use YYYY-MM-DD.\n";
    exit(1);
}

// Output header
if ($verbose) {
    echo "\n";
    echo "===========================================\n";
    echo "  Eclectyc Energy Daily Aggregation\n";
    echo "  " . date('d/m/Y H:i:s') . "\n";
    echo "===========================================\n\n";
    echo "Target Date: " . $targetDateObj->format('Y-m-d') . "\n\n";
}

try {
    // Connect to database
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    $aggregator = new DailyAggregator($db);
    $summary = $aggregator->aggregate($targetDateObj);

    // Log to audit
    $auditQuery = $db->prepare('
        INSERT INTO audit_logs (action, entity_type, new_values) 
        VALUES ("daily_aggregation", "system", ?)
    ');

    $auditQuery->execute([json_encode($summary->toArray(), JSON_THROW_ON_ERROR)]);

    if ($verbose) {
        echo "Processed {$summary->getMetersWithReadings()} meters with readings out of {$summary->getTotalMeters()} active meters.\n";
        echo "Meters without readings: {$summary->getMetersWithoutReadings()}\n";
        echo "Errors: {$summary->getErrors()}\n";

        foreach ($summary->getErrorMessages() as $message) {
            echo " - $message\n";
        }

        echo "\nAggregation Complete!\n";
    }

    exit($summary->getErrors() > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
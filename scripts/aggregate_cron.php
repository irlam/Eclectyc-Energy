 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;
use App\Domain\Aggregation\DailyAggregator;
use App\Domain\Aggregation\PeriodAggregator;
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
$args = getopt('d:v', ['date:', 'verbose', 'range:']);
$verbose = isset($args['v']) || isset($args['verbose']);
$targetDate = $args['d'] ?? $args['date'] ?? date('Y-m-d', strtotime('-1 day'));
$targetDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $targetDate);
$range = strtolower($args['range'] ?? 'daily');

// Validate date
if (!$targetDateObj) {
    echo "Error: Invalid date format. Use YYYY-MM-DD.\n";
    exit(1);
}

$allowedRanges = ['daily', 'weekly', 'monthly', 'annual'];
if (!in_array($range, $allowedRanges, true)) {
    echo "Error: Invalid range. Use one of: " . implode(', ', $allowedRanges) . "\n";
    exit(1);
}

// Output header
if ($verbose) {
    echo "\n";
    echo "===========================================\n";
    echo "  Eclectyc Energy Aggregation\n";
    echo "  " . date('d/m/Y H:i:s') . "\n";
    echo "===========================================\n\n";
    echo "Target Date: " . $targetDateObj->format('Y-m-d') . "\n";
    echo "Range: " . ucfirst($range) . "\n\n";
}

try {
    // Connect to database
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    switch ($range) {
        case 'weekly':
            $periodAggregator = new PeriodAggregator($db);
            $weekStart = $targetDateObj->modify('monday this week');
            if ($weekStart > $targetDateObj) {
                $weekStart = $weekStart->modify('-7 days');
            }
            $weekEnd = $weekStart->modify('+6 days');
            $summary = $periodAggregator->aggregate('weekly', $weekStart, $weekEnd);
            break;
        case 'monthly':
            $periodAggregator = new PeriodAggregator($db);
            $monthStart = $targetDateObj->modify('first day of this month');
            $monthEnd = $monthStart->modify('last day of this month');
            $summary = $periodAggregator->aggregate('monthly', $monthStart, $monthEnd);
            break;
        case 'annual':
            $periodAggregator = new PeriodAggregator($db);
            $yearStart = $targetDateObj->setDate((int) $targetDateObj->format('Y'), 1, 1);
            $yearEnd = $yearStart->setDate((int) $yearStart->format('Y'), 12, 31);
            $summary = $periodAggregator->aggregate('annual', $yearStart, $yearEnd);
            break;
        default:
            $aggregator = new DailyAggregator($db);
            $summary = $aggregator->aggregate($targetDateObj);
            break;
    }

    // Log to audit
    $auditQuery = $db->prepare('
        INSERT INTO audit_logs (action, entity_type, new_values) 
        VALUES (:action, \'system\', :payload)
    ');
     * Cron job script for aggregating meter readings into daily/weekly/monthly/annual summaries
    $auditQuery->execute([
        'action' => $range . '_aggregation',
        'payload' => json_encode($summary->toArray(), JSON_THROW_ON_ERROR),
    ]);

    if ($verbose) {
        echo "Processed {$summary->getMetersWithData()} meters with data out of {$summary->getTotalMeters()} active meters.\n";
        echo "Meters without data: {$summary->getMetersWithoutData()}\n";
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
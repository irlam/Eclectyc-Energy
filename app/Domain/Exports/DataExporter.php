<?php
/**
 * eclectyc-energy/app/Domain/Exports/DataExporter.php
 * Exports aggregated energy data in various formats.
 */

namespace App\Domain\Exports;

use PDO;
use DateTimeImmutable;

class DataExporter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Export data for a meter or site in CSV format
     * 
     * @param array $params Export parameters (meter_id, site_id, start_date, end_date, granularity)
     * @return ExportResult Export results with data and metadata
     */
    public function export(array $params): ExportResult
    {
        $result = new ExportResult($params);

        try {
            $this->validateParams($params);
            
            $granularity = $params['granularity'] ?? 'daily';
            $data = $this->fetchData($params, $granularity);

            $result->setData($data);
            $result->setRowCount(count($data));
        } catch (\Exception $e) {
            $result->registerError($e->getMessage());
        }

        return $result;
    }

    /**
     * Validate export parameters
     * 
     * @param array $params Export parameters
     * @throws \InvalidArgumentException if validation fails
     */
    private function validateParams(array $params): void
    {
        if (!isset($params['start_date'])) {
            throw new \InvalidArgumentException('Missing start_date parameter');
        }

        if (!isset($params['end_date'])) {
            throw new \InvalidArgumentException('Missing end_date parameter');
        }

        if (!isset($params['meter_id']) && !isset($params['site_id'])) {
            throw new \InvalidArgumentException('Must specify either meter_id or site_id');
        }
    }

    /**
     * Fetch data based on export parameters
     * 
     * @param array $params Export parameters
     * @param string $granularity Data granularity (daily, weekly, monthly)
     * @return array Data rows
     */
    private function fetchData(array $params, string $granularity): array
    {
        if ($granularity === 'daily') {
            return $this->fetchDailyData($params);
        }

        // For other granularities, start with daily data
        // This would be expanded to use weekly/monthly aggregation tables when available
        return $this->fetchDailyData($params);
    }

    /**
     * Fetch daily aggregation data
     * 
     * @param array $params Export parameters
     * @return array Data rows
     */
    private function fetchDailyData(array $params): array
    {
        $query = '
            SELECT 
                da.date,
                m.mpan,
                s.name as site_name,
                da.total_consumption,
                da.peak_consumption,
                da.off_peak_consumption,
                da.reading_count
            FROM daily_aggregations da
            INNER JOIN meters m ON da.meter_id = m.id
            INNER JOIN sites s ON m.site_id = s.id
            WHERE da.date BETWEEN :start_date AND :end_date
        ';

        $queryParams = [
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
        ];

        if (isset($params['meter_id'])) {
            $query .= ' AND da.meter_id = :meter_id';
            $queryParams['meter_id'] = $params['meter_id'];
        } elseif (isset($params['site_id'])) {
            $query .= ' AND m.site_id = :site_id';
            $queryParams['site_id'] = $params['site_id'];
        }

        $query .= ' ORDER BY da.date, m.mpan';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($queryParams);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Format data as CSV string
     * 
     * @param array $data Data rows
     * @return string CSV content
     */
    public function formatAsCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $csv = [];
        
        // Add header row
        $csv[] = implode(',', array_keys($data[0]));

        // Add data rows
        foreach ($data as $row) {
            $csv[] = implode(',', array_map(function ($value) {
                // Convert null to empty string
                if ($value === null) {
                    return '';
                }
                
                // Convert to string
                $value = (string) $value;
                
                // Escape values containing commas, quotes, or newlines
                if (strpos($value, ',') !== false || 
                    strpos($value, '"') !== false || 
                    strpos($value, "\n") !== false || 
                    strpos($value, "\r") !== false) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }
                
                return $value;
            }, $row));
        }

        return implode("\n", $csv);
    }
}

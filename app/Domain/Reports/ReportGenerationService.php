<?php
/**
 * eclectyc-energy/app/Domain/Reports/ReportGenerationService.php
 * Service to generate and email reports
 */

namespace App\Domain\Reports;

use App\Models\ScheduledReport;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

class ReportGenerationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate and send a scheduled report
     */
    public function generateAndSend(ScheduledReport $report)
    {
        // Record execution start
        $executionId = $report->recordExecution('processing');

        try {
            // Generate the report
            $reportData = $this->generateReport($report);
            
            // Save report to file
            $filePath = $this->saveReport($report, $reportData);
            
            // Get recipients
            $recipients = $report->getRecipients();
            $recipientsCount = count($recipients);
            
            // Send to recipients
            $emailsSent = 0;
            foreach ($recipients as $recipient) {
                if ($this->sendReportEmail($report, $recipient['email'], $filePath, $reportData)) {
                    $emailsSent++;
                }
            }
            
            // Update execution record
            $report->updateExecution($executionId, 'completed', [
                'file_path' => $filePath,
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                'recipients_count' => $recipientsCount,
                'emails_sent' => $emailsSent
            ]);

            // Update report's last run
            $report->update([
                'last_run_at' => date('Y-m-d H:i:s'),
                'next_run_at' => $report->calculateNextRun()
            ]);

            return [
                'success' => true,
                'execution_id' => $executionId,
                'emails_sent' => $emailsSent,
                'file_path' => $filePath
            ];

        } catch (\Exception $e) {
            // Record failure
            $report->updateExecution($executionId, 'failed', [
                'error_message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate report data based on type
     */
    private function generateReport(ScheduledReport $report)
    {
        $filters = $report->getFiltersArray();
        
        switch ($report->report_type) {
            case 'consumption':
                return $this->generateConsumptionReport($filters);
            case 'cost':
                return $this->generateCostReport($filters);
            case 'data_quality':
                return $this->generateDataQualityReport($filters);
            case 'tariff_switching':
                return $this->generateTariffSwitchingReport($filters);
            default:
                return $this->generateConsumptionReport($filters);
        }
    }

    /**
     * Generate consumption report
     */
    private function generateConsumptionReport($filters)
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        $stmt = $this->pdo->prepare('
            SELECT 
                s.name AS site_name,
                m.mpan,
                SUM(da.total_consumption) AS total_consumption,
                COUNT(DISTINCT da.date) AS days_of_data,
                MIN(da.date) AS first_reading,
                MAX(da.date) AS last_reading
            FROM daily_aggregations da
            JOIN meters m ON da.meter_id = m.id
            JOIN sites s ON m.site_id = s.id
            WHERE da.date BETWEEN ? AND ?
            GROUP BY s.id, m.id
            ORDER BY s.name, m.mpan
        ');
        $stmt->execute([$startDate, $endDate]);

        return [
            'type' => 'consumption',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Generate cost report
     */
    private function generateCostReport($filters)
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        $stmt = $this->pdo->prepare('
            SELECT 
                s.name AS site_name,
                m.mpan,
                t.name AS tariff_name,
                SUM(da.total_consumption) AS total_consumption,
                SUM(da.total_cost) AS total_cost
            FROM daily_aggregations da
            JOIN meters m ON da.meter_id = m.id
            JOIN sites s ON m.site_id = s.id
            LEFT JOIN tariffs t ON m.tariff_id = t.id
            WHERE da.date BETWEEN ? AND ?
            GROUP BY s.id, m.id
            ORDER BY total_cost DESC
        ');
        $stmt->execute([$startDate, $endDate]);

        return [
            'type' => 'cost',
            'period' => ['start' => $startDate, 'end' => $endDate],
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Generate data quality report
     */
    private function generateDataQualityReport($filters)
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                s.name AS site_name,
                m.mpan,
                dq.issue_type,
                COUNT(*) AS issue_count,
                MAX(dq.detected_at) AS last_detected
            FROM data_quality_issues dq
            JOIN meters m ON dq.meter_id = m.id
            JOIN sites s ON m.site_id = s.id
            WHERE dq.status = "open"
            GROUP BY s.id, m.id, dq.issue_type
            ORDER BY issue_count DESC
        ');
        $stmt->execute();

        return [
            'type' => 'data_quality',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Generate tariff switching report
     */
    private function generateTariffSwitchingReport($filters)
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                s.name AS site_name,
                m.mpan,
                tsa.current_tariff_id,
                tsa.recommended_tariff_id,
                tsa.potential_annual_savings,
                tsa.analysis_period_days
            FROM tariff_switching_analyses tsa
            JOIN meters m ON tsa.meter_id = m.id
            JOIN sites s ON m.site_id = s.id
            WHERE tsa.potential_annual_savings > 0
            ORDER BY tsa.potential_annual_savings DESC
            LIMIT 20
        ');
        $stmt->execute();

        return [
            'type' => 'tariff_switching',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Save report to file
     */
    private function saveReport(ScheduledReport $report, array $reportData)
    {
        $storageDir = __DIR__ . '/../../../storage/reports';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $filename = sprintf(
            'report_%s_%s_%s.%s',
            $report->id,
            $report->report_type,
            date('Y-m-d_His'),
            $this->getFileExtension($report->report_format)
        );

        $filePath = $storageDir . '/' . $filename;

        // Generate file based on format
        switch ($report->report_format) {
            case 'csv':
                $this->saveCsvReport($filePath, $reportData);
                break;
            case 'html':
                $this->saveHtmlReport($filePath, $reportData);
                break;
            default:
                $this->saveCsvReport($filePath, $reportData);
        }

        return $filePath;
    }

    /**
     * Save report as CSV
     */
    private function saveCsvReport($filePath, array $reportData)
    {
        $fp = fopen($filePath, 'w');
        
        // Write header
        fputcsv($fp, ['Report Type', $reportData['type']]);
        if (isset($reportData['period'])) {
            fputcsv($fp, ['Period', $reportData['period']['start'] . ' to ' . $reportData['period']['end']]);
        }
        fputcsv($fp, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($fp, []);

        // Write data
        if (!empty($reportData['data'])) {
            // Header row
            fputcsv($fp, array_keys($reportData['data'][0]));
            
            // Data rows
            foreach ($reportData['data'] as $row) {
                fputcsv($fp, $row);
            }
        }

        fclose($fp);
    }

    /**
     * Save report as HTML
     */
    private function saveHtmlReport($filePath, array $reportData)
    {
        $html = '<html><head><title>Energy Report</title></head><body>';
        $html .= '<h1>' . ucfirst($reportData['type']) . ' Report</h1>';
        
        if (isset($reportData['period'])) {
            $html .= '<p>Period: ' . $reportData['period']['start'] . ' to ' . $reportData['period']['end'] . '</p>';
        }
        
        $html .= '<table border="1" cellpadding="5">';
        
        if (!empty($reportData['data'])) {
            // Header row
            $html .= '<tr>';
            foreach (array_keys($reportData['data'][0]) as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr>';
            
            // Data rows
            foreach ($reportData['data'] as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $html .= '</tr>';
            }
        }
        
        $html .= '</table></body></html>';
        
        file_put_contents($filePath, $html);
    }

    /**
     * Get file extension for format
     */
    private function getFileExtension($format)
    {
        switch ($format) {
            case 'csv':
                return 'csv';
            case 'html':
                return 'html';
            case 'pdf':
                return 'pdf';
            case 'excel':
                return 'xlsx';
            default:
                return 'csv';
        }
    }

    /**
     * Send report via email
     */
    private function sendReportEmail(ScheduledReport $report, $to, $filePath, array $reportData)
    {
        try {
            $mail = new PHPMailer(true);
            
            // Check if we're using SMTP or mail()
            if (getenv('MAIL_SMTP_ENABLED') === 'true') {
                $mail->isSMTP();
                $mail->Host = getenv('MAIL_HOST');
                $mail->SMTPAuth = true;
                $mail->Username = getenv('MAIL_USERNAME');
                $mail->Password = getenv('MAIL_PASSWORD');
                $mail->SMTPSecure = getenv('MAIL_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = getenv('MAIL_PORT') ?: 587;
            } else {
                $mail->isMail();
            }

            $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: 'noreply@eclectyc.energy', getenv('MAIL_FROM_NAME') ?: 'Eclectyc Energy');
            $mail->addAddress($to);
            $mail->Subject = 'Energy Report: ' . $report->name;
            
            // Email body
            $body = "Your scheduled energy report is attached.\n\n";
            $body .= "Report: {$report->name}\n";
            if ($report->description) {
                $body .= "Description: {$report->description}\n";
            }
            $body .= "Type: " . ucfirst($report->report_type) . "\n";
            if (isset($reportData['period'])) {
                $body .= "Period: {$reportData['period']['start']} to {$reportData['period']['end']}\n";
            }
            $body .= "\n---\n";
            $body .= "Eclectyc Energy Management Platform\n";
            
            $mail->Body = $body;
            $mail->isHTML(false);

            // Attach report file
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath);
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Failed to send report email to {$to}: " . $mail->ErrorInfo);
            return false;
        }
    }
}

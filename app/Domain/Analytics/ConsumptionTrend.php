<?php
/**
 * eclectyc-energy/app/Domain/Analytics/ConsumptionTrend.php
 * Value object representing consumption trend analysis results.
 * Last updated: 06/11/2025
 */

namespace App\Domain\Analytics;

use DateTimeImmutable;

class ConsumptionTrend
{
    private int $siteId;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    /**
     * @var array<string, mixed>
     */
    private array $trendData;
    /**
     * @var array<string, mixed>
     */
    private array $insights;

    /**
     * @param int $siteId Site identifier
     * @param DateTimeImmutable $startDate Start date of analysis period
     * @param DateTimeImmutable $endDate End date of analysis period
     * @param array<string, mixed> $trendData Trend data points
     * @param array<string, mixed> $insights Generated insights
     */
    public function __construct(
        int $siteId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        array $trendData,
        array $insights
    ) {
        $this->siteId = $siteId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->trendData = $trendData;
        $this->insights = $insights;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrendData(): array
    {
        return $this->trendData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInsights(): array
    {
        return $this->insights;
    }

    public function toArray(): array
    {
        return [
            'site_id' => $this->siteId,
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'trend_data' => $this->trendData,
            'insights' => $this->insights,
        ];
    }
}

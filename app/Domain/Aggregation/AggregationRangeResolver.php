<?php
/**
 * eclectyc-energy/app/Domain/Aggregation/AggregationRangeResolver.php
 * Utility class for resolving aggregation date ranges.
 */

namespace App\Domain\Aggregation;

use DateTimeImmutable;

class AggregationRangeResolver
{
    /**
     * Resolve weekly range from a target date.
     * Returns the Monday-Sunday week containing the target date.
     */
    public static function resolveWeeklyRange(DateTimeImmutable $targetDate): array
    {
        $weekStart = $targetDate->modify('monday this week');
        if ($weekStart > $targetDate) {
            $weekStart = $weekStart->modify('-7 days');
        }
        $weekEnd = $weekStart->modify('+6 days');
        
        return [$weekStart, $weekEnd];
    }

    /**
     * Resolve monthly range from a target date.
     * Returns the first and last day of the month containing the target date.
     */
    public static function resolveMonthlyRange(DateTimeImmutable $targetDate): array
    {
        $monthStart = $targetDate->modify('first day of this month');
        $monthEnd = $monthStart->modify('last day of this month');
        
        return [$monthStart, $monthEnd];
    }

    /**
     * Resolve annual range from a target date.
     * Returns January 1st and December 31st of the year containing the target date.
     */
    public static function resolveAnnualRange(DateTimeImmutable $targetDate): array
    {
        $yearStart = $targetDate->setDate((int) $targetDate->format('Y'), 1, 1);
        $yearEnd = $yearStart->setDate((int) $yearStart->format('Y'), 12, 31);
        
        return [$yearStart, $yearEnd];
    }
}

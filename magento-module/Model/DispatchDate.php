<?php
declare(strict_types=1);

namespace Acme\DispatchEstimate\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calculates the dispatch date for an order from a same-day cut-off, skipping weekends.
 *
 * Deliberately free of framework dependencies so it runs under plain PHPUnit.
 */
class DispatchDate
{
    /**
     * @var int
     */
    private int $cutoffHour;

    /**
     * @param int $cutoffHour Orders placed before this hour, store time, dispatch the same working day.
     * @throws InvalidArgumentException
     */
    public function __construct(int $cutoffHour = 14)
    {
        if ($cutoffHour < 0 || $cutoffHour > 23) {
            throw new InvalidArgumentException('Cut-off hour must be between 0 and 23.');
        }
        $this->cutoffHour = $cutoffHour;
    }

    /**
     * Return the dispatch date for an order placed at the given store-local time.
     *
     * @param DateTimeImmutable $placedAt
     * @return DateTimeImmutable Midnight on the dispatch day, in the same time zone.
     */
    public function forOrderPlacedAt(DateTimeImmutable $placedAt): DateTimeImmutable
    {
        $day = $placedAt->setTime(0, 0);

        if ((int) $placedAt->format('G') >= $this->cutoffHour) {
            $day = $day->modify('+1 day');
        }

        while ((int) $day->format('N') >= 6) {
            $day = $day->modify('+1 day');
        }

        return $day;
    }
}

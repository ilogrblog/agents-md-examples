<?php
declare(strict_types=1);

namespace Acme\DispatchEstimate\Test\Unit\Model;

use Acme\DispatchEstimate\Model\DispatchDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DispatchDateTest extends TestCase
{
    /**
     * @var DateTimeZone
     */
    private DateTimeZone $zone;

    protected function setUp(): void
    {
        $this->zone = new DateTimeZone('Europe/London');
    }

    public function testBeforeCutoffDispatchesSameDay(): void
    {
        $this->assertDispatch('2026-09-29 13:59', '2026-09-29');
    }

    public function testAtCutoffDispatchesNextWorkingDay(): void
    {
        $this->assertDispatch('2026-09-29 14:00', '2026-09-30');
    }

    public function testFridayAfterCutoffDispatchesMonday(): void
    {
        $this->assertDispatch('2026-10-02 16:30', '2026-10-05');
    }

    public function testWeekendOrderDispatchesMonday(): void
    {
        $this->assertDispatch('2026-10-03 09:00', '2026-10-05');
        $this->assertDispatch('2026-10-04 23:59', '2026-10-05');
    }

    public function testRejectsImpossibleCutoff(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DispatchDate(24);
    }

    /**
     * @param string $placedAt
     * @param string $expected
     * @return void
     */
    private function assertDispatch(string $placedAt, string $expected): void
    {
        $result = (new DispatchDate(14))->forOrderPlacedAt(new DateTimeImmutable($placedAt, $this->zone));
        $this->assertSame($expected, $result->format('Y-m-d'));
        $this->assertSame('Europe/London', $result->getTimezone()->getName());
    }
}

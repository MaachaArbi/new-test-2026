<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\BookingPayerSplit;
use App\Modules\Booking\Domain\Exception\InvalidBookingPayerSplitException;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BookingPayerSplitTest extends TestCase
{
    #[Test]
    public function assign_exposes_amount_and_is_active(): void
    {
        $split = BookingPayerSplit::assign(
            bookingId: 10,
            payerAccountId: 20,
            amount: Money::fromMinorUnits(7_000, 'TND'),
        );

        self::assertTrue($split->isActive());
        self::assertSame(7_000, $split->amount()->amount());
        self::assertSame('TND', $split->currencyCode());
        self::assertNull($split->validTo());
    }

    #[Test]
    public function revoke_rejects_second_call(): void
    {
        $split = BookingPayerSplit::assign(
            bookingId: 1,
            payerAccountId: 2,
            amount: Money::fromMinorUnits(100, 'TND'),
        );

        $split->revoke();
        self::assertFalse($split->isActive());

        $this->expectException(InvalidBookingPayerSplitException::class);
        $split->revoke();
    }

    #[Test]
    public function entity_has_no_booking_ceiling_api(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(BookingPayerSplit::class))->getMethods(),
        );

        self::assertNotContains('assertWithinTotal', $methods);
        self::assertNotContains('recalculateTotals', $methods);
    }
}

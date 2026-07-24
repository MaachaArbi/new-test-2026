<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\BookingSettlement;
use App\Modules\Booking\Domain\Exception\InvalidBookingSettlementException;
use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;
use App\Modules\Booking\Domain\ValueObject\SettlementRate;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BookingSettlementTest extends TestCase
{
    #[Test]
    public function assign_defaults_settled_direct_to_zero_and_exposes_money(): void
    {
        $settlement = BookingSettlement::assign(
            bookingId: 10,
            beneficiaryAccountId: 20,
            beneficiaryRole: BeneficiaryRole::Distributor,
            amountOwed: Money::fromMinorUnits(15_101_10, 'TND'),
            rate: SettlementRate::fromString('50'),
            resalePriceAmount: Money::fromMinorUnits(18_121_32, 'TND'),
        );

        self::assertTrue($settlement->isActive());
        self::assertSame(0, $settlement->amountSettledDirect()->amount());
        self::assertSame('50', $settlement->rate()?->toString());
        self::assertSame(18_121_32, $settlement->resalePriceAmount()?->amount());
        self::assertSame('TND', $settlement->currencyCode());
    }

    #[Test]
    public function assign_rejects_currency_mismatch_on_resale(): void
    {
        $this->expectException(InvalidBookingSettlementException::class);

        BookingSettlement::assign(
            bookingId: 1,
            beneficiaryAccountId: 2,
            beneficiaryRole: BeneficiaryRole::Supplier,
            amountOwed: Money::fromMinorUnits(100, 'TND'),
            resalePriceAmount: Money::fromMinorUnits(100, 'EUR'),
        );
    }

    #[Test]
    public function revoke_rejects_second_call(): void
    {
        $settlement = BookingSettlement::assign(
            bookingId: 1,
            beneficiaryAccountId: 2,
            beneficiaryRole: BeneficiaryRole::MainAgency,
            amountOwed: Money::fromMinorUnits(100, 'TND'),
        );

        $settlement->revoke();
        self::assertFalse($settlement->isActive());

        $this->expectException(InvalidBookingSettlementException::class);
        $settlement->revoke();
    }

    #[Test]
    public function entity_has_no_booking_recalculation_api(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(BookingSettlement::class))->getMethods(),
        );

        self::assertNotContains('recalculateTotals', $methods);
        self::assertNotContains('applyToBooking', $methods);
    }
}

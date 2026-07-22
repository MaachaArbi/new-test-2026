<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Exception\InvalidBookingStateException;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\ExchangeRate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    #[Test]
    public function create_ok_with_null_supplier_and_money_fields(): void
    {
        $booking = self::createBooking(
            endDate: new DateTimeImmutable('2026-08-05'),
            supplierAccountId: null,
        );

        self::assertNull($booking->id());
        self::assertNull($booking->supplierAccountId());
        self::assertSame(1, $booking->folderId());
        self::assertSame('hotel', $booking->serviceTypeCode()->toString());
        self::assertSame(date('Y-m-d'), $booking->bookingDate()->format('Y-m-d'));
        self::assertSame('TND', $booking->achatCurrencyCode());
        self::assertSame('EUR', $booking->venteCurrencyCode());
        self::assertSame('1.234567', $booking->achatExchangeRate()->toString());
        self::assertSame('1', $booking->venteExchangeRate()->toString());
        self::assertSame(10050, $booking->totalAchatAmount()->amount());
        self::assertSame('TND', $booking->totalAchatAmount()->currencyCode());
        self::assertSame(15000, $booking->totalVenteAmount()->amount());
        self::assertSame('EUR', $booking->totalVenteAmount()->currencyCode());
        self::assertSame(4950, $booking->margeAgenceAmount()->amount());
        self::assertSame(0, $booking->margeDistributeurAmount()->amount());
        self::assertSame(0, $booking->paidAmount()->amount());
        self::assertSame(PaymentStatus::Unpaid, $booking->paymentStatus());
    }

    #[Test]
    public function create_rejects_end_before_start(): void
    {
        try {
            self::createBooking(
                startDate: new DateTimeImmutable('2026-08-10'),
                endDate: new DateTimeImmutable('2026-08-01'),
                channelCode: 'web',
            );
            self::fail('Expected InvalidBookingStateException');
        } catch (InvalidBookingStateException $exception) {
            self::assertSame('booking.invalid_dates', $exception->errorCode());
        }
    }

    private static function createBooking(
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?int $supplierAccountId = null,
        string $channelCode = 'backoffice',
    ): Booking {
        return Booking::create(
            1,
            BookingServiceTypeCode::fromString('hotel'),
            BookingStatusCode::fromString('draft'),
            10,
            $supplierAccountId,
            20,
            $startDate ?? new DateTimeImmutable('2026-08-01'),
            $endDate,
            BookingChannelCode::fromString($channelCode),
            'TND',
            'EUR',
            ExchangeRate::fromString('1.234567'),
            ExchangeRate::fromString('1'),
            Money::fromMinorUnits(10050, 'TND'),
            Money::fromMinorUnits(15000, 'EUR'),
            Money::fromMinorUnits(4950, 'EUR'),
            Money::fromMinorUnits(0, 'EUR'),
            Money::fromMinorUnits(0, 'EUR'),
            PaymentStatus::Unpaid,
        );
    }
}

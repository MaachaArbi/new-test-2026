<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Exception\BookingStatusUnchangedException;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\ExchangeRate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingStatusTransitionTest extends TestCase
{
    #[Test]
    public function transition_to_different_status_ok(): void
    {
        $booking = self::bookingWithStatus('draft');

        $booking->transitionTo(BookingStatusCode::fromString('confirmed'));

        self::assertSame('confirmed', $booking->statusCode()->toString());
    }

    #[Test]
    public function transition_to_same_status_raises(): void
    {
        $booking = self::bookingWithStatus('confirmed');

        try {
            $booking->transitionTo(BookingStatusCode::fromString('confirmed'));
            self::fail('Expected BookingStatusUnchangedException');
        } catch (BookingStatusUnchangedException $exception) {
            self::assertSame('booking.status_unchanged', $exception->errorCode());
            self::assertSame('confirmed', $exception->context()['status_code']);
        }
    }

    #[Test]
    public function transition_from_final_to_non_final_ok_no_matrix(): void
    {
        // Prouve l'absence de matrice : completed (is_final=true en référentiel)
        // peut revenir à draft (non-final). Aucune restriction Domain.
        $booking = self::bookingWithStatus('completed');

        $booking->transitionTo(BookingStatusCode::fromString('draft'));

        self::assertSame('draft', $booking->statusCode()->toString());
    }

    private static function bookingWithStatus(string $status): Booking
    {
        return Booking::create(
            1,
            BookingServiceTypeCode::fromString('hotel'),
            BookingStatusCode::fromString($status),
            10,
            null,
            20,
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-05'),
            BookingChannelCode::fromString('backoffice'),
            'TND',
            'TND',
            ExchangeRate::fromString('1'),
            ExchangeRate::fromString('1'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            PaymentStatus::Unpaid,
        );
    }
}

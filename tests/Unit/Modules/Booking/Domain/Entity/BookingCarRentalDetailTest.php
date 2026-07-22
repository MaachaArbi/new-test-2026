<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\BookingCarRentalDetail;
use App\Modules\Booking\Domain\Exception\InvalidBookingCarRentalDetailException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingCarRentalDetailTest extends TestCase
{
    #[Test]
    public function create_rejects_dropoff_before_pickup(): void
    {
        try {
            BookingCarRentalDetail::create(
                bookingId: 1,
                pickupAt: new DateTimeImmutable('2026-06-25 15:00:00'),
                dropoffAt: new DateTimeImmutable('2026-06-25 10:00:00'),
            );
            self::fail('Expected InvalidBookingCarRentalDetailException');
        } catch (InvalidBookingCarRentalDetailException $exception) {
            self::assertSame('booking_car_rental_detail.invalid_dates', $exception->errorCode());
        }
    }

    #[Test]
    public function create_allows_equal_pickup_and_dropoff(): void
    {
        $at = new DateTimeImmutable('2026-06-25 15:00:00');
        $detail = BookingCarRentalDetail::create(1, pickupAt: $at, dropoffAt: $at);

        self::assertSame($at, $detail->pickupAt());
        self::assertSame($at, $detail->dropoffAt());
    }

    #[Test]
    public function create_allows_only_pickup_without_dropoff(): void
    {
        $detail = BookingCarRentalDetail::create(
            1,
            pickupAt: new DateTimeImmutable('2026-06-25 15:00:00'),
        );

        self::assertNotNull($detail->pickupAt());
        self::assertNull($detail->dropoffAt());
    }
}

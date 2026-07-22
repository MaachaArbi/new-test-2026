<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\BookingTransportSegment;
use App\Modules\Booking\Domain\Exception\InvalidBookingTransportSegmentException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingTransportSegmentTest extends TestCase
{
    #[Test]
    public function create_rejects_arrival_before_departure(): void
    {
        try {
            BookingTransportSegment::create(
                1,
                new DateTimeImmutable('2026-10-01 10:00:00'),
                new DateTimeImmutable('2026-10-01 08:00:00'),
            );
            self::fail('Expected InvalidBookingTransportSegmentException');
        } catch (InvalidBookingTransportSegmentException $exception) {
            self::assertSame('booking_transport_segment.invalid_dates', $exception->errorCode());
        }
    }

    #[Test]
    public function create_allows_equal_departure_and_arrival(): void
    {
        $at = new DateTimeImmutable('2026-10-01 10:00:00');
        $segment = BookingTransportSegment::create(1, $at, $at, 1, 'TU');

        self::assertSame(1, $segment->sequenceNumber());
        self::assertSame('TU', $segment->carrierCode());
    }
}

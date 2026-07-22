<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\ExchangeRate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingWorkflowTest extends TestCase
{
    #[Test]
    public function create_defaults_workflow_flags_cleared(): void
    {
        $booking = self::freshBooking();

        self::assertFalse($booking->isOnRequest());
        self::assertNull($booking->assignedAgentAccountId());
        self::assertNull($booking->assignedAt());
        self::assertFalse($booking->isLocked());
        self::assertFalse($booking->isDisputed());
        self::assertNull($booking->supplierStatusLabel());
    }

    #[Test]
    public function mark_and_clear_on_request(): void
    {
        $booking = self::freshBooking();

        $booking->markAsOnRequest();
        self::assertTrue($booking->isOnRequest());

        $booking->clearOnRequest();
        self::assertFalse($booking->isOnRequest());
    }

    #[Test]
    public function assign_to_agent_sets_id_and_timestamp(): void
    {
        $booking = self::freshBooking();
        $before = new DateTimeImmutable('now');

        $booking->assignToAgent(42);

        self::assertSame(42, $booking->assignedAgentAccountId());
        self::assertNotNull($booking->assignedAt());
        self::assertGreaterThanOrEqual(
            $before->getTimestamp(),
            $booking->assignedAt()->getTimestamp(),
        );
    }

    #[Test]
    public function assign_to_agent_reassigns_without_error(): void
    {
        $booking = self::freshBooking();
        $booking->assignToAgent(10);
        $firstAt = $booking->assignedAt();

        $booking->assignToAgent(99);

        self::assertSame(99, $booking->assignedAgentAccountId());
        self::assertNotNull($booking->assignedAt());
        self::assertGreaterThanOrEqual(
            $firstAt?->getTimestamp() ?? 0,
            $booking->assignedAt()->getTimestamp(),
        );
    }

    #[Test]
    public function unassign_clears_agent_and_timestamp(): void
    {
        $booking = self::freshBooking();
        $booking->assignToAgent(7);
        $booking->unassign();

        self::assertNull($booking->assignedAgentAccountId());
        self::assertNull($booking->assignedAt());
    }

    #[Test]
    public function lock_and_unlock(): void
    {
        $booking = self::freshBooking();

        $booking->lock();
        self::assertTrue($booking->isLocked());

        $booking->unlock();
        self::assertFalse($booking->isLocked());
    }

    #[Test]
    public function mark_and_clear_dispute(): void
    {
        $booking = self::freshBooking();

        $booking->markAsDisputed();
        self::assertTrue($booking->isDisputed());

        $booking->clearDispute();
        self::assertFalse($booking->isDisputed());
    }

    #[Test]
    public function update_supplier_status_label_set_and_clear(): void
    {
        $booking = self::freshBooking();

        $booking->updateSupplierStatusLabel('OK-HOTEL');
        self::assertSame('OK-HOTEL', $booking->supplierStatusLabel());

        $booking->updateSupplierStatusLabel(null);
        self::assertNull($booking->supplierStatusLabel());
    }

    private static function freshBooking(): Booking
    {
        return Booking::create(
            1,
            BookingServiceTypeCode::fromString('hotel'),
            BookingStatusCode::fromString('confirmed'),
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

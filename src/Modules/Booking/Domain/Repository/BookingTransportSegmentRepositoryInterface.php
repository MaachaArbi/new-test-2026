<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingTransportSegment;

interface BookingTransportSegmentRepositoryInterface
{
    /**
     * @return list<BookingTransportSegment> triés par sequence_number ASC
     */
    public function findByBookingId(int $bookingId): array;

    /** ADR-003 : existence ciblée (DBAL), pas de chargement de collection. */
    public function belongsToBooking(int $segmentId, int $bookingId): bool;

    public function save(BookingTransportSegment $segment): void;
}

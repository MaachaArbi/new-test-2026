<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingTransportSegment;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingTransportSegmentRepository implements BookingTransportSegmentRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<BookingTransportSegment>
     */
    public function findByBookingId(int $bookingId): array
    {
        /** @var list<BookingTransportSegment> $segments */
        $segments = $this->unitOfWork->createQueryBuilder()
            ->select('segment')
            ->from(BookingTransportSegment::class, 'segment')
            ->andWhere('segment.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('segment.sequenceNumber', 'ASC')
            ->addOrderBy('segment.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $segments;
    }

    public function belongsToBooking(int $segmentId, int $bookingId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_transport_segment WHERE id = :segmentId AND booking_id = :bookingId',
            ['segmentId' => $segmentId, 'bookingId' => $bookingId],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(BookingTransportSegment $segment): void
    {
        $this->unitOfWork->persist($segment);
    }
}

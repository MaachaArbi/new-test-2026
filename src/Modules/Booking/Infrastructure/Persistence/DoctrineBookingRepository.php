<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use ReflectionProperty;

/**
 * Persistance Booking — PK composite (id, booking_date).
 *
 * Doctrine refuse IDENTITY sur clé composite (MappingException).
 * Stratégie : pré-assignation de id via nextval(séquence IDENTITY) +
 * mapping sans generator, avant persist/flush.
 */
final class DoctrineBookingRepository implements BookingRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?Booking
    {
        // EntityManager::find(Booking::class, $id) lève / refuse un id
        // scalaire sur PK composite — voir journal + BookingCompositePkTest.
        /** @var Booking|null $booking */
        $booking = $this->unitOfWork->createQueryBuilder()
            ->select('booking')
            ->from(Booking::class, 'booking')
            ->andWhere('booking.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $booking;
    }

    public function findByPublicId(PublicId $publicId): ?Booking
    {
        /** @var Booking|null $booking */
        $booking = $this->unitOfWork->createQueryBuilder()
            ->select('booking')
            ->from(Booking::class, 'booking')
            ->andWhere('booking.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $booking;
    }

    public function save(Booking $booking): void
    {
        if ($booking->id() === null) {
            $this->assignNextIdentity($booking);
        }

        $this->unitOfWork->persist($booking);
    }

    private function assignNextIdentity(Booking $booking): void
    {
        $raw = $this->connection->fetchOne(
            "SELECT nextval(pg_get_serial_sequence('booking', 'id'))",
        );
        if (!is_numeric($raw)) {
            throw new \RuntimeException('Unable to allocate booking id from sequence.');
        }

        $property = new ReflectionProperty(Booking::class, 'id');
        $property->setValue($booking, (int) $raw);
    }
}

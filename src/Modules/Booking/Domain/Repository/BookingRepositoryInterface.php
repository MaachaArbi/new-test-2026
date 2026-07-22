<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Shared\Domain\ValueObject\PublicId;

interface BookingRepositoryInterface
{
    /**
     * Lookup par id seul (unicité globale garantie par IDENTITY).
     * N'utilise PAS EntityManager::find() avec un id scalaire — PK composite
     * (id, booking_date) ; implémentation via QueryBuilder.
     */
    public function findById(int $id): ?Booking;

    /**
     * Lookup par public_id seul (pas $em->find() — PK composite).
     * Index SQL composite avec booking_date : pas d'unicité stricte sur
     * public_id seul, mais entropie UUID suffisante en pratique.
     */
    public function findByPublicId(PublicId $publicId): ?Booking;

    public function save(Booking $booking): void;
}

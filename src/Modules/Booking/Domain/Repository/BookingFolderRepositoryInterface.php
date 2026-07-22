<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Shared\Domain\ValueObject\PublicId;

interface BookingFolderRepositoryInterface
{
    public function findById(int $id): ?BookingFolder;

    public function findByPublicId(PublicId $publicId): ?BookingFolder;

    public function existsByReferenceCode(string $referenceCode): bool;

    public function save(BookingFolder $folder): void;

    /**
     * Persiste un soft-delete Domain ({@see BookingFolder::delete()}).
     * Aucun DELETE SQL — uniquement flush de deleted_at.
     */
    public function delete(BookingFolder $folder): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingFolderRepository implements BookingFolderRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?BookingFolder
    {
        /** @var BookingFolder|null $folder */
        $folder = $this->unitOfWork->find(BookingFolder::class, $id);

        return $folder;
    }

    public function findByPublicId(PublicId $publicId): ?BookingFolder
    {
        /** @var BookingFolder|null $folder */
        $folder = $this->unitOfWork->createQueryBuilder()
            ->select('folder')
            ->from(BookingFolder::class, 'folder')
            ->andWhere('folder.publicId = :publicId')
            ->andWhere('folder.deletedAt IS NULL')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $folder;
    }

    public function existsByReferenceCode(string $referenceCode): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_folder WHERE reference_code = :referenceCode LIMIT 1',
            ['referenceCode' => $referenceCode],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(BookingFolder $folder): void
    {
        $this->unitOfWork->persist($folder);
    }

    public function delete(BookingFolder $folder): void
    {
        // Commit (flush) is the caller's responsibility.
    }
}

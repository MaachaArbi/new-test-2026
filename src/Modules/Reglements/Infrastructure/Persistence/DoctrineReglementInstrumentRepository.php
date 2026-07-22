<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Persistence;

use App\Modules\Reglements\Domain\Entity\ReglementInstrument;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineReglementInstrumentRepository implements ReglementInstrumentRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findById(int $id): ?ReglementInstrument
    {
        /** @var ReglementInstrument|null $instrument */
        $instrument = $this->unitOfWork->find(ReglementInstrument::class, $id);

        return $instrument;
    }

    public function findByPublicId(PublicId $publicId): ?ReglementInstrument
    {
        /** @var ReglementInstrument|null $instrument */
        $instrument = $this->unitOfWork->createQueryBuilder()
            ->select('i')
            ->from(ReglementInstrument::class, 'i')
            ->andWhere('i.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $instrument;
    }

    public function save(ReglementInstrument $instrument): void
    {
        $this->unitOfWork->persist($instrument);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Persistence;

use App\Modules\Settlement\Domain\Entity\SettlementInstrument;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineSettlementInstrumentRepository implements SettlementInstrumentRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findById(int $id): ?SettlementInstrument
    {
        /** @var SettlementInstrument|null $instrument */
        $instrument = $this->unitOfWork->find(SettlementInstrument::class, $id);

        return $instrument;
    }

    public function findByPublicId(PublicId $publicId): ?SettlementInstrument
    {
        /** @var SettlementInstrument|null $instrument */
        $instrument = $this->unitOfWork->createQueryBuilder()
            ->select('i')
            ->from(SettlementInstrument::class, 'i')
            ->andWhere('i.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $instrument;
    }

    public function save(SettlementInstrument $instrument): void
    {
        $this->unitOfWork->persist($instrument);
    }
}

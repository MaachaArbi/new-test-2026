<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Persistence;

use App\Modules\Settlement\Domain\Entity\SettlementEntryType;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineSettlementEntryTypeRepository implements SettlementEntryTypeRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByCode(string $code): ?SettlementEntryType
    {
        /** @var SettlementEntryType|null $entryType */
        $entryType = $this->unitOfWork->createQueryBuilder()
            ->select('t')
            ->from(SettlementEntryType::class, 't')
            ->andWhere('t.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();

        return $entryType;
    }
}

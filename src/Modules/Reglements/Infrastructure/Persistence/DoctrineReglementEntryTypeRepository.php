<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Persistence;

use App\Modules\Reglements\Domain\Entity\ReglementEntryType;
use App\Modules\Reglements\Domain\Repository\ReglementEntryTypeRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineReglementEntryTypeRepository implements ReglementEntryTypeRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByCode(string $code): ?ReglementEntryType
    {
        /** @var ReglementEntryType|null $entryType */
        $entryType = $this->unitOfWork->createQueryBuilder()
            ->select('t')
            ->from(ReglementEntryType::class, 't')
            ->andWhere('t.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();

        return $entryType;
    }
}

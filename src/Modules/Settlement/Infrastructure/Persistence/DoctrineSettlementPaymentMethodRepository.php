<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Persistence;

use App\Modules\Settlement\Domain\Entity\SettlementPaymentMethod;
use App\Modules\Settlement\Domain\Repository\SettlementPaymentMethodRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineSettlementPaymentMethodRepository implements SettlementPaymentMethodRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findById(int $id): ?SettlementPaymentMethod
    {
        /** @var SettlementPaymentMethod|null $method */
        $method = $this->unitOfWork->find(SettlementPaymentMethod::class, $id);

        return $method;
    }

    public function findByCode(string $code): ?SettlementPaymentMethod
    {
        /** @var SettlementPaymentMethod|null $method */
        $method = $this->unitOfWork->createQueryBuilder()
            ->select('m')
            ->from(SettlementPaymentMethod::class, 'm')
            ->andWhere('m.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();

        return $method;
    }
}

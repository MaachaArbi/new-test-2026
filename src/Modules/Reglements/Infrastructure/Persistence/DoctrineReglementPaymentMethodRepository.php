<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Persistence;

use App\Modules\Reglements\Domain\Entity\ReglementPaymentMethod;
use App\Modules\Reglements\Domain\Repository\ReglementPaymentMethodRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineReglementPaymentMethodRepository implements ReglementPaymentMethodRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByCode(string $code): ?ReglementPaymentMethod
    {
        /** @var ReglementPaymentMethod|null $method */
        $method = $this->unitOfWork->createQueryBuilder()
            ->select('m')
            ->from(ReglementPaymentMethod::class, 'm')
            ->andWhere('m.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();

        return $method;
    }
}

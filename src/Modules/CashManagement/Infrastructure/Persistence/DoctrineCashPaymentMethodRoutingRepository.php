<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Infrastructure\Persistence;

use App\Modules\CashManagement\Domain\Entity\CashPaymentMethodRouting;
use App\Modules\CashManagement\Domain\Repository\CashPaymentMethodRoutingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineCashPaymentMethodRoutingRepository implements CashPaymentMethodRoutingRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByPaymentMethodId(int $paymentMethodId): ?CashPaymentMethodRouting
    {
        $found = $this->unitOfWork->find(CashPaymentMethodRouting::class, $paymentMethodId);

        return $found instanceof CashPaymentMethodRouting ? $found : null;
    }

    public function create(CashPaymentMethodRouting $routing): void
    {
        $this->unitOfWork->persist($routing);
    }

    public function update(CashPaymentMethodRouting $routing): void
    {
        // dirty-checking Doctrine — commit = Handler.
    }
}

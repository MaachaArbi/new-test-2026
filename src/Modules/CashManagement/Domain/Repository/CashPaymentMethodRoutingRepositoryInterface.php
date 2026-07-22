<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Repository;

use App\Modules\CashManagement\Domain\Entity\CashPaymentMethodRouting;

interface CashPaymentMethodRoutingRepositoryInterface
{
    public function findByPaymentMethodId(int $paymentMethodId): ?CashPaymentMethodRouting;

    public function create(CashPaymentMethodRouting $routing): void;

    /**
     * Mutation Domain déjà appliquée (dirty-checking Doctrine).
     * Commit = responsabilité de l'appelant (UnitOfWork).
     */
    public function update(CashPaymentMethodRouting $routing): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Repository;

use App\Modules\Settlement\Domain\Entity\SettlementPaymentMethod;

interface SettlementPaymentMethodRepositoryInterface
{
    public function findById(int $id): ?SettlementPaymentMethod;

    public function findByCode(string $code): ?SettlementPaymentMethod;
}

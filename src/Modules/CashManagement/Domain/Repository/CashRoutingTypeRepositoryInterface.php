<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Repository;

use App\Modules\CashManagement\Domain\Entity\CashRoutingType;

interface CashRoutingTypeRepositoryInterface
{
    public function findByCode(string $code): ?CashRoutingType;
}

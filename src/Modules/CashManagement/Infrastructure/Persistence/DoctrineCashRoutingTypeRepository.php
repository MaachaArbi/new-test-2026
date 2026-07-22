<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Infrastructure\Persistence;

use App\Modules\CashManagement\Domain\Entity\CashRoutingType;
use App\Modules\CashManagement\Domain\Repository\CashRoutingTypeRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineCashRoutingTypeRepository implements CashRoutingTypeRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByCode(string $code): ?CashRoutingType
    {
        $found = $this->unitOfWork->find(CashRoutingType::class, $code);

        return $found instanceof CashRoutingType ? $found : null;
    }
}

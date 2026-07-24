<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Repository;

use App\Modules\Settlement\Domain\Entity\SettlementEntryType;

interface SettlementEntryTypeRepositoryInterface
{
    public function findByCode(string $code): ?SettlementEntryType;
}

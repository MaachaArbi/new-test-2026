<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Repository;

use App\Modules\CashManagement\Domain\Entity\CashSession;

interface CashSessionRepositoryInterface
{
    public function findById(int $id): ?CashSession;
}

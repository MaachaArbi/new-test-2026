<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Repository;

use App\Modules\Reglements\Domain\Entity\ReglementPaymentMethod;

interface ReglementPaymentMethodRepositoryInterface
{
    public function findByCode(string $code): ?ReglementPaymentMethod;
}

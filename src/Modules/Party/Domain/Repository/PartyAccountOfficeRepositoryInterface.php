<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccountOffice;

interface PartyAccountOfficeRepositoryInterface
{
    public function findByAccountId(int $accountId): ?PartyAccountOffice;

    public function existsByAccountId(int $accountId): bool;

    public function existsByOfficeCode(string $code): bool;

    public function save(PartyAccountOffice $office): void;
}

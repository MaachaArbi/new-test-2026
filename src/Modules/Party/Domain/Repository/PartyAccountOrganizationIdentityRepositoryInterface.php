<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccountOrganizationIdentity;

interface PartyAccountOrganizationIdentityRepositoryInterface
{
    public function findByAccountId(int $accountId): ?PartyAccountOrganizationIdentity;

    public function existsByAccountId(int $accountId): bool;

    public function save(PartyAccountOrganizationIdentity $identity): void;
}

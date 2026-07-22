<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccountGroup;
use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;

/**
 * Persistance des groupes de comptes (agrégat party_account_group).
 */
interface PartyAccountGroupRepositoryInterface
{
    public function existsByTypeAndName(PartyAccountGroupTypeCode $type, string $name): bool;

    public function findById(int $id): ?PartyAccountGroup;

    public function save(PartyAccountGroup $group): void;
}

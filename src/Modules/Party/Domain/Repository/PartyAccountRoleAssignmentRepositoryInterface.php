<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccountRoleAssignment;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;

/**
 * Persistance des assignations de rôle historisées.
 */
interface PartyAccountRoleAssignmentRepositoryInterface
{
    public function findById(int $id): ?PartyAccountRoleAssignment;

    public function hasActiveRole(int $accountId, PartyRoleCode $roleCode): bool;

    public function assign(PartyAccountRoleAssignment $assignment): void;

    /**
     * Flush après mutation Domain (validTo).
     *
     * Précondition : $assignment DOIT être une instance gérée par l'EntityManager
     * courant — typiquement obtenue via findById() dans la même requête HTTP/CLI.
     * Ne jamais passer une instance reconstruite ou détachée : flush() serait alors
     * un no-op silencieux (aucune persistence de valid_to).
     */
    public function revoke(PartyAccountRoleAssignment $assignment): void;
}

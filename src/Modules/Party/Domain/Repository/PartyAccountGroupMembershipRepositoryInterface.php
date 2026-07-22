<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccountGroupMembership;

/**
 * Persistance des appartenances compte ↔ groupe historisées.
 */
interface PartyAccountGroupMembershipRepositoryInterface
{
    public function findById(int $id): ?PartyAccountGroupMembership;

    public function hasActiveMembership(int $accountId, int $groupId): bool;

    public function assign(PartyAccountGroupMembership $membership): void;

    /**
     * Flush après mutation Domain (validTo).
     *
     * Précondition : $assignment DOIT être une instance gérée par l'EntityManager
     * courant — typiquement obtenue via findById() dans la même requête HTTP/CLI.
     * Ne jamais passer une instance reconstruite ou détachée : flush() serait alors
     * un no-op silencieux (aucune persistence de valid_to).
     */
    public function revoke(PartyAccountGroupMembership $assignment): void;
}

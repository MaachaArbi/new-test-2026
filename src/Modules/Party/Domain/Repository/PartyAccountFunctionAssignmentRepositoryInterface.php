<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccountFunctionAssignment;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;

/**
 * Persistance des assignations de fonction historisées.
 */
interface PartyAccountFunctionAssignmentRepositoryInterface
{
    public function findById(int $id): ?PartyAccountFunctionAssignment;

    public function hasActiveFunction(
        int $personAccountId,
        int $organizationAccountId,
        PartyFunctionCode $functionCode,
    ): bool;

    public function assign(PartyAccountFunctionAssignment $assignment): void;

    /**
     * Flush après mutation Domain (validTo).
     *
     * Précondition : $assignment DOIT être une instance gérée par l'EntityManager
     * courant — typiquement obtenue via findById() dans la même requête HTTP/CLI.
     * Ne jamais passer une instance reconstruite ou détachée : flush() serait alors
     * un no-op silencieux (aucune persistence de valid_to).
     */
    public function revoke(PartyAccountFunctionAssignment $assignment): void;
}

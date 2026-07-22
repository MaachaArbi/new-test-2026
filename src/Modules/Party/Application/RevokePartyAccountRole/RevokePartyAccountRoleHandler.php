<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\RevokePartyAccountRole;

use App\Modules\Party\Domain\Entity\PartyAccountRoleAssignment;
use App\Modules\Party\Domain\Exception\PartyAccountRoleAssignmentNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRoleAssignmentRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : révoquer une assignation de rôle (valid_to).
 *
 * Charge via findById(), mute Domain, flush via repository->revoke() —
 * même instance gérée (précondition documentée sur l'interface).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 */
final class RevokePartyAccountRoleHandler
{
    public function __construct(
        private readonly PartyAccountRoleAssignmentRepositoryInterface $roleAssignmentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(RevokePartyAccountRoleCommand $command): PartyAccountRoleAssignment
    {
        $assignment = $this->roleAssignmentRepository->findById($command->assignmentId);
        if ($assignment === null) {
            throw PartyAccountRoleAssignmentNotFoundException::forId($command->assignmentId);
        }

        $assignment->revoke();
        $this->roleAssignmentRepository->revoke($assignment);
        $this->unitOfWork->commit();

        return $assignment;
    }
}

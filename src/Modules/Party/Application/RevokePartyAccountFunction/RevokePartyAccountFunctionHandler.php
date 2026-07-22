<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\RevokePartyAccountFunction;

use App\Modules\Party\Domain\Entity\PartyAccountFunctionAssignment;
use App\Modules\Party\Domain\Exception\PartyAccountFunctionAssignmentNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountFunctionAssignmentRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : révoquer une assignation de fonction (valid_to).
 *
 * Charge via findById(), mute Domain, flush via repository->revoke() —
 * même instance gérée (précondition documentée sur l'interface).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 */
final class RevokePartyAccountFunctionHandler
{
    public function __construct(
        private readonly PartyAccountFunctionAssignmentRepositoryInterface $functionAssignmentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(RevokePartyAccountFunctionCommand $command): PartyAccountFunctionAssignment
    {
        $assignment = $this->functionAssignmentRepository->findById($command->assignmentId);
        if ($assignment === null) {
            throw PartyAccountFunctionAssignmentNotFoundException::forId($command->assignmentId);
        }

        $assignment->revoke();
        $this->functionAssignmentRepository->revoke($assignment);
        $this->unitOfWork->commit();

        return $assignment;
    }
}

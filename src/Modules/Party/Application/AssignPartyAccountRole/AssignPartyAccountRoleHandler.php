<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\AssignPartyAccountRole;

use App\Modules\Party\Domain\Entity\PartyAccountRoleAssignment;
use App\Modules\Party\Domain\Exception\PartyAccountRoleAlreadyActiveException;
use App\Modules\Party\Domain\Repository\PartyAccountRoleAssignmentRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : assigner un rôle — vérification explicite du doublon actif
 * AVANT écriture (la contrainte uq_party_account_role_active n'est qu'un filet DB).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 * ADR-003 (command bus) sera branché plus tard ; le futur Controller pourra
 * soit appeler ce handler directement, soit basculer vers le bus sans changer
 * la logique métier.
 */
final class AssignPartyAccountRoleHandler
{
    public function __construct(
        private readonly PartyAccountRoleAssignmentRepositoryInterface $roleAssignmentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AssignPartyAccountRoleCommand $command): PartyAccountRoleAssignment
    {
        $roleCode = PartyRoleCode::fromString($command->roleCode);

        if ($this->roleAssignmentRepository->hasActiveRole($command->accountId, $roleCode)) {
            throw PartyAccountRoleAlreadyActiveException::forAccountAndRole(
                $command->accountId,
                $roleCode->toString(),
            );
        }

        $assignment = PartyAccountRoleAssignment::assign(
            $command->accountId,
            $roleCode,
            $command->createdBy,
        );

        $this->roleAssignmentRepository->assign($assignment);
        $this->unitOfWork->commit();

        return $assignment;
    }
}

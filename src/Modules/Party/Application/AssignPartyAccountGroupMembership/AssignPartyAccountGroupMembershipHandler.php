<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\AssignPartyAccountGroupMembership;

use App\Modules\Party\Domain\Entity\PartyAccountGroupMembership;
use App\Modules\Party\Domain\Exception\PartyAccountGroupMembershipAlreadyActiveException;
use App\Modules\Party\Domain\Repository\PartyAccountGroupMembershipRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : assigner une appartenance — vérification explicite du doublon actif
 * AVANT écriture (la contrainte uq_party_account_group_member_active n'est qu'un filet DB).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 * ADR-003 (command bus) sera branché plus tard ; le futur Controller pourra
 * soit appeler ce handler directement, soit basculer vers le bus sans changer
 * la logique métier.
 */
final class AssignPartyAccountGroupMembershipHandler
{
    public function __construct(
        private readonly PartyAccountGroupMembershipRepositoryInterface $membershipRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AssignPartyAccountGroupMembershipCommand $command): PartyAccountGroupMembership
    {
        if ($this->membershipRepository->hasActiveMembership($command->accountId, $command->groupId)) {
            throw PartyAccountGroupMembershipAlreadyActiveException::forAccountAndGroup(
                $command->accountId,
                $command->groupId,
            );
        }

        $membership = PartyAccountGroupMembership::assign(
            $command->accountId,
            $command->groupId,
            $command->createdBy,
        );

        $this->membershipRepository->assign($membership);
        $this->unitOfWork->commit();

        return $membership;
    }
}

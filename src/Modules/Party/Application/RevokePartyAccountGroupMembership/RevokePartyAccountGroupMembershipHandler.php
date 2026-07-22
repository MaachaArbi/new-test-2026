<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\RevokePartyAccountGroupMembership;

use App\Modules\Party\Domain\Entity\PartyAccountGroupMembership;
use App\Modules\Party\Domain\Exception\PartyAccountGroupMembershipNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountGroupMembershipRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : révoquer une membership de groupe (valid_to).
 *
 * Charge via findById(), mute Domain, flush via repository->revoke() —
 * même instance gérée (précondition documentée sur l'interface).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 */
final class RevokePartyAccountGroupMembershipHandler
{
    public function __construct(
        private readonly PartyAccountGroupMembershipRepositoryInterface $membershipRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(RevokePartyAccountGroupMembershipCommand $command): PartyAccountGroupMembership
    {
        $membership = $this->membershipRepository->findById($command->membershipId);
        if ($membership === null) {
            throw PartyAccountGroupMembershipNotFoundException::forId($command->membershipId);
        }

        $membership->revoke();
        $this->membershipRepository->revoke($membership);
        $this->unitOfWork->commit();

        return $membership;
    }
}

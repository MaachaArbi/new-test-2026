<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\SetPartyAccountOrganizationIdentity;

use App\Modules\Party\Domain\Entity\PartyAccountOrganizationIdentity;
use App\Modules\Party\Domain\Exception\PartyAccountMustBeOrganizationException;
use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountOrganizationIdentityRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyAccountNature;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer organization_identity — nature=organization obligatoire.
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 */
final class SetPartyAccountOrganizationIdentityHandler
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $accountRepository,
        private readonly PartyAccountOrganizationIdentityRepositoryInterface $identityRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(
        SetPartyAccountOrganizationIdentityCommand $command,
    ): PartyAccountOrganizationIdentity {
        $this->assertAccountIsOrganization($command->accountId);

        $identity = PartyAccountOrganizationIdentity::create(
            $command->accountId,
            $command->taxId,
            $command->tradeRegister,
            $command->legalFormCode,
            $command->isVatSubject,
            $command->website,
        );

        $this->identityRepository->save($identity);
        $this->unitOfWork->commit();

        return $identity;
    }

    private function assertAccountIsOrganization(int $accountId): void
    {
        $nature = $this->accountRepository->findNatureById($accountId);
        if ($nature === null) {
            throw PartyAccountNotFoundException::forId($accountId);
        }

        if ($nature !== PartyAccountNature::Organization->value) {
            throw PartyAccountMustBeOrganizationException::forAccount(
                $accountId,
                $nature,
            );
        }
    }
}

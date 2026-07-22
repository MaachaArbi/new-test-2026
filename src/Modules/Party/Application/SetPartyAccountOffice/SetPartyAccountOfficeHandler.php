<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\SetPartyAccountOffice;

use App\Modules\Party\Domain\Entity\PartyAccountOffice;
use App\Modules\Party\Domain\Exception\PartyAccountMustBeOrganizationException;
use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Exception\PartyAccountOfficeCodeAlreadyUsedException;
use App\Modules\Party\Domain\Repository\PartyAccountOfficeRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyAccountNature;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer office — nature=organization + unicité office_code.
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 */
final class SetPartyAccountOfficeHandler
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $accountRepository,
        private readonly PartyAccountOfficeRepositoryInterface $officeRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(SetPartyAccountOfficeCommand $command): PartyAccountOffice
    {
        $nature = $this->accountRepository->findNatureById($command->accountId);
        match (true) {
            $nature === null => throw PartyAccountNotFoundException::forId($command->accountId),
            $nature !== PartyAccountNature::Organization->value => throw PartyAccountMustBeOrganizationException::forAccount(
                $command->accountId,
                $nature,
            ),
            default => null,
        };

        if ($this->officeRepository->existsByOfficeCode($command->officeCode)) {
            throw PartyAccountOfficeCodeAlreadyUsedException::forCode($command->officeCode);
        }

        $office = PartyAccountOffice::create(
            $command->accountId,
            $command->officeCode,
            $command->defaultCurrencyCode,
        );

        $this->officeRepository->save($office);
        $this->unitOfWork->commit();

        return $office;
    }
}

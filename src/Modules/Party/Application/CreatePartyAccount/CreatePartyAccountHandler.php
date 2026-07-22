<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\CreatePartyAccount;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer un PartyAccount via les factories Domain.
 *
 * Service invocable (__invoke) — cohérent avec CreatePartyAccountGroup /
 * Assign* / Set* (pas de Messenger pour l'instant, ADR-003 plus tard).
 */
final class CreatePartyAccountHandler
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreatePartyAccountCommand $command): PartyAccount
    {
        $email = $command->email !== null
            ? Email::fromString($command->email)
            : null;

        $account = match ($command->nature) {
            'person' => PartyAccount::createPerson(
                $command->displayName,
                $email,
                $command->parentAccountId,
            ),
            'organization' => PartyAccount::createOrganization(
                $command->displayName,
                $email,
                $command->parentAccountId,
            ),
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported party account nature "%s".',
                $command->nature,
            )),
        };

        $this->partyAccountRepository->save($account);
        $this->unitOfWork->commit();

        return $account;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\UpdatePartyAccount;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Exception\PartyAccountNoChangesException;
use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : mise à jour partielle (displayName / isDisabled).
 */
final class UpdatePartyAccountHandler
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(UpdatePartyAccountCommand $command): PartyAccount
    {
        $hasDisplayNameChange = $command->hasDisplayName && $command->displayName !== null;
        $hasIsDisabledChange = $command->hasIsDisabled && $command->isDisabled !== null;

        if (!$hasDisplayNameChange && !$hasIsDisabledChange) {
            throw PartyAccountNoChangesException::create();
        }

        $account = $this->partyAccountRepository->findByPublicId(
            PublicId::fromString($command->publicId),
        );
        if ($account === null) {
            throw PartyAccountNotFoundException::forPublicId($command->publicId);
        }

        if ($hasDisplayNameChange) {
            /** @var string $displayName */
            $displayName = $command->displayName;
            $account->updateDisplayName($displayName);
        }

        if ($hasIsDisabledChange) {
            if ($command->isDisabled === true) {
                $account->disable();
            } else {
                $account->enable();
            }
        }

        $this->partyAccountRepository->save($account);
        $this->unitOfWork->commit();

        return $account;
    }
}

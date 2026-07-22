<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\DeletePartyAccount;

use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : soft-delete idempotent via PartyAccount::delete().
 */
final class DeletePartyAccountHandler
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(DeletePartyAccountCommand $command): DeletePartyAccountOutcome
    {
        $account = $this->partyAccountRepository->findByPublicIdIncludingDeleted(
            PublicId::fromString($command->publicId),
        );

        if ($account === null) {
            throw PartyAccountNotFoundException::forPublicId($command->publicId);
        }

        if ($account->isDeleted()) {
            return DeletePartyAccountOutcome::AlreadyDeleted;
        }

        $account->delete();
        $this->partyAccountRepository->delete($account);
        $this->unitOfWork->commit();

        return DeletePartyAccountOutcome::SoftDeleted;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\CreateSettlementMatching;

use App\Modules\Settlement\Domain\Entity\SettlementMatching;
use App\Modules\Settlement\Domain\Exception\SettlementLedgerEntryNotFoundException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingBookMismatchException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingExceedsCreditException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingExceedsDebitException;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementMatchingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Crée un lettrage. Plafonds crédit + débit (voir journal).
 * Ne touche jamais settlement_balance.
 */
final class CreateSettlementMatchingHandler
{
    public function __construct(
        private readonly SettlementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly SettlementMatchingRepositoryInterface $matchingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateSettlementMatchingCommand $command): SettlementMatching
    {
        $debit = $this->ledgerRepository->findById($command->debitEntryId);
        if ($debit === null) {
            throw SettlementLedgerEntryNotFoundException::forId($command->debitEntryId);
        }

        $credit = $this->ledgerRepository->findById($command->creditEntryId);
        if ($credit === null) {
            throw SettlementLedgerEntryNotFoundException::forId($command->creditEntryId);
        }

        if (
            $debit->partyAccountId() !== $credit->partyAccountId()
            || $debit->partyRole() !== $credit->partyRole()
            || $debit->currencyCode() !== $credit->currencyCode()
        ) {
            throw SettlementMatchingBookMismatchException::forEntries(
                $command->debitEntryId,
                $command->creditEntryId,
            );
        }

        $creditCapacity = abs($credit->amountMinor());
        $alreadyOnCredit = $this->matchingRepository->sumActiveMatchedForCreditEntry($command->creditEntryId);
        if ($alreadyOnCredit + $command->matchedAmountMinor > $creditCapacity) {
            throw SettlementMatchingExceedsCreditException::forCredit(
                $command->creditEntryId,
                $creditCapacity,
                $alreadyOnCredit,
                $command->matchedAmountMinor,
            );
        }

        $debitCapacity = abs($debit->amountMinor());
        $alreadyOnDebit = $this->matchingRepository->sumActiveMatchedForDebitEntry($command->debitEntryId);
        if ($alreadyOnDebit + $command->matchedAmountMinor > $debitCapacity) {
            throw SettlementMatchingExceedsDebitException::forDebit(
                $command->debitEntryId,
                $debitCapacity,
                $alreadyOnDebit,
                $command->matchedAmountMinor,
            );
        }

        $matching = SettlementMatching::match(
            debitEntryId: $command->debitEntryId,
            creditEntryId: $command->creditEntryId,
            matchedAmountMinor: $command->matchedAmountMinor,
            isAutomatic: $command->isAutomatic,
            matchGroup: $command->matchGroup,
            matchedBy: $command->matchedBy,
        );

        $this->matchingRepository->match($matching);
        $this->unitOfWork->commit();

        return $matching;
    }
}

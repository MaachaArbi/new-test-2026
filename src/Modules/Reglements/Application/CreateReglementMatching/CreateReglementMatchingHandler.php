<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\CreateReglementMatching;

use App\Modules\Reglements\Domain\Entity\ReglementMatching;
use App\Modules\Reglements\Domain\Exception\ReglementLedgerEntryNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingBookMismatchException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingExceedsCreditException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingExceedsDebitException;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementMatchingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Crée un lettrage. Plafonds crédit + débit (voir journal).
 * Ne touche jamais reglement_balance.
 */
final class CreateReglementMatchingHandler
{
    public function __construct(
        private readonly ReglementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly ReglementMatchingRepositoryInterface $matchingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateReglementMatchingCommand $command): ReglementMatching
    {
        $debit = $this->ledgerRepository->findById($command->debitEntryId);
        if ($debit === null) {
            throw ReglementLedgerEntryNotFoundException::forId($command->debitEntryId);
        }

        $credit = $this->ledgerRepository->findById($command->creditEntryId);
        if ($credit === null) {
            throw ReglementLedgerEntryNotFoundException::forId($command->creditEntryId);
        }

        if (
            $debit->partyAccountId() !== $credit->partyAccountId()
            || $debit->partyRole() !== $credit->partyRole()
            || $debit->currencyCode() !== $credit->currencyCode()
        ) {
            throw ReglementMatchingBookMismatchException::forEntries(
                $command->debitEntryId,
                $command->creditEntryId,
            );
        }

        $creditCapacity = abs($credit->amountMinor());
        $alreadyOnCredit = $this->matchingRepository->sumActiveMatchedForCreditEntry($command->creditEntryId);
        if ($alreadyOnCredit + $command->matchedAmountMinor > $creditCapacity) {
            throw ReglementMatchingExceedsCreditException::forCredit(
                $command->creditEntryId,
                $creditCapacity,
                $alreadyOnCredit,
                $command->matchedAmountMinor,
            );
        }

        $debitCapacity = abs($debit->amountMinor());
        $alreadyOnDebit = $this->matchingRepository->sumActiveMatchedForDebitEntry($command->debitEntryId);
        if ($alreadyOnDebit + $command->matchedAmountMinor > $debitCapacity) {
            throw ReglementMatchingExceedsDebitException::forDebit(
                $command->debitEntryId,
                $debitCapacity,
                $alreadyOnDebit,
                $command->matchedAmountMinor,
            );
        }

        $matching = ReglementMatching::match(
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

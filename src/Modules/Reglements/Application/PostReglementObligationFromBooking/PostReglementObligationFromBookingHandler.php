<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\PostReglementObligationFromBooking;

use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Exception\ReglementEntryTypeNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementEntryTypeRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;

/**
 * Poste une écriture obligation_vente par split actif.
 *
 * INSERT Domain-contrôlé (pas encore de reglement_post_obligation SQL) —
 * pattern mixte documenté : transfert = fonction SQL ; obligation = Domain.
 * Toutes les écritures d'un même booking → un seul commit UnitOfWork.
 */
final class PostReglementObligationFromBookingHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingPayerSplitRepositoryInterface $payerSplitRepository,
        private readonly ReglementEntryTypeRepositoryInterface $entryTypeRepository,
        private readonly ReglementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * @return list<ReglementLedgerEntry>
     */
    public function __invoke(PostReglementObligationFromBookingCommand $command): array
    {
        $booking = $this->bookingRepository->findById($command->bookingId);
        if ($booking === null) {
            throw BookingNotFoundException::forId($command->bookingId);
        }

        $entryType = $this->entryTypeRepository->findByCode('obligation_vente');
        if ($entryType === null || $entryType->id() === null) {
            throw ReglementEntryTypeNotFoundException::forCode('obligation_vente');
        }

        $splits = $this->payerSplitRepository->findByBookingId($command->bookingId, activeOnly: true);
        $currencyCode = $booking->venteCurrencyCode();
        $effectiveDate = new DateTimeImmutable('today');
        $entryTypeId = (int) $entryType->id();

        $posted = [];
        foreach ($splits as $split) {
            $entry = ReglementLedgerEntry::post(
                partyAccountId: $split->payerAccountId(),
                partyRole: InstrumentPartyRole::Client,
                currencyCode: $currencyCode,
                entryTypeId: $entryTypeId,
                amountMinor: $split->amount()->amount(),
                effectiveDate: $effectiveDate,
                bookingId: $command->bookingId,
                memo: sprintf('Obligation vente booking #%d', $command->bookingId),
                createdBy: $command->createdBy,
            );
            $this->ledgerRepository->append($entry);
            $posted[] = $entry;
        }

        $this->unitOfWork->commit();

        return $posted;
    }
}

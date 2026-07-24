<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\PostSettlementObligationFromBooking;

use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Exception\SettlementEntryTypeNotFoundException;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;

/**
 * Poste une écriture obligation_vente par split actif.
 *
 * INSERT Domain-contrôlé (pas encore de settlement_post_obligation SQL) —
 * pattern mixte documenté : transfert = fonction SQL ; obligation = Domain.
 * Toutes les écritures d'un même booking → un seul commit UnitOfWork.
 */
final class PostSettlementObligationFromBookingHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingPayerSplitRepositoryInterface $payerSplitRepository,
        private readonly SettlementEntryTypeRepositoryInterface $entryTypeRepository,
        private readonly SettlementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * @return list<SettlementLedgerEntry>
     */
    public function __invoke(PostSettlementObligationFromBookingCommand $command): array
    {
        $booking = $this->bookingRepository->findById($command->bookingId);
        if ($booking === null) {
            throw BookingNotFoundException::forId($command->bookingId);
        }

        $entryType = $this->entryTypeRepository->findByCode('obligation_vente');
        if ($entryType === null || $entryType->id() === null) {
            throw SettlementEntryTypeNotFoundException::forCode('obligation_vente');
        }

        $splits = $this->payerSplitRepository->findByBookingId($command->bookingId, activeOnly: true);
        $currencyCode = $booking->venteCurrencyCode();
        $effectiveDate = new DateTimeImmutable('today');
        $entryTypeId = (int) $entryType->id();

        $posted = [];
        foreach ($splits as $split) {
            $entry = SettlementLedgerEntry::post(
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

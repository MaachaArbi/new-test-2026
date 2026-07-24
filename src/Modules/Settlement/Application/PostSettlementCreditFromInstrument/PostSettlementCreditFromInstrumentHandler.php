<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\PostSettlementCreditFromInstrument;

use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Exception\SettlementEntryTypeNotFoundException;
use App\Modules\Settlement\Domain\Exception\SettlementInstrumentNotActiveException;
use App\Modules\Settlement\Domain\Exception\SettlementInstrumentNotFoundException;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Settlement\Domain\ValueObject\SettlementInstrumentStatus;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;

/**
 * Poste un crédit grand livre depuis un instrument Active.
 * amountMinor négatif (reglement_client / reglement_fournisseur, normal_sign=-1).
 */
final class PostSettlementCreditFromInstrumentHandler
{
    public function __construct(
        private readonly SettlementInstrumentRepositoryInterface $instrumentRepository,
        private readonly SettlementEntryTypeRepositoryInterface $entryTypeRepository,
        private readonly SettlementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(PostSettlementCreditFromInstrumentCommand $command): SettlementLedgerEntry
    {
        $instrument = $this->instrumentRepository->findById($command->instrumentId);
        if ($instrument === null) {
            throw SettlementInstrumentNotFoundException::forId($command->instrumentId);
        }

        if ($instrument->statusCode() !== SettlementInstrumentStatus::Active) {
            throw SettlementInstrumentNotActiveException::forId(
                $command->instrumentId,
                $instrument->statusCode()->value,
            );
        }

        $entryTypeCode = $instrument->partyRole() === InstrumentPartyRole::Client
            ? 'reglement_client'
            : 'reglement_fournisseur';

        $entryType = $this->entryTypeRepository->findByCode($entryTypeCode);
        if ($entryType === null || $entryType->id() === null) {
            throw SettlementEntryTypeNotFoundException::forCode($entryTypeCode);
        }

        $entry = SettlementLedgerEntry::post(
            partyAccountId: $instrument->partyAccountId(),
            partyRole: $instrument->partyRole(),
            currencyCode: $instrument->currencyCode(),
            entryTypeId: (int) $entryType->id(),
            amountMinor: -$instrument->amountMinor(),
            effectiveDate: new DateTimeImmutable('today'),
            instrumentId: $command->instrumentId,
            memo: sprintf('Crédit instrument #%d', $command->instrumentId),
            createdBy: $command->createdBy,
        );

        $this->ledgerRepository->append($entry);
        $this->unitOfWork->commit();

        return $entry;
    }
}

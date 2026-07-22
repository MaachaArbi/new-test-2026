<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\PostReglementCreditFromInstrument;

use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Exception\ReglementEntryTypeNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotActiveException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementEntryTypeRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Reglements\Domain\ValueObject\ReglementInstrumentStatus;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;

/**
 * Poste un crédit grand livre depuis un instrument Active.
 * amountMinor négatif (reglement_client / reglement_fournisseur, normal_sign=-1).
 */
final class PostReglementCreditFromInstrumentHandler
{
    public function __construct(
        private readonly ReglementInstrumentRepositoryInterface $instrumentRepository,
        private readonly ReglementEntryTypeRepositoryInterface $entryTypeRepository,
        private readonly ReglementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(PostReglementCreditFromInstrumentCommand $command): ReglementLedgerEntry
    {
        $instrument = $this->instrumentRepository->findById($command->instrumentId);
        if ($instrument === null) {
            throw ReglementInstrumentNotFoundException::forId($command->instrumentId);
        }

        if ($instrument->statusCode() !== ReglementInstrumentStatus::Active) {
            throw ReglementInstrumentNotActiveException::forId(
                $command->instrumentId,
                $instrument->statusCode()->value,
            );
        }

        $entryTypeCode = $instrument->partyRole() === InstrumentPartyRole::Client
            ? 'reglement_client'
            : 'reglement_fournisseur';

        $entryType = $this->entryTypeRepository->findByCode($entryTypeCode);
        if ($entryType === null || $entryType->id() === null) {
            throw ReglementEntryTypeNotFoundException::forCode($entryTypeCode);
        }

        $entry = ReglementLedgerEntry::post(
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

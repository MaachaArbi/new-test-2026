<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\CreateSettlementInstrument;

use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Domain\Entity\SettlementInstrument;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementInstrumentPartyRoleException;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use ValueError;

/**
 * Use case : créer un instrument (status Active).
 *
 * party_role ↔ nature party_account : aucune règle simple existante ailleurs
 * (nature = person/organization ; client/fournisseur = rôles d'assignation).
 * On ne invente rien — FK SQL + enum Domain suffisent.
 */
final class CreateSettlementInstrumentHandler
{
    public function __construct(
        private readonly SettlementInstrumentRepositoryInterface $instrumentRepository,
        private readonly SettlementReferentialValidator $referentialValidator,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateSettlementInstrumentCommand $command): SettlementInstrument
    {
        $this->referentialValidator->assertActivePaymentMethod($command->paymentMethodId);
        $this->referentialValidator->assertCurrencyExists($command->currencyCode);

        try {
            $partyRole = InstrumentPartyRole::from($command->partyRole);
        } catch (ValueError) {
            throw InvalidSettlementInstrumentPartyRoleException::forValue($command->partyRole);
        }

        $instrument = SettlementInstrument::create(
            partyAccountId: $command->partyAccountId,
            partyRole: $partyRole,
            currencyCode: $command->currencyCode,
            paymentMethodId: $command->paymentMethodId,
            amountMinor: $command->amountMinor,
            instrumentRef: $command->instrumentRef,
            bankName: $command->bankName,
            dueDate: $command->dueDate !== null ? new DateTimeImmutable($command->dueDate) : null,
            issuedOn: $command->issuedOn !== null ? new DateTimeImmutable($command->issuedOn) : null,
            metadata: $command->metadata,
            officeAccountId: $command->officeAccountId,
        );

        $this->instrumentRepository->save($instrument);
        $this->unitOfWork->commit();

        return $instrument;
    }
}

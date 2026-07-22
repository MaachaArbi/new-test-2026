<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\CreateReglementInstrument;

use App\Modules\Reglements\Application\ReglementReferentialValidator;
use App\Modules\Reglements\Domain\Entity\ReglementInstrument;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentPartyRoleException;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
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
final class CreateReglementInstrumentHandler
{
    public function __construct(
        private readonly ReglementInstrumentRepositoryInterface $instrumentRepository,
        private readonly ReglementReferentialValidator $referentialValidator,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateReglementInstrumentCommand $command): ReglementInstrument
    {
        $this->referentialValidator->assertActivePaymentMethod($command->paymentMethodId);
        $this->referentialValidator->assertCurrencyExists($command->currencyCode);

        try {
            $partyRole = InstrumentPartyRole::from($command->partyRole);
        } catch (ValueError) {
            throw InvalidReglementInstrumentPartyRoleException::forValue($command->partyRole);
        }

        $instrument = ReglementInstrument::create(
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

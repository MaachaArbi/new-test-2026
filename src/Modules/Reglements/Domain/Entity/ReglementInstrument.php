<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Entity;

use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentStatusUnchangedException;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Reglements\Domain\ValueObject\ReglementInstrumentStatus;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Agrégat instrument de règlement (la « pièce »).
 *
 * amount_minor immuable après création (jamais de setter) — miroir CHECK SQL > 0.
 * Un retour/annulation d'instrument doit normalement déclencher une écriture
 * inverse dans le grand livre — HORS PÉRIMÈTRE ici, le grand livre n'existe
 * pas encore. transitionStatus() pose uniquement la mutation du statut de
 * l'instrument lui-même ; l'orchestration complète (écriture inverse) viendra
 * avec la vague ledger_entry.
 */
final class ReglementInstrument
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private int $partyAccountId,
        private InstrumentPartyRole $partyRole,
        private string $currencyCode,
        private int $paymentMethodId,
        private int $amountMinor,
        private ?string $instrumentRef,
        private ?string $bankName,
        private ?DateTimeImmutable $dueDate,
        private ?DateTimeImmutable $issuedOn,
        private array $metadata,
        private ReglementInstrumentStatus $statusCode,
        private ?DateTimeImmutable $statusChangedAt,
        private ?string $statusReason,
        private ?int $officeAccountId,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        int $partyAccountId,
        InstrumentPartyRole $partyRole,
        string $currencyCode,
        int $paymentMethodId,
        int $amountMinor,
        ?string $instrumentRef = null,
        ?string $bankName = null,
        ?DateTimeImmutable $dueDate = null,
        ?DateTimeImmutable $issuedOn = null,
        array $metadata = [],
        ?int $officeAccountId = null,
    ): self {
        if ($amountMinor <= 0) {
            throw InvalidReglementInstrumentException::amountMustBePositive($amountMinor);
        }

        return new self(
            id: null,
            publicId: PublicId::generate(),
            partyAccountId: $partyAccountId,
            partyRole: $partyRole,
            currencyCode: strtoupper(trim($currencyCode)),
            paymentMethodId: $paymentMethodId,
            amountMinor: $amountMinor,
            instrumentRef: $instrumentRef,
            bankName: $bankName,
            dueDate: $dueDate,
            issuedOn: $issuedOn,
            metadata: $metadata,
            statusCode: ReglementInstrumentStatus::Active,
            statusChangedAt: null,
            statusReason: null,
            officeAccountId: $officeAccountId,
        );
    }

    /**
     * Change le statut métier. Aucune matrice de transitions : tout statut
     * peut mener à tout autre. Seule règle : refuser une « transition » vers
     * le statut déjà actuel (même philosophie que Booking::transitionTo()).
     *
     * NOTE PÉRIMÈTRE : un retour/annulation d'instrument doit normalement
     * déclencher une écriture inverse dans le grand livre — HORS PÉRIMÈTRE
     * ici, le grand livre n'existe pas encore. Cette méthode pose uniquement
     * la mutation du statut de l'instrument lui-même ; l'orchestration
     * complète (écriture inverse) viendra avec la vague ledger_entry.
     */
    public function transitionStatus(ReglementInstrumentStatus $newStatus, ?string $reason = null): void
    {
        if ($this->statusCode === $newStatus) {
            throw ReglementInstrumentStatusUnchangedException::forStatus($newStatus->value);
        }

        $this->statusCode = $newStatus;
        $this->statusChangedAt = new DateTimeImmutable('now');
        $this->statusReason = $reason;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function partyAccountId(): int
    {
        return $this->partyAccountId;
    }

    public function partyRole(): InstrumentPartyRole
    {
        return $this->partyRole;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function paymentMethodId(): int
    {
        return $this->paymentMethodId;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function instrumentRef(): ?string
    {
        return $this->instrumentRef;
    }

    public function bankName(): ?string
    {
        return $this->bankName;
    }

    public function dueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function issuedOn(): ?DateTimeImmutable
    {
        return $this->issuedOn;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function statusCode(): ReglementInstrumentStatus
    {
        return $this->statusCode;
    }

    public function statusChangedAt(): ?DateTimeImmutable
    {
        return $this->statusChangedAt;
    }

    public function statusReason(): ?string
    {
        return $this->statusReason;
    }

    public function officeAccountId(): ?int
    {
        return $this->officeAccountId;
    }
}

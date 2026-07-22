<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Entity;

use App\Modules\Reglements\Domain\Exception\InvalidReglementLedgerEntryException;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Écriture du grand livre — append-only.
 *
 * AUCUNE méthode de mutation après création : une correction est toujours
 * une nouvelle instance via post() avec reversesEntryId, jamais un UPDATE.
 * Le trigger PostgreSQL rejette physiquement UPDATE/DELETE ; le Domain
 * reflète la même discipline.
 */
final class ReglementLedgerEntry
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private int $partyAccountId,
        private InstrumentPartyRole $partyRole,
        private string $currencyCode,
        private int $entryTypeId,
        private int $amountMinor,
        private DateTimeImmutable $effectiveDate,
        private ?int $bookingId,
        private ?int $instrumentId,
        private ?int $invoiceId,
        private ?int $creditNoteId,
        private ?int $transferId,
        private ?int $reversesEntryId,
        private ?string $memo,
        private ?int $createdBy,
    ) {
    }

    public static function post(
        int $partyAccountId,
        InstrumentPartyRole $partyRole,
        string $currencyCode,
        int $entryTypeId,
        int $amountMinor,
        DateTimeImmutable $effectiveDate,
        ?int $bookingId = null,
        ?int $instrumentId = null,
        ?int $invoiceId = null,
        ?int $creditNoteId = null,
        ?int $transferId = null,
        ?int $reversesEntryId = null,
        ?string $memo = null,
        ?int $createdBy = null,
    ): self {
        if ($amountMinor === 0) {
            throw InvalidReglementLedgerEntryException::amountMustBeNonZero($amountMinor);
        }

        if (
            $bookingId === null
            && $instrumentId === null
            && $invoiceId === null
            && $creditNoteId === null
            && $transferId === null
            && $reversesEntryId === null
        ) {
            throw InvalidReglementLedgerEntryException::originRequired();
        }

        return new self(
            id: null,
            publicId: PublicId::generate(),
            partyAccountId: $partyAccountId,
            partyRole: $partyRole,
            currencyCode: strtoupper(trim($currencyCode)),
            entryTypeId: $entryTypeId,
            amountMinor: $amountMinor,
            effectiveDate: $effectiveDate,
            bookingId: $bookingId,
            instrumentId: $instrumentId,
            invoiceId: $invoiceId,
            creditNoteId: $creditNoteId,
            transferId: $transferId,
            reversesEntryId: $reversesEntryId,
            memo: $memo,
            createdBy: $createdBy,
        );
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

    public function entryTypeId(): int
    {
        return $this->entryTypeId;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function effectiveDate(): DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function bookingId(): ?int
    {
        return $this->bookingId;
    }

    public function instrumentId(): ?int
    {
        return $this->instrumentId;
    }

    public function invoiceId(): ?int
    {
        return $this->invoiceId;
    }

    public function creditNoteId(): ?int
    {
        return $this->creditNoteId;
    }

    public function transferId(): ?int
    {
        return $this->transferId;
    }

    public function reversesEntryId(): ?int
    {
        return $this->reversesEntryId;
    }

    public function memo(): ?string
    {
        return $this->memo;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }
}

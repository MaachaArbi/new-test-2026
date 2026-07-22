<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Agrégat racine booking_folder — dossier regroupant N booking.
 *
 * Soft-delete via {@see delete()} / `deleted_at` uniquement (pas de disable :
 * le schéma n'a pas de concept is_disabled). Idempotent.
 *
 * @see docs/decisions/2026-07-21-soft-delete-vs-disable-party-account.md
 */
final class BookingFolder
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private string $referenceCode,
        private int $partyAccountId,
        private int $officeAccountId,
        private ?DateTimeImmutable $deletedAt,
    ) {
    }

    public static function create(
        string $referenceCode,
        int $partyAccountId,
        int $officeAccountId,
    ): self {
        return new self(
            id: null,
            publicId: PublicId::generate(),
            referenceCode: $referenceCode,
            partyAccountId: $partyAccountId,
            officeAccountId: $officeAccountId,
            deletedAt: null,
        );
    }

    /**
     * Soft-delete (`deleted_at`). Idempotent : second appel no-op.
     */
    public function delete(): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function referenceCode(): string
    {
        return $this->referenceCode;
    }

    public function partyAccountId(): int
    {
        return $this->partyAccountId;
    }

    public function officeAccountId(): int
    {
        return $this->officeAccountId;
    }

    /**
     * Soft-delete actif (équivalent Domain de `deleted_at IS NOT NULL`).
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }
}

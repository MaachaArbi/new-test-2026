<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Entity;

use App\Shared\Domain\ValueObject\PublicId;

/**
 * Nature d'écriture du grand livre (table seedée — lecture seule dans cette vague).
 *
 * public_id présent en schéma → entité Domain (contrairement aux refs Booking).
 * normalSign documentaire (+1 débit / −1 crédit) — le signe réel est sur amount_minor.
 */
final class ReglementEntryType
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private string $code,
        private string $label,
        private int $normalSign,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function normalSign(): int
    {
        return $this->normalSign;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Entity;

use App\Shared\Domain\ValueObject\PublicId;

/**
 * Mode de règlement (table seedée — lecture seule dans cette vague).
 *
 * Contrairement aux référentiels Booking (codes ouverts sans public_id Domain),
 * cette table porte un public_id → entité Domain explicite.
 */
final class ReglementPaymentMethod
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private string $code,
        private string $label,
        private bool $isCashLike,
        private bool $isActive,
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

    public function isCashLike(): bool
    {
        return $this->isCashLike;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}

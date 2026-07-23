<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Entity;

use App\Modules\CashManagement\Domain\ValueObject\CashSessionStatus;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Session de caisse — reconstruction lecture seule (écriture via fonctions SQL).
 *
 * validatedAt / validatedBy non exposés cette vague (toujours NULL tant que
 * cash_validate_session n'est pas branché).
 */
final class CashSession
{
    private function __construct(
        private int $id,
        private PublicId $publicId,
        private int $holderAccountId,
        private ?int $officeAccountId,
        private CashSessionStatus $statusCode,
        private DateTimeImmutable $openedAt,
        private ?int $openedBy,
        private ?DateTimeImmutable $closedAt,
        private ?int $closedBy,
    ) {
    }

    public static function fromPersistence(
        int $id,
        PublicId $publicId,
        int $holderAccountId,
        ?int $officeAccountId,
        CashSessionStatus $statusCode,
        DateTimeImmutable $openedAt,
        ?int $openedBy,
        ?DateTimeImmutable $closedAt,
        ?int $closedBy,
    ): self {
        return new self(
            $id,
            $publicId,
            $holderAccountId,
            $officeAccountId,
            $statusCode,
            $openedAt,
            $openedBy,
            $closedAt,
            $closedBy,
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function holderAccountId(): int
    {
        return $this->holderAccountId;
    }

    public function officeAccountId(): ?int
    {
        return $this->officeAccountId;
    }

    public function statusCode(): CashSessionStatus
    {
        return $this->statusCode;
    }

    public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function openedBy(): ?int
    {
        return $this->openedBy;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedBy(): ?int
    {
        return $this->closedBy;
    }
}

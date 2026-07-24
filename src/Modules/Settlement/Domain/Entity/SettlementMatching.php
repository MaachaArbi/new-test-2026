<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Entity;

use App\Modules\Settlement\Domain\Exception\InvalidSettlementMatchingException;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Lettrage N-N optionnel — ne mute jamais le grand livre ni settlement_balance.
 */
final class SettlementMatching
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private int $debitEntryId,
        private int $creditEntryId,
        private int $matchedAmountMinor,
        private bool $isAutomatic,
        private ?string $matchGroup,
        private DateTimeImmutable $matchedAt,
        private ?int $matchedBy,
        private ?DateTimeImmutable $unmatchedAt,
        private ?int $unmatchedBy,
    ) {
    }

    public static function match(
        int $debitEntryId,
        int $creditEntryId,
        int $matchedAmountMinor,
        bool $isAutomatic = false,
        ?string $matchGroup = null,
        ?int $matchedBy = null,
    ): self {
        if ($matchedAmountMinor <= 0) {
            throw InvalidSettlementMatchingException::amountMustBePositive($matchedAmountMinor);
        }

        if ($debitEntryId === $creditEntryId) {
            throw InvalidSettlementMatchingException::entriesMustBeDistinct();
        }

        return new self(
            id: null,
            publicId: PublicId::generate(),
            debitEntryId: $debitEntryId,
            creditEntryId: $creditEntryId,
            matchedAmountMinor: $matchedAmountMinor,
            isAutomatic: $isAutomatic,
            matchGroup: $matchGroup,
            matchedAt: new DateTimeImmutable('now'),
            matchedBy: $matchedBy,
            unmatchedAt: null,
            unmatchedBy: null,
        );
    }

    public function unmatch(?int $unmatchedBy = null): void
    {
        if ($this->unmatchedAt !== null) {
            throw InvalidSettlementMatchingException::alreadyUnmatched((int) ($this->id ?? 0));
        }

        $this->unmatchedAt = new DateTimeImmutable('now');
        $this->unmatchedBy = $unmatchedBy;
    }

    public function isActive(): bool
    {
        return $this->unmatchedAt === null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function debitEntryId(): int
    {
        return $this->debitEntryId;
    }

    public function creditEntryId(): int
    {
        return $this->creditEntryId;
    }

    public function matchedAmountMinor(): int
    {
        return $this->matchedAmountMinor;
    }

    public function isAutomatic(): bool
    {
        return $this->isAutomatic;
    }

    public function matchGroup(): ?string
    {
        return $this->matchGroup;
    }

    public function matchedAt(): DateTimeImmutable
    {
        return $this->matchedAt;
    }

    public function matchedBy(): ?int
    {
        return $this->matchedBy;
    }

    public function unmatchedAt(): ?DateTimeImmutable
    {
        return $this->unmatchedAt;
    }

    public function unmatchedBy(): ?int
    {
        return $this->unmatchedBy;
    }
}

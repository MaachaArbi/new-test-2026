<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Dto;

use App\Modules\Party\Domain\Entity\PartyAccount;

/**
 * DTO réponse HTTP — expose uniquement public_id (jamais l'id interne).
 */
final readonly class PartyAccountResponse
{
    private function __construct(
        public string $publicId,
        public string $nature,
        public string $displayName,
        public ?string $email,
        public bool $isDisabled,
    ) {
    }

    public static function fromDomain(PartyAccount $account): self
    {
        $email = $account->email();

        return new self(
            publicId: $account->publicId()->toString(),
            nature: $account->nature()->value,
            displayName: $account->displayName(),
            email: $email?->toString(),
            isDisabled: $account->isDisabled(),
        );
    }

    /**
     * @return array{
     *     publicId: string,
     *     nature: string,
     *     displayName: string,
     *     email: string|null,
     *     isDisabled: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'nature' => $this->nature,
            'displayName' => $this->displayName,
            'email' => $this->email,
            'isDisabled' => $this->isDisabled,
        ];
    }
}

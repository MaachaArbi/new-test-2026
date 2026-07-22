<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Dto;

use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/party-accounts — validation d'input (pas de règle métier Domain).
 */
final class CreatePartyAccountRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['person', 'organization'])]
    public mixed $nature = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public mixed $displayName = null;

    // Même pattern que Email::fromString() — pas Assert\Email (divergence html5).
    #[Assert\Regex(pattern: Email::FORMAT_PATTERN)]
    public mixed $email = null;

    #[Assert\Type('integer')]
    public mixed $parentAccountId = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->nature = $data['nature'] ?? null;
        $request->displayName = $data['displayName'] ?? null;

        $email = $data['email'] ?? null;
        $request->email = $email === '' ? null : $email;

        $request->parentAccountId = $data['parentAccountId'] ?? null;

        return $request;
    }
}

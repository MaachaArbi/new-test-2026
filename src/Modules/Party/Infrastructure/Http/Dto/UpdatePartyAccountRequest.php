<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Body PATCH /api/v1/party-accounts/{publicId} — champs optionnels.
 */
final class UpdatePartyAccountRequest
{
    public bool $hasDisplayName = false;

    public bool $hasIsDisabled = false;

    #[Assert\Length(max: 255)]
    public mixed $displayName = null;

    public mixed $isDisabled = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();

        if (array_key_exists('displayName', $data)) {
            $request->hasDisplayName = true;
            $request->displayName = $data['displayName'];
        }

        if (array_key_exists('isDisabled', $data)) {
            $request->hasIsDisabled = true;
            $request->isDisabled = $data['isDisabled'];
        }

        return $request;
    }

    #[Assert\Callback]
    public function validateProvidedFields(ExecutionContextInterface $context): void
    {
        if ($this->hasDisplayName) {
            if (!is_string($this->displayName) || trim($this->displayName) === '') {
                $context->buildViolation('This value should not be blank.')
                    ->atPath('displayName')
                    ->addViolation();
            }
        }

        if ($this->hasIsDisabled && !is_bool($this->isDisabled)) {
            $context->buildViolation('This value should be of type bool.')
                ->atPath('isDisabled')
                ->addViolation();
        }
    }
}

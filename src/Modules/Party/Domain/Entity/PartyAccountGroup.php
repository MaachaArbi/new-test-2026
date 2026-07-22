<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use App\Shared\Domain\ValueObject\PublicId;

/**
 * Agrégat party_account_group — regroupement nommé scopé par dimension.
 *
 * Pas d'historisation du contenu (name) : rename en place. Unicité
 * (group_type_code, name) vérifiée en Application avant insert.
 */
final class PartyAccountGroup
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private PartyAccountGroupTypeCode $groupTypeCode,
        private string $name,
    ) {
    }

    public static function create(
        PartyAccountGroupTypeCode $groupTypeCode,
        string $name,
    ): self {
        return new self(
            null,
            PublicId::generate(),
            $groupTypeCode,
            $name,
        );
    }

    public function rename(string $newName): void
    {
        $this->name = $newName;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function groupTypeCode(): PartyAccountGroupTypeCode
    {
        return $this->groupTypeCode;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }
}

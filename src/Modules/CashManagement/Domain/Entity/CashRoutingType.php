<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Entity;

/**
 * Destination de routing (table seedée — lecture seule dans cette vague).
 *
 * PK = code (pas d'id numérique). Seed SQL : caisse / banque_directe /
 * transmission_externe / aucun.
 */
final class CashRoutingType
{
    private function __construct(
        private string $code,
        private string $label,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }
}

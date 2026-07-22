<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Identifiant de corrélation par requête (ou par cycle CLI / worker).
 */
final class CorrelationIdHolder implements ResetInterface
{
    private ?string $correlationId = null;

    public function get(): string
    {
        return $this->correlationId ??= Uuid::uuid4()->toString();
    }

    public function set(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function reset(): void
    {
        $this->correlationId = null;
    }
}

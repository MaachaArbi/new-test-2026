<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application;

use App\Modules\Reglements\Domain\Exception\ReglementPaymentMethodInactiveException;
use App\Modules\Reglements\Domain\Exception\ReglementUnknownCurrencyException;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Vérifie l'existence des référentiels Règlements avant écriture (ADR-003 DBAL).
 */
final class ReglementReferentialValidator
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function assertActivePaymentMethod(int $paymentMethodId): void
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM reglement_payment_method WHERE id = :id AND is_active = true',
            ['id' => $paymentMethodId],
        );

        if ($raw === false || $raw === null) {
            throw ReglementPaymentMethodInactiveException::forId($paymentMethodId);
        }
    }

    public function assertCurrencyExists(string $code): void
    {
        $normalized = strtoupper(trim($code));
        if (!$this->exists('ref_currency', $normalized)) {
            throw ReglementUnknownCurrencyException::forCode($normalized);
        }
    }

    private function exists(string $table, string $code): bool
    {
        $sql = match ($table) {
            'ref_currency' => 'SELECT 1 FROM ref_currency WHERE code = :code',
            default => throw new InvalidArgumentException(sprintf('Unsupported referential table "%s".', $table)),
        };

        $raw = $this->connection->fetchOne($sql, ['code' => $code]);

        return $raw !== false && $raw !== null;
    }
}

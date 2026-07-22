<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Domain\Exception\BookingUnknownChannelException;
use App\Modules\Booking\Domain\Exception\BookingUnknownChargeTypeException;
use App\Modules\Booking\Domain\Exception\BookingUnknownCurrencyException;
use App\Modules\Booking\Domain\Exception\BookingUnknownServiceTypeException;
use App\Modules\Booking\Domain\Exception\BookingUnknownStatusException;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Vérifie l'existence des codes référentiels booking avant écriture (ADR-003 DBAL).
 * Évite de laisser une FK SQL être le seul filet (500 Doctrine).
 */
final class BookingReferentialValidator
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function assertServiceTypeExists(string $code): void
    {
        if (!$this->exists('booking_service_type', $code)) {
            throw BookingUnknownServiceTypeException::forCode($code);
        }
    }

    public function assertStatusExists(string $code): void
    {
        if (!$this->exists('booking_status', $code)) {
            throw BookingUnknownStatusException::forCode($code);
        }
    }

    public function assertChannelExists(string $code): void
    {
        if (!$this->exists('booking_channel', $code)) {
            throw BookingUnknownChannelException::forCode($code);
        }
    }

    public function assertChargeTypeExists(string $code): void
    {
        if (!$this->exists('booking_charge_type', $code)) {
            throw BookingUnknownChargeTypeException::forCode($code);
        }
    }

    public function assertCurrencyExists(string $field, string $code): void
    {
        $normalized = strtoupper(trim($code));
        if (!$this->exists('ref_currency', $normalized)) {
            throw BookingUnknownCurrencyException::forCode($field, $normalized);
        }
    }

    private function exists(string $table, string $code): bool
    {
        $sql = match ($table) {
            'booking_service_type' => 'SELECT 1 FROM booking_service_type WHERE code = :code',
            'booking_status' => 'SELECT 1 FROM booking_status WHERE code = :code',
            'booking_channel' => 'SELECT 1 FROM booking_channel WHERE code = :code',
            'booking_charge_type' => 'SELECT 1 FROM booking_charge_type WHERE code = :code',
            'ref_currency' => 'SELECT 1 FROM ref_currency WHERE code = :code',
            default => throw new InvalidArgumentException(sprintf('Unsupported referential table "%s".', $table)),
        };

        $raw = $this->connection->fetchOne($sql, ['code' => $code]);

        return $raw !== false && $raw !== null;
    }
}

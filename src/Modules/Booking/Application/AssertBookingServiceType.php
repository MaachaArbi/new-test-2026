<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Exception\BookingServiceTypeMismatchException;
use Doctrine\DBAL\Connection;

/**
 * Vérifie que (service_type_code, extension) existe dans
 * booking_service_type_extension (référentiel data-driven, ADR-003 DBAL).
 */
final class AssertBookingServiceType
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(int $bookingId, string $extensionCode): void
    {
        $actual = $this->connection->fetchOne(
            'SELECT service_type_code FROM booking WHERE id = :id',
            ['id' => $bookingId],
        );

        if (!is_string($actual) || $actual === '') {
            throw BookingNotFoundException::forId($bookingId);
        }

        $actualCode = $actual;

        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM booking_service_type_extension
             WHERE service_type_code = :service_type_code
               AND extension_code = :extension_code',
            [
                'service_type_code' => $actualCode,
                'extension_code' => $extensionCode,
            ],
        );

        if ($exists === false || $exists === null) {
            throw BookingServiceTypeMismatchException::forBooking(
                $bookingId,
                $extensionCode,
                $actualCode,
            );
        }
    }
}

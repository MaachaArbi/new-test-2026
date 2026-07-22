<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBooking;

use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\ExchangeRate;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use ValueError;

/**
 * Use case : créer un booking pivot (montants/devises inclus).
 * channelCode / paymentStatus résolus ici (défauts Command) — pas dans le Domain.
 * Référentiels ouverts vérifiés en Application avant create (pas FK SQL seule).
 */
final class CreateBookingHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingReferentialValidator $referentialValidator,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateBookingCommand $command): Booking
    {
        $this->referentialValidator->assertServiceTypeExists($command->serviceTypeCode);
        $this->referentialValidator->assertStatusExists($command->statusCode);
        $this->referentialValidator->assertChannelExists($command->channelCode);
        $this->referentialValidator->assertCurrencyExists('achatCurrencyCode', $command->achatCurrencyCode);
        $this->referentialValidator->assertCurrencyExists('venteCurrencyCode', $command->venteCurrencyCode);

        try {
            $paymentStatus = PaymentStatus::from($command->paymentStatus);
        } catch (ValueError $e) {
            throw new \InvalidArgumentException(
                sprintf('Invalid payment status: "%s".', $command->paymentStatus),
                0,
                $e,
            );
        }

        $booking = Booking::create(
            $command->folderId,
            BookingServiceTypeCode::fromString($command->serviceTypeCode),
            BookingStatusCode::fromString($command->statusCode),
            $command->customerAccountId,
            $command->supplierAccountId,
            $command->officeAccountId,
            new DateTimeImmutable($command->startDate),
            $command->endDate !== null ? new DateTimeImmutable($command->endDate) : null,
            BookingChannelCode::fromString($command->channelCode),
            $command->achatCurrencyCode,
            $command->venteCurrencyCode,
            ExchangeRate::fromString($command->achatExchangeRate),
            ExchangeRate::fromString($command->venteExchangeRate),
            Money::fromMinorUnits($command->totalAchatAmount, $command->achatCurrencyCode),
            Money::fromMinorUnits($command->totalVenteAmount, $command->venteCurrencyCode),
            Money::fromMinorUnits($command->margeAgenceAmount, $command->venteCurrencyCode),
            Money::fromMinorUnits($command->margeDistributeurAmount, $command->venteCurrencyCode),
            Money::fromMinorUnits($command->paidAmount, $command->venteCurrencyCode),
            $paymentStatus,
        );

        $this->bookingRepository->save($booking);
        $this->unitOfWork->commit();

        return $booking;
    }
}

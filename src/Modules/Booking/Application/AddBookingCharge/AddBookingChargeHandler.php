<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingCharge;

use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Domain\Entity\BookingCharge;
use App\Modules\Booking\Domain\Exception\BookingChargeSegmentMismatchException;
use App\Modules\Booking\Domain\Exception\BookingChargeTravelerMismatchException;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingChargeRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTravelerRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

/**
 * Ajoute une ligne booking_charge puis recalcule les totaux du booking
 * (SUM applicatif — jamais trigger SQL, ADR-002 / schéma).
 */
final class AddBookingChargeHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingChargeRepositoryInterface $chargeRepository,
        private readonly BookingTravelerRepositoryInterface $travelerRepository,
        private readonly BookingTransportSegmentRepositoryInterface $segmentRepository,
        private readonly BookingReferentialValidator $referentialValidator,
        private readonly Connection $connection,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AddBookingChargeCommand $command): BookingCharge
    {
        $booking = $this->bookingRepository->findById($command->bookingId);
        if ($booking === null) {
            throw BookingNotFoundException::forId($command->bookingId);
        }

        $this->referentialValidator->assertChargeTypeExists($command->chargeTypeCode);

        if ($command->travelerId !== null) {
            $this->assertTravelerBelongsToBooking($command->bookingId, $command->travelerId);
        }

        if ($command->segmentId !== null) {
            $this->assertSegmentBelongsToBooking($command->bookingId, $command->segmentId);
        }

        $charge = BookingCharge::create(
            $command->bookingId,
            $command->chargeTypeCode,
            $command->achatAmount,
            $command->venteAmount,
            $command->travelerId,
            $command->segmentId,
            $command->label,
            $command->metadata,
            $command->sortOrder,
        );

        $this->chargeRepository->save($charge);

        // Le charge n'est pas encore en base (persist() sans flush) : le SUM
        // DBAL ne le voit pas encore. On ajoute donc ses montants en mémoire
        // à la somme des charges déjà committées avant de recalculer.
        $sums = $this->sumAmountsForBooking($command->bookingId);
        $booking->recalculateTotals(
            Money::fromMinorUnits(
                $sums['achat'] + $command->achatAmount->amount(),
                $booking->achatCurrencyCode(),
            ),
            Money::fromMinorUnits(
                $sums['vente'] + $command->venteAmount->amount(),
                $booking->venteCurrencyCode(),
            ),
        );
        $this->bookingRepository->save($booking);

        $this->unitOfWork->commit();

        return $charge;
    }

    private function assertTravelerBelongsToBooking(int $bookingId, int $travelerId): void
    {
        if ($this->travelerRepository->belongsToBooking($travelerId, $bookingId)) {
            return;
        }

        throw BookingChargeTravelerMismatchException::forBookingAndTraveler($bookingId, $travelerId);
    }

    private function assertSegmentBelongsToBooking(int $bookingId, int $segmentId): void
    {
        if ($this->segmentRepository->belongsToBooking($segmentId, $bookingId)) {
            return;
        }

        throw BookingChargeSegmentMismatchException::forBookingAndSegment($bookingId, $segmentId);
    }

    /**
     * @return array{achat: int, vente: int}
     */
    private function sumAmountsForBooking(int $bookingId): array
    {
        /** @var array{achat: int|string|null, vente: int|string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(achat_amount), 0) AS achat,
                    COALESCE(SUM(vente_amount), 0) AS vente
             FROM booking_charge
             WHERE booking_id = :bookingId',
            ['bookingId' => $bookingId],
        );

        if ($row === false) {
            return ['achat' => 0, 'vente' => 0];
        }

        return [
            'achat' => (int) $row['achat'],
            'vente' => (int) $row['vente'],
        ];
    }
}

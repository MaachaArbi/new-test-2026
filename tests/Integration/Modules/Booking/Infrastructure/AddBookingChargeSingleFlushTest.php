<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AddBookingCharge\AddBookingChargeCommand;
use App\Modules\Booking\Application\AddBookingCharge\AddBookingChargeHandler;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingChargeRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTravelerRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Preuve chiffrée single-flush : AddBookingCharge (charge + booking) = un seul
 * cycle d'écriture ORM (UnitOfWork::commit), pas deux flush séparés.
 *
 * Baseline pré-migration : 2× persist+flush (charge puis booking) → deux
 * synchronisations UnitOfWork Doctrine. Après : 1 commit → INSERT + UPDATE
 * consécutifs dans le même cycle.
 */
final class AddBookingChargeSingleFlushTest extends KernelTestCase
{
    public function test_add_charge_issues_single_orm_flush_cycle_for_charge_and_booking(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        /** @var BookingRepositoryInterface $bookings */
        $bookings = $container->get(BookingRepositoryInterface::class);
        /** @var BookingChargeRepositoryInterface $charges */
        $charges = $container->get(BookingChargeRepositoryInterface::class);
        /** @var BookingTravelerRepositoryInterface $travelers */
        $travelers = $container->get(BookingTravelerRepositoryInterface::class);
        /** @var BookingTransportSegmentRepositoryInterface $segments */
        $segments = $container->get(BookingTransportSegmentRepositoryInterface::class);
        /** @var BookingFolderRepositoryInterface $folders */
        $folders = $container->get(BookingFolderRepositoryInterface::class);
        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var DebugDataHolder $debugDataHolder */
        $debugDataHolder = $container->get('doctrine.debug_data_holder');

        $bookingId = $this->seedBooking($unitOfWork, $accounts, $folders, $bookings, $connection);

        $handler = new AddBookingChargeHandler(
            $bookings,
            $charges,
            $travelers,
            $segments,
            new BookingReferentialValidator($connection),
            $connection,
            $unitOfWork,
        );

        $debugDataHolder->reset();

        ($handler)(new AddBookingChargeCommand(
            bookingId: $bookingId,
            chargeTypeCode: 'fare',
            achatAmount: Money::fromMinorUnits(1_000, 'TND'),
            venteAmount: Money::fromMinorUnits(1_200, 'TND'),
        ));

        $allSql = [];
        foreach ($debugDataHolder->getData() as $queries) {
            foreach ($queries as $query) {
                $allSql[] = $query['sql'];
            }
        }

        $writes = array_values(array_filter(
            $allSql,
            static fn (string $sql): bool => (bool) preg_match('/^\s*(INSERT|UPDATE)\b/i', $sql),
        ));

        // Un INSERT charge + un UPDATE booking dans le même flush — pas de
        // second cycle « flush charge puis flush booking » (baseline = 2 sync).
        self::assertCount(
            2,
            $writes,
            "Attendu exactement 2 écritures (INSERT charge + UPDATE booking), got:\n"
            .implode("\n", $writes)."\n--- all ---\n".implode("\n", $allSql),
        );
        self::assertMatchesRegularExpression('/^\s*INSERT\b/i', $writes[0]);
        self::assertMatchesRegularExpression('/^\s*UPDATE\b/i', $writes[1]);
        self::assertStringContainsStringIgnoringCase('booking_charge', $writes[0]);
        self::assertStringContainsStringIgnoringCase('booking', $writes[1]);
    }

    private function seedBooking(
        UnitOfWork $unitOfWork,
        PartyAccountRepositoryInterface $accounts,
        BookingFolderRepositoryInterface $folders,
        BookingRepositoryInterface $bookings,
        Connection $connection,
    ): int {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            'Flush Cust '.$suffix,
            Email::fromString('flush.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'Flush Off '.$suffix,
            Email::fromString('flush.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create(
            'FLUSH-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $folders->save($folder);
        $unitOfWork->commit();

        $booking = (new CreateBookingHandler(
            $bookings,
            new BookingReferentialValidator($connection),
            $unitOfWork,
        ))(new CreateBookingCommand(
            folderId: (int) $folder->id(),
            serviceTypeCode: 'flight',
            statusCode: 'draft',
            customerAccountId: (int) $customer->id(),
            supplierAccountId: null,
            officeAccountId: (int) $office->id(),
            startDate: '2026-10-01',
            endDate: '2026-10-05',
            achatCurrencyCode: 'TND',
            venteCurrencyCode: 'TND',
            achatExchangeRate: '1',
            venteExchangeRate: '1',
            totalAchatAmount: 0,
            totalVenteAmount: 0,
            margeAgenceAmount: 0,
            margeDistributeurAmount: 0,
            paidAmount: 0,
        ));

        $id = $booking->id();
        self::assertNotNull($id);

        return $id;
    }
}

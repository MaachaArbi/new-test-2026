<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\ExchangeRate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMInvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Documente le comportement réel Doctrine sur PK composite (id, booking_date).
 * PostgreSQL réel uniquement.
 */
final class BookingCompositePrimaryKeyProbeTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var BookingRepositoryInterface $bookingRepository */
        $bookingRepository = $container->get(BookingRepositoryInterface::class);
        $this->bookingRepository = $bookingRepository;

        /** @var BookingFolderRepositoryInterface $folderRepository */
        $folderRepository = $container->get(BookingFolderRepositoryInterface::class);
        $this->folderRepository = $folderRepository;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;
    }

    public function test_find_scalar_id_vs_composite_vs_querybuilder(): void
    {
        $booking = $this->persistProbeBooking();
        $id = (int) $booking->id();
        $bookingDate = $booking->bookingDate()->format('Y-m-d');

        $this->em->clear();

        // A) find(id scalaire) — attendu : échec / refus PK composite
        $scalarOutcome = 'ok_null_or_entity';
        $scalarMessage = '';
        try {
            $found = $this->em->find(Booking::class, $id);
            $scalarOutcome = $found === null ? 'null' : 'found';
        } catch (Throwable $e) {
            $scalarOutcome = $e::class;
            $scalarMessage = $e->getMessage();
        }

        self::assertSame(
            ORMInvalidArgumentException::class,
            $scalarOutcome,
            sprintf('Expected ORMInvalidArgumentException, got: %s — %s', $scalarOutcome, $scalarMessage),
        );
        self::assertStringContainsString('composite primary key', $scalarMessage);

        // B) find(composite array) — attendu : trouvé
        $byComposite = $this->em->find(Booking::class, [
            'id' => $id,
            'bookingDate' => $bookingDate,
        ]);
        self::assertNotNull($byComposite);
        self::assertSame($id, $byComposite->id());

        $this->em->clear();

        // C) QueryBuilder id seul — attendu : trouvé (unicité IDENTITY globale)
        $byQb = $this->bookingRepository->findById($id);
        self::assertNotNull($byQb);
        self::assertSame($id, $byQb->id());
    }

    private function persistProbeBooking(): Booking
    {
        $suffix = bin2hex(random_bytes(3));
        $customer = PartyAccount::createOrganization(
            'PkProbe Cust '.$suffix,
            Email::fromString('pkprobe.c.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'PkProbe Off '.$suffix,
            Email::fromString('pkprobe.o.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'PKP-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        $booking = Booking::create(
            (int) $folder->id(),
            BookingServiceTypeCode::fromString('hotel'),
            BookingStatusCode::fromString('draft'),
            (int) $customer->id(),
            null,
            (int) $office->id(),
            new DateTimeImmutable('2026-08-10'),
            new DateTimeImmutable('2026-08-12'),
            BookingChannelCode::fromString('backoffice'),
            'TND',
            'TND',
            ExchangeRate::fromString('1'),
            ExchangeRate::fromString('1'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            Money::fromMinorUnits(0, 'TND'),
            PaymentStatus::Unpaid,
        );
        $this->bookingRepository->save($booking);
        $this->unitOfWork->commit();

        return $booking;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Settlement\Infrastructure;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentCommand;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentHandler;
use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusCommand;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusHandler;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementInstrumentException;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementPaymentMethodRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Settlement\Domain\ValueObject\SettlementInstrumentStatus;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — référentiels seedés + instrument (création / transition).
 */
final class SettlementInstrumentPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private SettlementInstrumentRepositoryInterface $instrumentRepository;

    private SettlementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private SettlementEntryTypeRepositoryInterface $entryTypeRepository;

    private CreateSettlementInstrumentHandler $createHandler;

    private TransitionSettlementInstrumentStatusHandler $transitionHandler;

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

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var SettlementInstrumentRepositoryInterface $instrumentRepository */
        $instrumentRepository = $container->get(SettlementInstrumentRepositoryInterface::class);
        $this->instrumentRepository = $instrumentRepository;

        /** @var SettlementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(SettlementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var SettlementEntryTypeRepositoryInterface $entryTypeRepository */
        $entryTypeRepository = $container->get(SettlementEntryTypeRepositoryInterface::class);
        $this->entryTypeRepository = $entryTypeRepository;

        $this->createHandler = new CreateSettlementInstrumentHandler(
            $this->instrumentRepository,
            new SettlementReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->transitionHandler = new TransitionSettlementInstrumentStatusHandler(
            $this->instrumentRepository,
            $this->unitOfWork,
        );
    }

    public function test_seeded_referentials_are_readable_by_code(): void
    {
        $cheque = $this->paymentMethodRepository->findByCode('C');
        self::assertNotNull($cheque);
        self::assertSame('Chèque', $cheque->label());
        self::assertTrue($cheque->isCashLike());
        self::assertTrue($cheque->isActive());
        self::assertSame(36, strlen($cheque->publicId()->toString()));

        $entryType = $this->entryTypeRepository->findByCode('customer_payment');
        self::assertNotNull($entryType);
        self::assertSame(-1, $entryType->normalSign());
        self::assertSame(36, strlen($entryType->publicId()->toString()));
    }

    public function test_create_instrument_round_trip_all_fields(): void
    {
        $ctx = $this->seedAccounts('InstrRt');
        $cheque = $this->paymentMethodRepository->findByCode('C');
        self::assertNotNull($cheque);
        $paymentMethodId = (int) $cheque->id();

        $metadata = [
            'titulaire' => 'Amicale Alpha',
            'guichet' => ['code' => 'G12', 'ville' => 'Tunis'],
            'bordereau' => ['numero' => 'BR-2026-0042', 'lignes' => [1, 2, 3]],
            'flags' => ['remise' => true, 'urgence' => false],
        ];

        $instrument = ($this->createHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: $ctx['partyId'],
            partyRole: 'customer',
            currencyCode: 'TND',
            paymentMethodId: $paymentMethodId,
            amountMinor: 250_750,
            instrumentRef: 'CHQ-998877',
            bankName: 'BIAT',
            dueDate: '2026-09-15',
            issuedOn: '2026-07-22',
            metadata: $metadata,
            officeAccountId: $ctx['officeId'],
        ));

        $id = (int) $instrument->id();
        self::assertGreaterThan(0, $id);
        self::assertSame(SettlementInstrumentStatus::Active, $instrument->statusCode());

        $this->em->clear();

        $reloaded = $this->instrumentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($instrument->publicId()->toString(), $reloaded->publicId()->toString());
        self::assertSame($ctx['partyId'], $reloaded->partyAccountId());
        self::assertSame(InstrumentPartyRole::Customer, $reloaded->partyRole());
        self::assertSame('TND', $reloaded->currencyCode());
        self::assertSame($paymentMethodId, $reloaded->paymentMethodId());
        self::assertSame(250_750, $reloaded->amountMinor());
        self::assertSame('CHQ-998877', $reloaded->instrumentRef());
        self::assertSame('BIAT', $reloaded->bankName());
        self::assertSame('2026-09-15', $reloaded->dueDate()?->format('Y-m-d'));
        self::assertSame('2026-07-22', $reloaded->issuedOn()?->format('Y-m-d'));
        // JSONB Postgres réordonne les clés objet — égalité de contenu, pas assertSame.
        self::assertEquals($metadata, $reloaded->metadata());
        self::assertSame(SettlementInstrumentStatus::Active, $reloaded->statusCode());
        self::assertNull($reloaded->statusChangedAt());
        self::assertNull($reloaded->statusReason());
        self::assertSame($ctx['officeId'], $reloaded->officeAccountId());

        $byPublicId = $this->instrumentRepository->findByPublicId($reloaded->publicId());
        self::assertNotNull($byPublicId);
        self::assertSame($id, $byPublicId->id());
    }

    public function test_create_rejects_non_positive_amount_before_sql(): void
    {
        $ctx = $this->seedAccounts('InstrAmt');
        $cb = $this->paymentMethodRepository->findByCode('CB');
        self::assertNotNull($cb);

        try {
            ($this->createHandler)(new CreateSettlementInstrumentCommand(
                partyAccountId: $ctx['partyId'],
                partyRole: 'customer',
                currencyCode: 'TND',
                paymentMethodId: (int) $cb->id(),
                amountMinor: 0,
            ));
            self::fail('Expected InvalidSettlementInstrumentException');
        } catch (InvalidSettlementInstrumentException $exception) {
            self::assertSame('settlement_instrument.invalid_amount', $exception->errorCode());
        }
    }

    public function test_transition_active_to_returned_persists_audit(): void
    {
        $ctx = $this->seedAccounts('InstrTr');
        $virement = $this->paymentMethodRepository->findByCode('V');
        self::assertNotNull($virement);

        $instrument = ($this->createHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: $ctx['partyId'],
            partyRole: 'supplier',
            currencyCode: 'EUR',
            paymentMethodId: (int) $virement->id(),
            amountMinor: 99_00,
            instrumentRef: 'VIR-1',
        ));

        $id = (int) $instrument->id();
        $before = new \DateTimeImmutable('now');

        ($this->transitionHandler)(new TransitionSettlementInstrumentStatusCommand(
            instrumentId: $id,
            statusCode: 'returned',
            reason: 'rejeté banque',
        ));

        $this->em->clear();

        $reloaded = $this->instrumentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame(SettlementInstrumentStatus::Returned, $reloaded->statusCode());
        self::assertSame('rejeté banque', $reloaded->statusReason());
        self::assertNotNull($reloaded->statusChangedAt());
        self::assertGreaterThanOrEqual(
            $before->getTimestamp() - 1,
            $reloaded->statusChangedAt()->getTimestamp(),
        );
    }

    public function test_metadata_jsonb_non_trivial_round_trip(): void
    {
        $ctx = $this->seedAccounts('InstrMeta');
        $espece = $this->paymentMethodRepository->findByCode('E');
        self::assertNotNull($espece);

        $metadata = [
            'nested' => [
                'a' => 1,
                'b' => ['x' => 'y', 'z' => [true, null, 3.5]],
            ],
            'unicode' => 'café — أمينة',
            'empty_list' => [],
        ];

        $instrument = ($this->createHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: $ctx['partyId'],
            partyRole: 'customer',
            currencyCode: 'TND',
            paymentMethodId: (int) $espece->id(),
            amountMinor: 50,
            metadata: $metadata,
        ));

        $this->em->clear();

        $reloaded = $this->instrumentRepository->findById((int) $instrument->id());
        self::assertNotNull($reloaded);
        self::assertEquals($metadata, $reloaded->metadata());
    }

    /**
     * @return array{partyId: int, officeId: int}
     */
    private function seedAccounts(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $party = PartyAccount::createOrganization(
            $label.' Party '.$suffix,
            Email::fromString('reg.party.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('reg.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($party);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        return [
            'partyId' => (int) $party->id(),
            'officeId' => (int) $office->id(),
        ];
    }
}

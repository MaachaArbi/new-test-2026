<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\CashManagement\Infrastructure;

use App\Modules\CashManagement\Application\CashSessionPartyAccountValidator;
use App\Modules\CashManagement\Application\CloseCashSession\CloseCashSessionCommand;
use App\Modules\CashManagement\Application\CloseCashSession\CloseCashSessionHandler;
use App\Modules\CashManagement\Application\OpenCashSession\OpenCashSessionCommand;
use App\Modules\CashManagement\Application\OpenCashSession\OpenCashSessionHandler;
use App\Modules\CashManagement\Application\ReceiveCashInstrument\ReceiveCashInstrumentCommand;
use App\Modules\CashManagement\Application\ReceiveCashInstrument\ReceiveCashInstrumentHandler;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentAlreadyInSessionException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentNotActiveException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentNotFoundException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentRoutingNotCaisseException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveReceivedByNotFoundException;
use App\Modules\CashManagement\Domain\Exception\CashSessionNotOpenException;
use App\Modules\CashManagement\Domain\Repository\CashPaymentMethodRoutingRepositoryInterface;
use App\Modules\CashManagement\Domain\Repository\CashSessionRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentCommand;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentHandler;
use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusCommand;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusHandler;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementPaymentMethodRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — cash_receive_instrument + 5 validations Application.
 */
final class CashReceiveInstrumentPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private CashSessionRepositoryInterface $sessionRepository;

    private SettlementInstrumentRepositoryInterface $instrumentRepository;

    private SettlementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private CashPaymentMethodRoutingRepositoryInterface $routingRepository;

    private OpenCashSessionHandler $openHandler;

    private CloseCashSessionHandler $closeHandler;

    private ReceiveCashInstrumentHandler $receiveHandler;

    private CreateSettlementInstrumentHandler $createInstrumentHandler;

    private TransitionSettlementInstrumentStatusHandler $transitionHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var CashSessionRepositoryInterface $sessionRepository */
        $sessionRepository = $container->get(CashSessionRepositoryInterface::class);
        $this->sessionRepository = $sessionRepository;

        /** @var SettlementInstrumentRepositoryInterface $instrumentRepository */
        $instrumentRepository = $container->get(SettlementInstrumentRepositoryInterface::class);
        $this->instrumentRepository = $instrumentRepository;

        /** @var SettlementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(SettlementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var CashPaymentMethodRoutingRepositoryInterface $routingRepository */
        $routingRepository = $container->get(CashPaymentMethodRoutingRepositoryInterface::class);
        $this->routingRepository = $routingRepository;

        $validator = new CashSessionPartyAccountValidator($this->connection);
        $this->openHandler = new OpenCashSessionHandler($this->connection, $validator);
        $this->closeHandler = new CloseCashSessionHandler($this->connection, $validator);
        $this->receiveHandler = new ReceiveCashInstrumentHandler(
            $this->connection,
            $this->sessionRepository,
            $this->instrumentRepository,
            $this->routingRepository,
        );
        $this->createInstrumentHandler = new CreateSettlementInstrumentHandler(
            $this->instrumentRepository,
            new SettlementReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->transitionHandler = new TransitionSettlementInstrumentStatusHandler(
            $this->instrumentRepository,
            $this->unitOfWork,
        );
    }

    public function test_receive_instrument_persists_amount_and_currency(): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(12_500);

        $movementId = ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
            sessionId: $fixture['sessionId'],
            instrumentId: $fixture['instrumentId'],
            receivedBy: $fixture['receiverId'],
        ));

        /** @var array{session_id: int|string, instrument_id: int|string, currency_code: string, amount_minor: int|string, created_by: int|string, type_code: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT session_id, instrument_id, currency_code, amount_minor, created_by,
                    (SELECT code FROM cash_movement_type WHERE id = movement_type_id) AS type_code
             FROM cash_movement WHERE id = :id',
            ['id' => $movementId],
        );
        self::assertNotFalse($row);
        self::assertSame($fixture['sessionId'], $this->toInt($row['session_id']));
        self::assertSame($fixture['instrumentId'], $this->toInt($row['instrument_id']));
        self::assertSame('TND', $row['currency_code']);
        self::assertSame(12_500, $this->toInt($row['amount_minor']));
        self::assertSame($fixture['receiverId'], $this->toInt($row['created_by']));
        self::assertSame('instrument_receipt', $row['type_code']);
    }

    public function test_receive_rejects_closed_session(): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(1_000);
        ($this->closeHandler)(new CloseCashSessionCommand(sessionId: $fixture['sessionId']));

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $fixture['sessionId'],
                instrumentId: $fixture['instrumentId'],
            ));
            self::fail('Expected CashSessionNotOpenException');
        } catch (CashSessionNotOpenException $exception) {
            self::assertSame('cash_session.not_open', $exception->errorCode());
            self::assertSame('closed', $exception->context()['status_code']);
        }
    }

    public function test_receive_rejects_validated_session(): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(1_000);
        ($this->closeHandler)(new CloseCashSessionCommand(sessionId: $fixture['sessionId']));
        $this->connection->executeStatement(
            'UPDATE cash_session
             SET status_code = \'validated\', validated_at = now()
             WHERE id = :id',
            ['id' => $fixture['sessionId']],
        );

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $fixture['sessionId'],
                instrumentId: $fixture['instrumentId'],
            ));
            self::fail('Expected CashSessionNotOpenException');
        } catch (CashSessionNotOpenException $exception) {
            self::assertSame('cash_session.not_open', $exception->errorCode());
            self::assertSame('validated', $exception->context()['status_code']);
        }
    }

    public function test_receive_rejects_missing_instrument(): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(1_000);

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $fixture['sessionId'],
                instrumentId: 999_999_970,
            ));
            self::fail('Expected CashReceiveInstrumentNotFoundException');
        } catch (CashReceiveInstrumentNotFoundException $exception) {
            self::assertSame('cash_receive.instrument_not_found', $exception->errorCode());
        }
    }

    public function test_receive_rejects_returned_instrument(): void
    {
        $this->assertInactiveStatusRejected('returned');
    }

    public function test_receive_rejects_cancelled_instrument(): void
    {
        $this->assertInactiveStatusRejected('cancelled');
    }

    public function test_receive_rejects_missing_routing(): void
    {
        $accounts = $this->seedAccounts('MissRoute');
        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(holderAccountId: $accounts['holderId']));
        $methodId = $this->insertTemporaryPaymentMethodWithoutRouting();
        $instrumentId = $this->createInstrument($accounts['holderId'], $methodId, 2_000);

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $sessionId,
                instrumentId: $instrumentId,
            ));
            self::fail('Expected CashReceiveInstrumentRoutingNotCaisseException');
        } catch (CashReceiveInstrumentRoutingNotCaisseException $exception) {
            self::assertSame('cash_receive.routing_not_caisse', $exception->errorCode());
            self::assertNull($exception->context()['routing_type_code']);
        }
    }

    public function test_receive_rejects_non_caisse_routing(): void
    {
        $accounts = $this->seedAccounts('NonCaisse');
        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(holderAccountId: $accounts['holderId']));
        $method = $this->paymentMethodRepository->findByCode('V');
        self::assertNotNull($method);
        $instrumentId = $this->createInstrument($accounts['holderId'], (int) $method->id(), 2_000);

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $sessionId,
                instrumentId: $instrumentId,
            ));
            self::fail('Expected CashReceiveInstrumentRoutingNotCaisseException');
        } catch (CashReceiveInstrumentRoutingNotCaisseException $exception) {
            self::assertSame('cash_receive.routing_not_caisse', $exception->errorCode());
            self::assertSame('direct_bank', $exception->context()['routing_type_code']);
        }
    }

    public function test_receive_rejects_duplicate_in_same_session(): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(3_000);
        ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
            sessionId: $fixture['sessionId'],
            instrumentId: $fixture['instrumentId'],
        ));

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $fixture['sessionId'],
                instrumentId: $fixture['instrumentId'],
            ));
            self::fail('Expected CashReceiveInstrumentAlreadyInSessionException');
        } catch (CashReceiveInstrumentAlreadyInSessionException $exception) {
            self::assertSame('cash_receive.instrument_already_in_session', $exception->errorCode());
        }
    }

    public function test_same_instrument_allowed_in_two_different_sessions(): void
    {
        $accountsA = $this->seedAccounts('SessA');
        $accountsB = $this->seedAccounts('SessB');
        $sessionA = ($this->openHandler)(new OpenCashSessionCommand(holderAccountId: $accountsA['holderId']));
        $sessionB = ($this->openHandler)(new OpenCashSessionCommand(holderAccountId: $accountsB['holderId']));
        $method = $this->paymentMethodRepository->findByCode('C');
        self::assertNotNull($method);
        $instrumentId = $this->createInstrument($accountsA['holderId'], (int) $method->id(), 4_000);

        $movementA = ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
            sessionId: $sessionA,
            instrumentId: $instrumentId,
        ));
        $movementB = ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
            sessionId: $sessionB,
            instrumentId: $instrumentId,
        ));

        self::assertNotSame($movementA, $movementB);
    }

    public function test_receive_rejects_missing_received_by_before_sql(): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(1_000);

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $fixture['sessionId'],
                instrumentId: $fixture['instrumentId'],
                receivedBy: 999_999_969,
            ));
            self::fail('Expected CashReceiveReceivedByNotFoundException');
        } catch (CashReceiveReceivedByNotFoundException $exception) {
            self::assertSame('cash_receive.received_by_not_found', $exception->errorCode());
        }

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM cash_movement WHERE session_id = :sid AND instrument_id = :iid',
            ['sid' => $fixture['sessionId'], 'iid' => $fixture['instrumentId']],
        );
        self::assertSame(0, (int) (is_numeric($count) ? $count : 0));
    }

    private function assertInactiveStatusRejected(string $status): void
    {
        $fixture = $this->seedOpenSessionWithCaisseInstrument(1_500);
        ($this->transitionHandler)(new TransitionSettlementInstrumentStatusCommand(
            instrumentId: $fixture['instrumentId'],
            statusCode: $status,
            reason: 'test',
        ));

        try {
            ($this->receiveHandler)(new ReceiveCashInstrumentCommand(
                sessionId: $fixture['sessionId'],
                instrumentId: $fixture['instrumentId'],
            ));
            self::fail('Expected CashReceiveInstrumentNotActiveException');
        } catch (CashReceiveInstrumentNotActiveException $exception) {
            self::assertSame('cash_receive.instrument_not_active', $exception->errorCode());
            self::assertSame($status, $exception->context()['status_code']);
        }
    }

    /**
     * @return array{sessionId: int, instrumentId: int, receiverId: int, holderId: int}
     */
    private function seedOpenSessionWithCaisseInstrument(int $amountMinor): array
    {
        $accounts = $this->seedAccounts('Recv');
        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
            officeAccountId: $accounts['officeId'],
            openedBy: $accounts['openerId'],
        ));
        $method = $this->paymentMethodRepository->findByCode('C');
        self::assertNotNull($method);
        $routing = $this->routingRepository->findByPaymentMethodId((int) $method->id());
        self::assertNotNull($routing);
        self::assertSame('cash_session', $routing->routingTypeCode());

        $instrumentId = $this->createInstrument($accounts['holderId'], (int) $method->id(), $amountMinor);

        return [
            'sessionId' => $sessionId,
            'instrumentId' => $instrumentId,
            'receiverId' => $accounts['openerId'],
            'holderId' => $accounts['holderId'],
        ];
    }

    private function createInstrument(int $partyAccountId, int $paymentMethodId, int $amountMinor): int
    {
        $instrument = ($this->createInstrumentHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: $partyAccountId,
            partyRole: 'customer',
            currencyCode: 'TND',
            paymentMethodId: $paymentMethodId,
            amountMinor: $amountMinor,
        ));

        return (int) $instrument->id();
    }

    private function insertTemporaryPaymentMethodWithoutRouting(): int
    {
        $code = 'T'.strtoupper(bin2hex(random_bytes(1))); // 3 chars — VARCHAR(4)
        $this->connection->executeStatement(
            'INSERT INTO settlement_payment_method (code, label, is_active)
             VALUES (:code, :label, true)',
            ['code' => $code, 'label' => 'Temp no routing '.$code],
        );
        $id = $this->connection->fetchOne(
            'SELECT id FROM settlement_payment_method WHERE code = :code',
            ['code' => $code],
        );
        self::assertNotFalse($id);
        self::assertTrue(is_numeric($id));

        return (int) $id;
    }

    private function toInt(int|string $value): int
    {
        return is_int($value) ? $value : (int) $value;
    }

    /**
     * @return array{holderId: int, officeId: int, openerId: int}
     */
    private function seedAccounts(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $holder = PartyAccount::createOrganization(
            $label.' Holder '.$suffix,
            Email::fromString('cash.recv.holder.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Office '.$suffix,
            Email::fromString('cash.recv.office.'.$suffix.'@example.com'),
        );
        $opener = PartyAccount::createPerson(
            $label.' Opener '.$suffix,
            Email::fromString('cash.recv.opener.'.$suffix.'@example.com'),
        );

        $this->accountRepository->save($holder);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();
        $this->accountRepository->save($opener);
        $this->unitOfWork->commit();

        return [
            'holderId' => (int) $holder->id(),
            'officeId' => (int) $office->id(),
            'openerId' => (int) $opener->id(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\CashManagement\Infrastructure;

use App\Modules\CashManagement\Application\CreateCashPaymentMethodRouting\CreateCashPaymentMethodRoutingCommand;
use App\Modules\CashManagement\Application\CreateCashPaymentMethodRouting\CreateCashPaymentMethodRoutingHandler;
use App\Modules\CashManagement\Application\UpdateCashPaymentMethodRouting\UpdateCashPaymentMethodRoutingCommand;
use App\Modules\CashManagement\Application\UpdateCashPaymentMethodRouting\UpdateCashPaymentMethodRoutingHandler;
use App\Modules\CashManagement\Domain\Exception\CashPaymentMethodRoutingAlreadyExistsException;
use App\Modules\CashManagement\Domain\Exception\InvalidCashPaymentMethodRoutingException;
use App\Modules\CashManagement\Domain\Repository\CashPaymentMethodRoutingRepositoryInterface;
use App\Modules\CashManagement\Domain\Repository\CashRoutingTypeRepositoryInterface;
use App\Modules\CashManagement\Domain\ValueObject\InstrumentTrackingMode;
use App\Modules\Settlement\Domain\Repository\SettlementPaymentMethodRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — référentiel routing Cash Management.
 */
final class CashPaymentMethodRoutingPersistenceTest extends KernelTestCase
{
    /** @var array<string, string> code => routing_type_code attendu (11 modes seedés) */
    private const SEEDED_ROUTING = [
        'AD' => 'aucun',
        'CB' => 'aucun',
        'PE' => 'aucun',
        'C' => 'caisse',
        'LC' => 'caisse',
        'E' => 'caisse',
        'PC' => 'transmission_externe',
        'V' => 'banque_directe',
        'VE' => 'banque_directe',
        'RC' => 'aucun',
        'RI' => 'aucun',
    ];

    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private Connection $connection;

    private SettlementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private CashRoutingTypeRepositoryInterface $routingTypeRepository;

    private CashPaymentMethodRoutingRepositoryInterface $routingRepository;

    private CreateCashPaymentMethodRoutingHandler $createHandler;

    private UpdateCashPaymentMethodRoutingHandler $updateHandler;

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

        /** @var SettlementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(SettlementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var CashRoutingTypeRepositoryInterface $routingTypeRepository */
        $routingTypeRepository = $container->get(CashRoutingTypeRepositoryInterface::class);
        $this->routingTypeRepository = $routingTypeRepository;

        /** @var CashPaymentMethodRoutingRepositoryInterface $routingRepository */
        $routingRepository = $container->get(CashPaymentMethodRoutingRepositoryInterface::class);
        $this->routingRepository = $routingRepository;

        $this->createHandler = new CreateCashPaymentMethodRoutingHandler(
            $this->paymentMethodRepository,
            $this->routingTypeRepository,
            $this->routingRepository,
            $this->unitOfWork,
        );
        $this->updateHandler = new UpdateCashPaymentMethodRoutingHandler(
            $this->routingTypeRepository,
            $this->routingRepository,
            $this->unitOfWork,
        );
    }

    public function test_seeded_routing_types_are_readable(): void
    {
        foreach (['caisse', 'banque_directe', 'transmission_externe', 'aucun'] as $code) {
            $type = $this->routingTypeRepository->findByCode($code);
            self::assertNotNull($type, $code);
            self::assertSame($code, $type->code());
            self::assertNotSame('', $type->label());
        }
    }

    public function test_seeded_payment_method_routing_matches_schema(): void
    {
        self::assertCount(11, self::SEEDED_ROUTING);

        foreach (self::SEEDED_ROUTING as $methodCode => $expectedRouting) {
            $method = $this->paymentMethodRepository->findByCode($methodCode);
            self::assertNotNull($method, $methodCode);
            self::assertNotNull($method->id());

            $routing = $this->routingRepository->findByPaymentMethodId((int) $method->id());
            self::assertNotNull($routing, 'seed manquant pour '.$methodCode);
            self::assertSame($expectedRouting, $routing->routingTypeCode(), $methodCode);
        }

        $rawCount = $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM cash_payment_method_routing r
             JOIN settlement_payment_method m ON m.id = r.payment_method_id
             WHERE m.code IN (\'AD\',\'CB\',\'PE\',\'C\',\'LC\',\'E\',\'PC\',\'V\',\'VE\',\'RC\',\'RI\')',
        );
        self::assertNotFalse($rawCount);
        self::assertSame(11, (int) (is_numeric($rawCount) ? $rawCount : 0));
    }

    public function test_create_aucun_with_individual_rejected_before_sql(): void
    {
        $methodId = $this->insertTemporaryPaymentMethod('TX');

        try {
            ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
                paymentMethodId: $methodId,
                routingTypeCode: 'aucun',
                instrumentTrackingMode: 'individual',
                strictSourceIsolation: false,
            ));
            self::fail('Expected InvalidCashPaymentMethodRoutingException');
        } catch (InvalidCashPaymentMethodRoutingException $exception) {
            self::assertSame('cash_payment_method_routing.inconsistent_tracking', $exception->errorCode());
        }

        self::assertNull($this->routingRepository->findByPaymentMethodId($methodId));
    }

    public function test_create_caisse_with_not_applicable_rejected_before_sql(): void
    {
        $methodId = $this->insertTemporaryPaymentMethod('TY');

        try {
            ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
                paymentMethodId: $methodId,
                routingTypeCode: 'caisse',
                instrumentTrackingMode: 'not_applicable',
                strictSourceIsolation: false,
            ));
            self::fail('Expected InvalidCashPaymentMethodRoutingException');
        } catch (InvalidCashPaymentMethodRoutingException $exception) {
            self::assertSame('cash_payment_method_routing.inconsistent_tracking', $exception->errorCode());
        }

        self::assertNull($this->routingRepository->findByPaymentMethodId($methodId));
    }

    public function test_create_valid_round_trip(): void
    {
        $methodId = $this->insertTemporaryPaymentMethod('TZ');

        $created = ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
            paymentMethodId: $methodId,
            routingTypeCode: 'caisse',
            instrumentTrackingMode: 'individual',
            strictSourceIsolation: true,
            requiresCustodyCheck: true,
            isActive: true,
        ));

        self::assertSame($methodId, $created->paymentMethodId());
        self::assertSame('caisse', $created->routingTypeCode());
        self::assertSame(InstrumentTrackingMode::Individual, $created->instrumentTrackingMode());
        self::assertTrue($created->strictSourceIsolation());

        $this->em->clear();

        $reloaded = $this->routingRepository->findByPaymentMethodId($methodId);
        self::assertNotNull($reloaded);
        self::assertSame('caisse', $reloaded->routingTypeCode());
        self::assertSame(InstrumentTrackingMode::Individual, $reloaded->instrumentTrackingMode());
        self::assertTrue($reloaded->strictSourceIsolation());
        self::assertTrue($reloaded->requiresCustodyCheck());
        self::assertTrue($reloaded->isActive());
    }

    public function test_update_inconsistent_tracking_rejected(): void
    {
        $cheque = $this->paymentMethodRepository->findByCode('C');
        self::assertNotNull($cheque);
        $methodId = (int) $cheque->id();

        try {
            ($this->updateHandler)(new UpdateCashPaymentMethodRoutingCommand(
                paymentMethodId: $methodId,
                routingTypeCode: 'banque_directe',
                instrumentTrackingMode: 'not_applicable',
                strictSourceIsolation: false,
                requiresCustodyCheck: true,
                isActive: true,
            ));
            self::fail('Expected InvalidCashPaymentMethodRoutingException');
        } catch (InvalidCashPaymentMethodRoutingException $exception) {
            self::assertSame('cash_payment_method_routing.inconsistent_tracking', $exception->errorCode());
        }

        $this->em->clear();
        $reloaded = $this->routingRepository->findByPaymentMethodId($methodId);
        self::assertNotNull($reloaded);
        self::assertSame('caisse', $reloaded->routingTypeCode());
        self::assertSame(InstrumentTrackingMode::Individual, $reloaded->instrumentTrackingMode());
    }

    public function test_duplicate_payment_method_routing_rejected(): void
    {
        $cheque = $this->paymentMethodRepository->findByCode('E');
        self::assertNotNull($cheque);
        $methodId = (int) $cheque->id();

        try {
            ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
                paymentMethodId: $methodId,
                routingTypeCode: 'caisse',
                instrumentTrackingMode: 'individual',
                strictSourceIsolation: false,
            ));
            self::fail('Expected CashPaymentMethodRoutingAlreadyExistsException');
        } catch (CashPaymentMethodRoutingAlreadyExistsException $exception) {
            self::assertSame('cash_payment_method_routing.already_exists', $exception->errorCode());
        }
    }

    public function test_update_valid_round_trip(): void
    {
        $methodId = $this->insertTemporaryPaymentMethod('TU');

        ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
            paymentMethodId: $methodId,
            routingTypeCode: 'caisse',
            instrumentTrackingMode: 'individual',
            strictSourceIsolation: false,
        ));

        $updated = ($this->updateHandler)(new UpdateCashPaymentMethodRoutingCommand(
            paymentMethodId: $methodId,
            routingTypeCode: 'transmission_externe',
            instrumentTrackingMode: 'individual',
            strictSourceIsolation: false,
            requiresCustodyCheck: false,
            isActive: true,
        ));

        self::assertSame('transmission_externe', $updated->routingTypeCode());
        self::assertFalse($updated->requiresCustodyCheck());

        $this->em->clear();
        $reloaded = $this->routingRepository->findByPaymentMethodId($methodId);
        self::assertNotNull($reloaded);
        self::assertSame('transmission_externe', $reloaded->routingTypeCode());
        self::assertFalse($reloaded->requiresCustodyCheck());
    }

    private function insertTemporaryPaymentMethod(string $code): int
    {
        $this->connection->executeStatement(
            'INSERT INTO settlement_payment_method (public_id, code, label, is_cash_like, is_active)
             VALUES (:public_id, :code, :label, false, true)
             ON CONFLICT (code) DO NOTHING',
            [
                'public_id' => PublicId::generate()->toString(),
                'code' => $code,
                'label' => 'Temp '.$code,
            ],
        );

        $id = $this->connection->fetchOne(
            'SELECT id FROM settlement_payment_method WHERE code = :code',
            ['code' => $code],
        );
        self::assertNotFalse($id);
        self::assertNotNull($id);
        self::assertTrue(is_numeric($id));

        $paymentMethodId = (int) $id;

        $this->connection->executeStatement(
            'DELETE FROM cash_payment_method_routing WHERE payment_method_id = :id',
            ['id' => $paymentMethodId],
        );
        $this->em->clear();

        return $paymentMethodId;
    }
}

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
use App\Modules\Reglements\Domain\Repository\ReglementPaymentMethodRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — référentiel routing Cash Management.
 */
final class CashPaymentMethodRoutingPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private ReglementPaymentMethodRepositoryInterface $paymentMethodRepository;

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

        /** @var ReglementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(ReglementPaymentMethodRepositoryInterface::class);
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

    public function test_create_aucun_with_individual_rejected_before_sql(): void
    {
        $methodId = $this->unusedPaymentMethodId('CB');

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
        $methodId = $this->unusedPaymentMethodId('V');

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
        $methodId = $this->unusedPaymentMethodId('E');

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
        $methodId = $this->unusedPaymentMethodId('C');

        ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
            paymentMethodId: $methodId,
            routingTypeCode: 'caisse',
            instrumentTrackingMode: 'individual',
            strictSourceIsolation: false,
        ));

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
        $methodId = $this->unusedPaymentMethodId('LC');

        ($this->createHandler)(new CreateCashPaymentMethodRoutingCommand(
            paymentMethodId: $methodId,
            routingTypeCode: 'caisse',
            instrumentTrackingMode: 'aggregate',
            strictSourceIsolation: false,
        ));

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
        $methodId = $this->unusedPaymentMethodId('PC');

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

    /**
     * Choisit un payment_method seedé sans routing déjà posé (tests non isolés en truncate).
     */
    private function unusedPaymentMethodId(string $preferredCode): int
    {
        $preferred = $this->paymentMethodRepository->findByCode($preferredCode);
        self::assertNotNull($preferred);
        self::assertNotNull($preferred->id());

        if ($this->routingRepository->findByPaymentMethodId((int) $preferred->id()) === null) {
            return (int) $preferred->id();
        }

        foreach (['AD', 'CB', 'C', 'E', 'V', 'VE', 'LC', 'PC', 'RC', 'PE', 'RI'] as $code) {
            $method = $this->paymentMethodRepository->findByCode($code);
            self::assertNotNull($method);
            self::assertNotNull($method->id());
            if ($this->routingRepository->findByPaymentMethodId((int) $method->id()) === null) {
                return (int) $method->id();
            }
        }

        self::fail('Aucun payment_method libre pour le test routing');
    }
}

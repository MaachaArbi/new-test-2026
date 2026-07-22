<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure\Command;

use App\Modules\Party\Application\ListPartyAccounts\ListPartyAccountsHandler;
use App\Modules\Party\Application\ListPartyAccounts\ListPartyAccountsQuery;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Party\Infrastructure\Command\PurgeTestPartyAccountsCommand;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — soft-delete Domain + protection myGO + liste filtrée.
 */
final class PurgeTestPartyAccountsCommandTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountRepositoryInterface $accounts;

    private Connection $connection;

    private ListPartyAccountsHandler $listHandler;

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

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        $this->accounts = $accounts;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;

        /** @var ListPartyAccountsHandler $listHandler */
        $listHandler = $container->get(ListPartyAccountsHandler::class);
        $this->listHandler = $listHandler;
    }

    public function test_refuses_non_dev_test_environment(): void
    {
        $command = new PurgeTestPartyAccountsCommand(
            $this->connection,
            $this->accounts,
            $this->unitOfWork,
            'prod',
        );
        $tester = new CommandTester($command);
        $status = $tester->execute(['--execute' => true, '--force' => true]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Refusé', $tester->getDisplay());
        self::assertStringContainsString('prod', $tester->getDisplay());
    }

    public function test_dry_run_then_execute_soft_deletes_tests_keeps_agency_and_hides_from_list(): void
    {
        $this->ensureAgencyPresent();

        $suffix = bin2hex(random_bytes(4));
        $person = PartyAccount::createPerson(
            'RoundTrip Org '.$suffix,
            Email::fromString('purge.person.'.$suffix.'@example.com'),
        );
        $org = PartyAccount::createOrganization(
            'Fn RoundTrip Org '.$suffix,
            Email::fromString('purge.org.'.$suffix.'@example.com'),
        );
        $nullEmail = PartyAccount::createPerson('LocalCred '.$suffix);

        $this->accounts->save($person);
        $this->accounts->save($org);
        $this->accounts->save($nullEmail);
        $this->unitOfWork->commit();
        $this->em->clear();

        $personId = (int) $person->id();
        $orgId = (int) $org->id();
        $nullEmailId = (int) $nullEmail->id();
        $needle = $suffix;
        $agencyId = (int) $this->agencyId();

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('app:party:purge-test-accounts');
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('dry-run', strtolower($tester->getDisplay()));
        self::assertNotFalse($this->connection->fetchOne(
            'SELECT 1 FROM party_account WHERE id = :id AND deleted_at IS NULL',
            ['id' => $personId],
        ));

        self::assertSame(0, $tester->execute(['--execute' => true, '--force' => true]));
        self::assertStringContainsString('Soft-delete OK', $tester->getDisplay());

        // Lignes toujours présentes, soft-deleted
        foreach ([$personId, $orgId, $nullEmailId] as $id) {
            self::assertNotFalse($this->connection->fetchOne(
                'SELECT 1 FROM party_account WHERE id = :id AND deleted_at IS NOT NULL',
                ['id' => $id],
            ));
        }

        self::assertTrue((bool) $this->connection->fetchOne(
            'SELECT 1 FROM party_account WHERE id = :id AND display_name = :name AND deleted_at IS NULL',
            ['id' => $agencyId, 'name' => 'myGO'],
        ));

        $listed = ($this->listHandler)(new ListPartyAccountsQuery(
            page: 1,
            limit: 100,
            search: $needle,
        ));
        self::assertSame(0, $listed->total);
        self::assertSame([], $listed->data);
    }

    private function ensureAgencyPresent(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('app:party:bootstrap-agency'));
        self::assertSame(0, $tester->execute([]));
        $this->em->clear();
    }

    private function agencyId(): int
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM party_account WHERE display_name = :name AND deleted_at IS NULL LIMIT 1',
            ['name' => 'myGO'],
        );
        self::assertNotFalse($id);
        self::assertTrue(is_numeric($id));

        return (int) $id;
    }
}

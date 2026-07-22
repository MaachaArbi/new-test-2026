<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Modules\Party\Domain\ValueObject\PartyAccountNature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel uniquement — jamais SQLite.
 */
final class PartyAccountPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountRepositoryInterface $repository;

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

        /** @var PartyAccountRepositoryInterface $repository */
        $repository = $container->get(PartyAccountRepositoryInterface::class);
        $this->repository = $repository;
    }

    public function test_round_trip_persists_and_reloads_mapped_fields(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $displayName = 'RoundTrip Org '.$suffix;
        $email = Email::fromString('roundtrip.'.$suffix.'@example.com');

        $account = PartyAccount::createOrganization($displayName, $email, parentAccountId: null);
        $publicId = $account->publicId()->toString();

        $this->repository->save($account);
        $this->unitOfWork->commit();
        $id = $account->id();
        self::assertNotNull($id);

        $this->em->clear();

        $reloaded = $this->repository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($publicId, $reloaded->publicId()->toString());
        self::assertSame(PartyAccountNature::Organization, $reloaded->nature());
        self::assertSame($displayName, $reloaded->displayName());
        self::assertNotNull($reloaded->email());
        self::assertTrue($reloaded->email()->equals($email));
        self::assertNull($reloaded->parentAccountId());
        self::assertFalse($reloaded->isDisabled());
        self::assertFalse($reloaded->isProspect());
        self::assertFalse($reloaded->isDisputed());
    }

    public function test_bootstrap_agency_command_is_idempotent(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(\Symfony\Component\HttpKernel\KernelInterface::class, $kernel);

        $application = new Application($kernel);
        $command = $application->find('app:party:bootstrap-agency');
        $tester = new CommandTester($command);

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());

        $countAfterFirst = $this->countAgencyAccounts();
        self::assertSame(1, $countAfterFirst);

        $agency = $this->findAgencyAccount();
        self::assertNotNull($agency);
        self::assertSame('myGO', $agency->displayName());
        self::assertSame(PartyAccountNature::Organization, $agency->nature());
        self::assertNotNull($agency->email());
        self::assertTrue($agency->email()->equals(Email::fromString('booking@mygo.pro')));

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('déjà présent', $tester->getDisplay());
        self::assertSame(1, $this->countAgencyAccounts());
    }

    private function countAgencyAccounts(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PartyAccount::class, 'p')
            ->where('p.displayName = :name')
            ->setParameter('name', 'myGO')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findAgencyAccount(): ?PartyAccount
    {
        /** @var PartyAccount|null $account */
        $account = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PartyAccount::class, 'p')
            ->where('p.displayName = :name')
            ->setParameter('name', 'myGO')
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }
}

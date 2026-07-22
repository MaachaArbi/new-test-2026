<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure;

use App\Modules\Party\Application\SetPartyAccountOffice\SetPartyAccountOfficeCommand;
use App\Modules\Party\Application\SetPartyAccountOffice\SetPartyAccountOfficeHandler;
use App\Modules\Party\Application\SetPartyAccountOrganizationIdentity\SetPartyAccountOrganizationIdentityCommand;
use App\Modules\Party\Application\SetPartyAccountOrganizationIdentity\SetPartyAccountOrganizationIdentityHandler;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Exception\PartyAccountMustBeOrganizationException;
use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Exception\PartyAccountOfficeCodeAlreadyUsedException;
use App\Modules\Party\Domain\Repository\PartyAccountOfficeRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountOrganizationIdentityRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — extensions 1-1 organization_identity + office.
 */
final class PartyAccountOrganizationIdentityAndOfficePersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountRepositoryInterface $accountRepository;

    private PartyAccountOrganizationIdentityRepositoryInterface $identityRepository;

    private PartyAccountOfficeRepositoryInterface $officeRepository;

    private SetPartyAccountOrganizationIdentityHandler $setIdentityHandler;

    private SetPartyAccountOfficeHandler $setOfficeHandler;

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

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var PartyAccountOrganizationIdentityRepositoryInterface $identityRepository */
        $identityRepository = $container->get(PartyAccountOrganizationIdentityRepositoryInterface::class);
        $this->identityRepository = $identityRepository;

        /** @var PartyAccountOfficeRepositoryInterface $officeRepository */
        $officeRepository = $container->get(PartyAccountOfficeRepositoryInterface::class);
        $this->officeRepository = $officeRepository;

        $this->setIdentityHandler = new SetPartyAccountOrganizationIdentityHandler(
            $this->accountRepository,
            $this->identityRepository,

            $this->unitOfWork
);
        $this->setOfficeHandler = new SetPartyAccountOfficeHandler(
            $this->accountRepository,
            $this->officeRepository,

            $this->unitOfWork
);
    }

    public function test_organization_identity_round_trip(): void
    {
        $account = $this->createOrganization('Identity RT');

        ($this->setIdentityHandler)(new SetPartyAccountOrganizationIdentityCommand(
            accountId: (int) $account->id(),
            taxId: 'TAX-RT-001',
            tradeRegister: 'RC-001',
            legalFormCode: 'SARL',
            isVatSubject: true,
            website: 'https://example.org',
        ));

        $this->em->clear();

        $reloaded = $this->identityRepository->findByAccountId((int) $account->id());
        self::assertNotNull($reloaded);
        self::assertSame($account->id(), $reloaded->accountId());
        self::assertSame('TAX-RT-001', $reloaded->taxId());
        self::assertSame('RC-001', $reloaded->tradeRegister());
        self::assertSame('SARL', $reloaded->legalFormCode());
        self::assertTrue($reloaded->isVatSubject());
        self::assertSame('https://example.org', $reloaded->website());
    }

    public function test_office_round_trip(): void
    {
        $suffix = bin2hex(random_bytes(3));
        $account = $this->createOrganization('Office RT '.$suffix);

        ($this->setOfficeHandler)(new SetPartyAccountOfficeCommand(
            accountId: (int) $account->id(),
            officeCode: 'OFC-'.$suffix,
            defaultCurrencyCode: 'TND',
        ));

        $this->em->clear();

        $reloaded = $this->officeRepository->findByAccountId((int) $account->id());
        self::assertNotNull($reloaded);
        self::assertSame($account->id(), $reloaded->accountId());
        self::assertSame('OFC-'.$suffix, $reloaded->officeCode());
        self::assertSame('TND', $reloaded->defaultCurrencyCode());
    }

    public function test_identity_rejected_when_account_missing(): void
    {
        $missingId = 999_999_991;

        try {
            ($this->setIdentityHandler)(new SetPartyAccountOrganizationIdentityCommand(
                $missingId,
                'TAX',
                null,
                null,
                false,
                null,
            ));
            self::fail('Expected PartyAccountNotFoundException');
        } catch (PartyAccountNotFoundException $exception) {
            self::assertSame('party_account.not_found', $exception->errorCode());
            self::assertSame(['account_id' => $missingId], $exception->context());
        }
    }

    public function test_identity_rejected_for_person_account(): void
    {
        $person = $this->createPerson('Identity Person Reject');

        try {
            ($this->setIdentityHandler)(new SetPartyAccountOrganizationIdentityCommand(
                (int) $person->id(),
                'TAX',
                null,
                null,
                false,
                null,
            ));
            self::fail('Expected PartyAccountMustBeOrganizationException');
        } catch (PartyAccountMustBeOrganizationException $exception) {
            self::assertSame('party_account.must_be_organization', $exception->errorCode());
        }
    }

    public function test_office_rejected_for_person_account(): void
    {
        $person = $this->createPerson('Office Person Reject');
        $suffix = bin2hex(random_bytes(3));

        try {
            ($this->setOfficeHandler)(new SetPartyAccountOfficeCommand(
                (int) $person->id(),
                'PERS-'.$suffix,
                'TND',
            ));
            self::fail('Expected PartyAccountMustBeOrganizationException');
        } catch (PartyAccountMustBeOrganizationException $exception) {
            self::assertSame('party_account.must_be_organization', $exception->errorCode());
        }
    }

    public function test_duplicate_office_code_is_rejected(): void
    {
        $suffix = bin2hex(random_bytes(3));
        $code = 'DUP-'.$suffix;
        $first = $this->createOrganization('Office Dup A '.$suffix);
        $second = $this->createOrganization('Office Dup B '.$suffix);

        ($this->setOfficeHandler)(new SetPartyAccountOfficeCommand(
            (int) $first->id(),
            $code,
            'TND',
        ));

        try {
            ($this->setOfficeHandler)(new SetPartyAccountOfficeCommand(
                (int) $second->id(),
                $code,
                'TND',
            ));
            self::fail('Expected PartyAccountOfficeCodeAlreadyUsedException');
        } catch (PartyAccountOfficeCodeAlreadyUsedException $exception) {
            self::assertSame('party_account_office.code_already_used', $exception->errorCode());
            self::assertSame(['office_code' => $code], $exception->context());
        }
    }

    public function test_bootstrap_agency_writes_identity_and_office_idempotently(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(\Symfony\Component\HttpKernel\KernelInterface::class, $kernel);

        $application = new Application($kernel);
        $tester = new CommandTester($application->find('app:party:bootstrap-agency'));

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());

        $agency = $this->findAgencyAccount();
        self::assertNotNull($agency);
        $accountId = (int) $agency->id();

        $this->em->clear();

        $identity = $this->identityRepository->findByAccountId($accountId);
        self::assertNotNull($identity);
        self::assertSame('14455455AM000', $identity->taxId());
        self::assertSame('https://www.mygo.co', $identity->website());
        self::assertFalse($identity->isVatSubject());

        $office = $this->officeRepository->findByAccountId($accountId);
        self::assertNotNull($office);
        self::assertSame('MYGO-2023', $office->officeCode());
        self::assertSame('TND', $office->defaultCurrencyCode());

        $identityCount = $this->countIdentitiesFor($accountId);
        $officeCount = $this->countOfficesFor($accountId);
        self::assertSame(1, $identityCount);
        self::assertSame(1, $officeCount);

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('déjà présent', $tester->getDisplay());
        self::assertStringContainsString('skip', $tester->getDisplay());

        self::assertSame(1, $this->countIdentitiesFor($accountId));
        self::assertSame(1, $this->countOfficesFor($accountId));
        self::assertSame(1, $this->countOfficesByCode('MYGO-2023'));
    }

    private function createOrganization(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString('org.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        return $account;
    }

    private function createPerson(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createPerson(
            $label.' '.$suffix,
            Email::fromString('person.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        return $account;
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

    private function countIdentitiesFor(int $accountId): int
    {
        return $this->fetchCount(
            'SELECT COUNT(*) FROM party_account_organization_identity WHERE account_id = :id',
            ['id' => $accountId],
        );
    }

    private function countOfficesFor(int $accountId): int
    {
        return $this->fetchCount(
            'SELECT COUNT(*) FROM party_account_office WHERE account_id = :id',
            ['id' => $accountId],
        );
    }

    private function countOfficesByCode(string $code): int
    {
        return $this->fetchCount(
            'SELECT COUNT(*) FROM party_account_office WHERE office_code = :code',
            ['code' => $code],
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchCount(string $sql, array $params): int
    {
        $value = $this->em->getConnection()->fetchOne($sql, $params);
        self::assertIsNumeric($value);

        return (int) $value;
    }
}

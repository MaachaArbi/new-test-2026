<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Core\Infrastructure;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — round-trips CoreCredential.
 */
final class CoreCredentialPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $entityManager;

    private CoreCredentialRepositoryInterface $credentialRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var CoreCredentialRepositoryInterface $credentialRepository */
        $credentialRepository = $container->get(CoreCredentialRepositoryInterface::class);
        $this->credentialRepository = $credentialRepository;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;
    }

    public function test_round_trip_create_local(): void
    {
        $accountId = $this->createPersonAccount('LocalCred');
        $opaqueHash = '$argon2id$v=19$m=65536,t=4,p=1$opaque-local-'.$accountId;

        $credential = CoreCredential::createLocal($accountId, $opaqueHash, isPrimary: true);
        $this->credentialRepository->save($credential);
        $this->unitOfWork->commit();

        $id = $credential->id();
        self::assertNotNull($id);

        $this->entityManager->clear();

        $reloaded = $this->credentialRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($accountId, $reloaded->accountId());
        self::assertSame(CredentialProvider::Local, $reloaded->provider());
        self::assertNull($reloaded->providerUserId());
        self::assertSame($opaqueHash, $reloaded->passwordHash());
        self::assertTrue($reloaded->isPrimary());
        self::assertTrue($reloaded->isEnabled());
        self::assertNull($reloaded->lastLoginAt());
    }

    public function test_round_trip_create_oauth(): void
    {
        $accountId = $this->createPersonAccount('OAuthCred');
        $providerUserId = 'google-sub-'.$accountId;

        $credential = CoreCredential::createOAuth(
            $accountId,
            CredentialProvider::Google,
            $providerUserId,
            isPrimary: false,
        );
        $this->credentialRepository->save($credential);
        $this->unitOfWork->commit();

        $id = $credential->id();
        self::assertNotNull($id);

        $this->entityManager->clear();

        $reloaded = $this->credentialRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame(CredentialProvider::Google, $reloaded->provider());
        self::assertSame($providerUserId, $reloaded->providerUserId());
        self::assertNull($reloaded->passwordHash());
        self::assertFalse($reloaded->isPrimary());
    }

    public function test_find_by_provider_identity(): void
    {
        $accountId = $this->createPersonAccount('FindByIdentity');
        $providerUserId = 'fb-uid-'.$accountId;

        $credential = CoreCredential::createOAuth(
            $accountId,
            CredentialProvider::Facebook,
            $providerUserId,
            isPrimary: true,
        );
        $this->credentialRepository->save($credential);
        $this->unitOfWork->commit();
        $id = $credential->id();
        self::assertNotNull($id);

        $this->entityManager->clear();

        $found = $this->credentialRepository->findByProviderIdentity(
            CredentialProvider::Facebook,
            $providerUserId,
        );
        self::assertNotNull($found);
        self::assertSame($id, $found->id());
        self::assertSame($accountId, $found->accountId());

        self::assertNull(
            $this->credentialRepository->findByProviderIdentity(
                CredentialProvider::Facebook,
                'missing-'.$providerUserId,
            ),
        );
    }

    public function test_find_active_by_account_id_returns_multiple_credentials(): void
    {
        $accountId = $this->createPersonAccount('MultiCred');

        $local = CoreCredential::createLocal(
            $accountId,
            '$argon2id$opaque-multi-'.$accountId,
            isPrimary: true,
        );
        $google = CoreCredential::createOAuth(
            $accountId,
            CredentialProvider::Google,
            'google-multi-'.$accountId,
            isPrimary: false,
        );
        $this->credentialRepository->save($local);
        $this->unitOfWork->commit();
        $this->credentialRepository->save($google);
        $this->unitOfWork->commit();

        $disabled = CoreCredential::createOAuth(
            $accountId,
            CredentialProvider::ApiKey,
            'api-disabled-'.$accountId,
            isPrimary: false,
        );
        $disabled->disable();
        $this->credentialRepository->save($disabled);
        $this->unitOfWork->commit();

        $this->entityManager->clear();

        $active = $this->credentialRepository->findActiveByAccountId($accountId);
        self::assertCount(2, $active);

        $providers = array_map(
            static fn (CoreCredential $credential): string => $credential->provider()->value,
            $active,
        );
        sort($providers);
        self::assertSame(['google', 'local'], $providers);

        foreach ($active as $credential) {
            self::assertTrue($credential->isEnabled());
        }
    }

    private function createPersonAccount(string $label): int
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createPerson($label.' '.$suffix);
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        $accountId = $account->id();
        self::assertNotNull($accountId);

        return $accountId;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure\Http;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class GetPartyAccountControllerTest extends WebTestCase
{
    public function test_get_existing_account_returns_200_without_internal_id(): void
    {
        $client = static::createClient();
        $auth = $this->createAuthenticatedSession($client);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$auth['publicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
            ],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('id', $payload);
        self::assertSame($auth['publicId'], $payload['publicId']);
        self::assertSame('person', $payload['nature']);
        self::assertSame($auth['displayName'], $payload['displayName']);
        self::assertSame($auth['email'], $payload['email']);
    }

    public function test_get_missing_account_returns_404_translated_fr_and_en(): void
    {
        $missingPublicId = '00000000-0000-4000-8000-000000000099';

        $client = static::createClient();
        $auth = $this->createAuthenticatedSession($client);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$missingPublicId,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'fr',
                'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
            ],
        );

        self::assertResponseStatusCodeSame(404);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{error: array{code: string, message: string, context: array<string, mixed>}} $frPayload */
        $frPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('party_account.not_found', $frPayload['error']['code']);
        self::assertSame('Compte party introuvable.', $frPayload['error']['message']);
        self::assertSame($missingPublicId, $frPayload['error']['context']['public_id']);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$missingPublicId,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
            ],
        );

        self::assertResponseStatusCodeSame(404);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{error: array{code: string, message: string}} $enPayload */
        $enPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('party_account.not_found', $enPayload['error']['code']);
        self::assertSame('Party account not found.', $enPayload['error']['message']);
    }

    public function test_technical_exception_returns_generic_500_without_leak(): void
    {
        $client = static::createClient();
        $auth = $this->createAuthenticatedSession($client);

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = static::getContainer()->get(PartyAccountRepositoryInterface::class);
        $account = $accounts->findByEmail(Email::fromString($auth['email']));
        self::assertNotNull($account);

        static::ensureKernelShutdown();
        $client = static::createClient();

        $secretMessage = 'SECRET_LEAK /var/www/html/src/Modules/Party/boom.php:42 password=hunter2';

        $repository = $this->createStub(PartyAccountRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn($account);
        $repository->method('findByPublicId')
            ->willThrowException(new \RuntimeException($secretMessage));

        static::getContainer()->set(PartyAccountRepositoryInterface::class, $repository);

        $client->request(
            'GET',
            '/api/v1/party-accounts/11111111-1111-4111-8111-111111111111',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
            ],
        );

        self::assertResponseStatusCodeSame(500);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        $raw = (string) $client->getResponse()->getContent();
        /** @var array{error: array{code: string, message: string}} $payload */
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'error' => [
                    'code' => 'internal_error',
                    'message' => 'An unexpected error occurred.',
                ],
            ],
            $payload,
        );
        self::assertCount(1, $payload);
        self::assertCount(2, $payload['error']);
        self::assertStringNotContainsString('SECRET_LEAK', $raw);
        self::assertStringNotContainsString('/var/www', $raw);
        self::assertStringNotContainsString('hunter2', $raw);
        self::assertStringNotContainsString('RuntimeException', $raw);
    }

    /**
     * @return array{token: string, email: string, publicId: string, displayName: string}
     */
    private function createAuthenticatedSession(KernelBrowser $client): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.get.'.$suffix.'@example.com';
        $displayName = 'Http Get '.$suffix;
        $password = 'Http-Get-Pass-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson($displayName, Email::fromString($email));
        $accounts->save($account);
        $unitOfWork->commit();

        $credentials->save(CoreCredential::createLocal(
            accountId: (int) $account->id(),
            passwordHash: $hasher->hash($password),
            isPrimary: true,
        ));
        $unitOfWork->commit();

        $client->request(
            'POST',
            '/api/v1/auth/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['email' => $email, 'password' => $password],
                JSON_THROW_ON_ERROR,
            ),
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{token: string} $loginBody */
        $loginBody = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'token' => $loginBody['token'],
            'email' => $email,
            'publicId' => $account->publicId()->toString(),
            'displayName' => $displayName,
        ];
    }
}

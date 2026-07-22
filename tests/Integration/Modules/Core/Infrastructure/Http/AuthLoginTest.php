<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Core\Infrastructure\Http;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class AuthLoginTest extends WebTestCase
{
    public function test_login_with_valid_credentials_returns_200_and_decodable_jwt(): void
    {
        $client = static::createClient();
        $fixture = $this->createAccountWithLocalPassword('Auth-Ok-Pass-'.$this->suffix());

        $responseBody = $this->postLogin($client, $fixture['email'], $fixture['password']);
        self::assertResponseStatusCodeSame(200);

        self::assertArrayHasKey('token', $responseBody);
        self::assertIsString($responseBody['token']);
        self::assertNotSame('', $responseBody['token']);

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $payload = $jwtManager->parse($responseBody['token']);

        self::assertSame($fixture['publicId'], $payload['public_id']);
        self::assertSame($fixture['email'], $payload['username']);
        self::assertArrayNotHasKey('account_id', $payload);
    }

    public function test_login_with_wrong_password_returns_generic_401(): void
    {
        $client = static::createClient();
        $fixture = $this->createAccountWithLocalPassword('Auth-Bad-Pass-'.$this->suffix());

        $wrongPasswordBody = $this->postLogin($client, $fixture['email'], 'definitely-not-the-password');
        self::assertResponseStatusCodeSame(401);
        self::assertSame($this->genericUnauthorizedPayload(), $wrongPasswordBody);
    }

    public function test_login_with_unknown_email_returns_identical_generic_401(): void
    {
        $client = static::createClient();
        $fixture = $this->createAccountWithLocalPassword('Auth-Enum-Pass-'.$this->suffix());

        $wrongPasswordBody = $this->postLogin($client, $fixture['email'], 'wrong-password');
        self::assertResponseStatusCodeSame(401);

        $unknownEmailBody = $this->postLogin(
            $client,
            'unknown.'.$this->suffix().'@example.com',
            'wrong-password',
        );
        self::assertResponseStatusCodeSame(401);

        self::assertSame($wrongPasswordBody, $unknownEmailBody);
        self::assertSame($this->genericUnauthorizedPayload(), $unknownEmailBody);
    }

    public function test_login_with_disabled_account_returns_identical_generic_401(): void
    {
        $client = static::createClient();
        $fixture = $this->createAccountWithLocalPassword('Auth-Disabled-Pass-'.$this->suffix());

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = static::getContainer()->get(PartyAccountRepositoryInterface::class);
        $account = $accounts->findByEmail(Email::fromString($fixture['email']));
        self::assertNotNull($account);
        $account->disable();
        $accounts->save($account);
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = static::getContainer()->get(UnitOfWork::class);
        $unitOfWork->commit();

        $wrongPasswordBody = $this->postLogin($client, $fixture['email'], 'wrong-password');
        self::assertResponseStatusCodeSame(401);

        $disabledBody = $this->postLogin($client, $fixture['email'], $fixture['password']);
        self::assertResponseStatusCodeSame(401);

        self::assertSame($wrongPasswordBody, $disabledBody);
        self::assertSame($this->genericUnauthorizedPayload(), $disabledBody);
    }

    public function test_protected_endpoint_requires_valid_jwt(): void
    {
        $client = static::createClient();
        $fixture = $this->createAccountWithLocalPassword('Auth-Protect-Pass-'.$this->suffix());

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$fixture['publicId'],
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        self::assertResponseStatusCodeSame(401);

        $loginBody = $this->postLogin($client, $fixture['email'], $fixture['password']);
        self::assertResponseStatusCodeSame(200);
        self::assertIsString($loginBody['token']);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$fixture['publicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$loginBody['token'],
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{publicId: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($fixture['publicId'], $payload['publicId']);
    }

    /**
     * @return array{email: string, password: string, publicId: string}
     */
    private function createAccountWithLocalPassword(string $password): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = $this->suffix();
        $email = 'auth.login.'.$suffix.'@example.com';

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson(
            'Auth Login '.$suffix,
            Email::fromString($email),
        );
        $accounts->save($account);
        $unitOfWork->commit();

        $credential = CoreCredential::createLocal(
            accountId: (int) $account->id(),
            passwordHash: $hasher->hash($password),
            isPrimary: true,
        );
        $credentials->save($credential);
        $unitOfWork->commit();

        return [
            'email' => $email,
            'password' => $password,
            'publicId' => $account->publicId()->toString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postLogin(KernelBrowser $client, string $email, string $password): array
    {
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

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $body;
    }

    /**
     * @return array{code: int, message: string}
     */
    private function genericUnauthorizedPayload(): array
    {
        return [
            'code' => 401,
            'message' => 'Invalid credentials.',
        ];
    }

    private function suffix(): string
    {
        return bin2hex(random_bytes(4));
    }
}

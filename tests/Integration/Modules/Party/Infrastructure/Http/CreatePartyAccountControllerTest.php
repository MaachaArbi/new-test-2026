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

final class CreatePartyAccountControllerTest extends WebTestCase
{
    public function test_create_person_returns_201_with_location_and_is_readable_via_get(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $email = 'create.person.'.$suffix.'@example.com';
        $displayName = 'Created Person '.$suffix;

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'person',
                'displayName' => $displayName,
                'email' => $email,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertMatchesRegularExpression(
            '#^/api/v1/party-accounts/[0-9a-fA-F-]{36}$#',
            $location,
        );

        /** @var array{publicId: string, nature: string, displayName: string, email: string|null} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertSame('person', $payload['nature']);
        self::assertSame($displayName, $payload['displayName']);
        self::assertSame($email, $payload['email']);
        self::assertSame('/api/v1/party-accounts/'.$payload['publicId'], $location);

        $client->request(
            'GET',
            $location,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{publicId: string, nature: string, displayName: string, email: string|null} $getPayload */
        $getPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($payload, $getPayload);
    }

    public function test_create_organization_returns_201_with_location(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $displayName = 'Created Org '.$suffix;

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'organization',
                'displayName' => $displayName,
                'email' => 'create.org.'.$suffix.'@example.com',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);

        /** @var array{publicId: string, nature: string, displayName: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertSame('organization', $payload['nature']);
        self::assertSame($displayName, $payload['displayName']);
        self::assertSame('/api/v1/party-accounts/'.$payload['publicId'], $location);
    }

    public function test_invalid_nature_returns_422_with_violations(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'alien',
                'displayName' => 'Nope',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->assertValidationFailedPayload($client, 'nature');
    }

    public function test_blank_display_name_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'person',
                'displayName' => '',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->assertValidationFailedPayload($client, 'displayName');
    }

    public function test_malformed_email_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'person',
                'displayName' => 'Bad Email',
                'email' => 'not-an-email',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->assertValidationFailedPayload($client, 'email');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emailsRejectedByDomainPattern')]
    public function test_email_rejected_by_domain_pattern_returns_422_not_400(string $email): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'person',
                'displayName' => 'Domain Pattern Email',
                'email' => $email,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->assertValidationFailedPayload($client, 'email');

        $raw = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('email.invalid_format', $raw);
        self::assertStringContainsString('validation_failed', $raw);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function emailsRejectedByDomainPattern(): array
    {
        return [
            ['a@b.c'],
            ['user@123.45.67.89'],
        ];
    }

    public function test_person_with_parent_account_id_returns_domain_400(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'nature' => 'person',
                'displayName' => 'Person With Parent',
                'parentAccountId' => 99,
            ], JSON_THROW_ON_ERROR),
        );

        // Règle Domain (pas DTO) : parentAccountId interdit pour person → DomainException.
        self::assertResponseStatusCodeSame(400);

        /** @var array{error: array{code: string, message: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('party_account.parent_account_not_allowed_for_person', $payload['error']['code']);
        self::assertSame('A person account cannot have a parent account.', $payload['error']['message']);
        self::assertSame(99, $payload['error']['context']['attempted_parent_id']);
    }

    public function test_create_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/party-accounts',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'nature' => 'person',
                'displayName' => 'No Auth',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'create.auth.'.$suffix.'@example.com';
        $password = 'Create-Auth-Pass-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Create Auth '.$suffix, Email::fromString($email));
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

        return $loginBody['token'];
    }

    private function assertValidationFailedPayload(KernelBrowser $client, string $expectedField): void
    {
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{error: array{code: string, message: string, violations: list<array{field: string, message: string}>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('validation_failed', $payload['error']['code']);
        self::assertSame('Validation failed.', $payload['error']['message']);
        self::assertNotEmpty($payload['error']['violations']);

        $fields = array_map(
            static fn (array $violation): string => $violation['field'],
            $payload['error']['violations'],
        );
        self::assertContains($expectedField, $fields);
    }
}

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

final class UpdateDeletePartyAccountControllerTest extends WebTestCase
{
    public function test_patch_display_name_only_returns_200_and_is_visible_via_get(): void
    {
        $client = static::createClient();
        $ctx = $this->createAuthAndTarget($client);
        $newName = 'Renamed '.$ctx['suffix'];

        $client->request(
            'PATCH',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: $this->jsonAuthHeaders($ctx['token']),
            content: json_encode(['displayName' => $newName], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertSame($newName, $payload['displayName']);
        self::assertSame($ctx['targetPublicId'], $payload['publicId']);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$ctx['token'],
            ],
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{displayName: string} $getPayload */
        $getPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($newName, $getPayload['displayName']);
    }

    public function test_patch_is_disabled_true_then_false(): void
    {
        $client = static::createClient();
        $ctx = $this->createAuthAndTarget($client);

        $client->request(
            'PATCH',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: $this->jsonAuthHeaders($ctx['token']),
            content: json_encode(['isDisabled' => true], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{isDisabled: bool} $disabledPayload */
        $disabledPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($disabledPayload['isDisabled']);
        self::assertArrayNotHasKey('id', $disabledPayload);

        $client->request(
            'PATCH',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: $this->jsonAuthHeaders($ctx['token']),
            content: json_encode(['isDisabled' => false], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{isDisabled: bool} $enabledPayload */
        $enabledPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($enabledPayload['isDisabled']);
    }

    public function test_patch_empty_body_returns_400(): void
    {
        $client = static::createClient();
        $ctx = $this->createAuthAndTarget($client);

        $client->request(
            'PATCH',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: $this->jsonAuthHeaders($ctx['token']),
            content: '{}',
        );

        self::assertResponseStatusCodeSame(400);
        /** @var array{error: array{code: string, message: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('party_account.no_changes_provided', $payload['error']['code']);
    }

    public function test_patch_unknown_public_id_returns_404(): void
    {
        $client = static::createClient();
        $ctx = $this->createAuthAndTarget($client);
        $missing = '00000000-0000-4000-8000-000000000088';

        $client->request(
            'PATCH',
            '/api/v1/party-accounts/'.$missing,
            server: $this->jsonAuthHeaders($ctx['token']),
            content: json_encode(['displayName' => 'Nope'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('party_account.not_found', $payload['error']['code']);
    }

    public function test_delete_then_get_returns_404_and_second_delete_is_idempotent(): void
    {
        $client = static::createClient();
        $ctx = $this->createAuthAndTarget($client);

        $client->request(
            'DELETE',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$ctx['token'],
            ],
        );
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $client->getResponse()->getContent());
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$ctx['token'],
            ],
        );
        self::assertResponseStatusCodeSame(404);

        $client->request(
            'DELETE',
            '/api/v1/party-accounts/'.$ctx['targetPublicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$ctx['token'],
            ],
        );
        self::assertResponseStatusCodeSame(200);
    }

    public function test_delete_unknown_public_id_returns_404(): void
    {
        $client = static::createClient();
        $ctx = $this->createAuthAndTarget($client);
        $missing = '00000000-0000-4000-8000-000000000077';

        $client->request(
            'DELETE',
            '/api/v1/party-accounts/'.$missing,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$ctx['token'],
            ],
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function test_patch_and_delete_without_jwt_return_401(): void
    {
        $client = static::createClient();
        $publicId = '11111111-1111-4111-8111-111111111111';

        $client->request(
            'PATCH',
            '/api/v1/party-accounts/'.$publicId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['displayName' => 'X'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(401);

        $client->request(
            'DELETE',
            '/api/v1/party-accounts/'.$publicId,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * Compte JWT distinct du compte cible (disable/delete ne cassent pas l'auth).
     *
     * @return array{token: string, targetPublicId: string, suffix: string}
     */
    private function createAuthAndTarget(KernelBrowser $client): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $authEmail = 'upd.auth.'.$suffix.'@example.com';
        $authPassword = 'Upd-Auth-Pass-'.$suffix;
        $authAccount = PartyAccount::createPerson('Upd Auth '.$suffix, Email::fromString($authEmail));
        $accounts->save($authAccount);
        $unitOfWork->commit();
        $credentials->save(CoreCredential::createLocal(
            accountId: (int) $authAccount->id(),
            passwordHash: $hasher->hash($authPassword),
            isPrimary: true,
        ));

        $target = PartyAccount::createPerson(
            'Upd Target '.$suffix,
            Email::fromString('upd.target.'.$suffix.'@example.com'),
        );
        $accounts->save($target);
        $unitOfWork->commit();

        $client->request(
            'POST',
            '/api/v1/auth/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['email' => $authEmail, 'password' => $authPassword],
                JSON_THROW_ON_ERROR,
            ),
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{token: string} $loginBody */
        $loginBody = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'token' => $loginBody['token'],
            'targetPublicId' => $target->publicId()->toString(),
            'suffix' => $suffix,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function jsonAuthHeaders(string $token): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];
    }
}

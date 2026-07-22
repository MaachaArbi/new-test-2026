<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure\Http;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Application\ListPagination;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class ListPartyAccountsControllerTest extends WebTestCase
{
    public function test_list_without_filters_returns_paginated_structure(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));

        $this->createPerson($client, 'List Alpha '.$suffix, 'list.alpha.'.$suffix.'@example.com');
        $this->createPerson($client, 'List Beta '.$suffix, 'list.beta.'.$suffix.'@example.com');
        $this->createOrganization($client, 'List Org '.$suffix, 'list.org.'.$suffix.'@example.com');

        $client->request(
            'GET',
            '/api/v1/party-accounts?limit=2&page=1&search='.rawurlencode($suffix),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{data: list<array<string, mixed>>, meta: array{page: int, limit: int, total: int, totalPages: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['meta']['page']);
        self::assertSame(2, $payload['meta']['limit']);
        self::assertSame(3, $payload['meta']['total']);
        self::assertSame(2, $payload['meta']['totalPages']);
        self::assertCount(2, $payload['data']);

        foreach ($payload['data'] as $item) {
            self::assertArrayNotHasKey('id', $item);
            self::assertArrayHasKey('publicId', $item);
            self::assertArrayHasKey('nature', $item);
            self::assertArrayHasKey('displayName', $item);
            self::assertArrayHasKey('email', $item);
            self::assertCount(4, $item);
        }

        $client->request(
            'GET',
            '/api/v1/party-accounts?limit=2&page=2&search='.rawurlencode($suffix),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<array<string, mixed>>, meta: array{total: int, totalPages: int}} $page2 */
        $page2 = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(3, $page2['meta']['total']);
        self::assertSame(2, $page2['meta']['totalPages']);
        self::assertCount(1, $page2['data']);
    }

    public function test_filter_by_nature_returns_only_matching_accounts(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));

        $this->createPerson($client, 'Nature Person '.$suffix, 'nature.person.'.$suffix.'@example.com');
        $this->createOrganization($client, 'Nature Org '.$suffix, 'nature.org.'.$suffix.'@example.com');

        $client->request(
            'GET',
            '/api/v1/party-accounts?nature=organization&search='.rawurlencode($suffix),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<array{nature: string, displayName: string}>, meta: array{total: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['meta']['total']);
        self::assertCount(1, $payload['data']);
        self::assertSame('organization', $payload['data'][0]['nature']);
        self::assertSame('Nature Org '.$suffix, $payload['data'][0]['displayName']);
    }

    public function test_search_matches_partial_display_name(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $needle = 'UniqSearch'.$suffix;

        $this->createPerson($client, 'Prefix '.$needle.' Suffix', 'search.'.$suffix.'@example.com');
        $this->createPerson($client, 'Other '.$suffix, 'other.'.$suffix.'@example.com');

        $client->request(
            'GET',
            '/api/v1/party-accounts?search='.rawurlencode($needle),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<array{displayName: string}>, meta: array{total: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['meta']['total']);
        self::assertStringContainsString($needle, $payload['data'][0]['displayName']);
    }

    public function test_limit_above_max_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'GET',
            '/api/v1/party-accounts?limit='.(ListPagination::MAX_LIMIT + 1),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        self::assertResponseStatusCodeSame(422);

        /** @var array{error: array{code: string, violations: list<array{field: string}>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
        $fields = array_column($payload['error']['violations'], 'field');
        self::assertContains('limit', $fields);
    }

    public function test_page_beyond_total_returns_empty_data(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $this->createPerson($client, 'Beyond '.$suffix, 'beyond.'.$suffix.'@example.com');

        $client->request(
            'GET',
            '/api/v1/party-accounts?page=999&limit=10&search='.rawurlencode('Beyond '.$suffix),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<mixed>, meta: array{page: int, total: int, totalPages: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(999, $payload['meta']['page']);
        self::assertSame(1, $payload['meta']['total']);
        self::assertSame(1, $payload['meta']['totalPages']);
        self::assertSame([], $payload['data']);
    }

    public function test_list_without_jwt_returns_401(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/v1/party-accounts',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'list.auth.'.$suffix.'@example.com';
        $password = 'List-Auth-Pass-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('List Auth '.$suffix, Email::fromString($email));
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

    private function createPerson(KernelBrowser $client, string $displayName, string $email): void
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        $accounts->save(PartyAccount::createPerson($displayName, Email::fromString($email)));
        $unitOfWork->commit();
    }

    private function createOrganization(KernelBrowser $client, string $displayName, string $email): void
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        $accounts->save(PartyAccount::createOrganization($displayName, Email::fromString($email)));
        $unitOfWork->commit();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\CreateBookingCancellationPolicy\CreateBookingCancellationPolicyCommand;
use App\Modules\Booking\Application\CreateBookingCancellationPolicy\CreateBookingCancellationPolicyHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingCancellationPolicyRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Couvre le plus de cas d'erreur distincts (policy/tier HTTP).
 */
final class AddBookingCancellationTierControllerTest extends WebTestCase
{
    public function test_add_tier_returns_201_without_tier_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBookingWithPolicy('TierOk');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/cancellation-policy/'.$ctx['policyId'].'/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'daysBeforeStart' => 15,
                'penaltyType' => 'percentage',
                'penaltyValue' => '30.000',
                'sortOrder' => 1,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertSame($ctx['policyId'], $payload['policyId']);
        self::assertSame(15, $payload['daysBeforeStart']);
        self::assertSame('percentage', $payload['penaltyType']);
        self::assertSame('30.000', $payload['penaltyValue']);
        self::assertSame(1, $payload['sortOrder']);
    }

    public function test_add_tier_on_missing_booking_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000009/cancellation-policy/1/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'daysBeforeStart' => 7,
                'penaltyType' => 'free',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.not_found', $payload['error']['code']);
    }

    public function test_add_tier_on_missing_policy_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBookingWithPolicy('TierMissPol');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/cancellation-policy/999999999/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'daysBeforeStart' => 7,
                'penaltyType' => 'free',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_cancellation_policy.not_found', $payload['error']['code']);
    }

    public function test_add_tier_policy_belongs_to_other_booking_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctxA = $this->seedBookingWithPolicy('TierOwnA');
        $ctxB = $this->seedBookingWithPolicy('TierOwnB');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctxA['publicId'].'/cancellation-policy/'.$ctxB['policyId'].'/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'daysBeforeStart' => 7,
                'penaltyType' => 'free',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_cancellation_policy.not_found', $payload['error']['code']);
    }

    public function test_invalid_penalty_type_returns_422_not_500(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBookingWithPolicy('TierBadType');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/cancellation-policy/'.$ctx['policyId'].'/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'daysBeforeStart' => 7,
                'penaltyType' => 'not_a_real_penalty',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
    }

    public function test_invalid_penalty_value_for_free_returns_422_domain(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBookingWithPolicy('TierFreeVal');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/cancellation-policy/'.$ctx['policyId'].'/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'daysBeforeStart' => 30,
                'penaltyType' => 'free',
                'penaltyValue' => '10.000',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_cancellation_tier.invalid_penalty', $payload['error']['code']);
    }

    public function test_missing_days_before_start_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBookingWithPolicy('Tier422');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/cancellation-policy/'.$ctx['policyId'].'/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'penaltyType' => 'free',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_add_tier_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000008/cancellation-policy/1/tiers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'daysBeforeStart' => 7,
                'penaltyType' => 'free',
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
        $email = 'http.bk.tier.'.$suffix.'@example.com';
        $password = 'Http-Bk-Tier-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Bk Tier '.$suffix, Email::fromString($email));
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
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{token: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $body['token'];
    }

    /**
     * @return array{publicId: string, policyId: int}
     */
    private function seedBookingWithPolicy(string $label): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var BookingFolderRepositoryInterface $folders */
        $folders = $container->get(BookingFolderRepositoryInterface::class);
        /** @var BookingRepositoryInterface $bookings */
        $bookings = $container->get(BookingRepositoryInterface::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        /** @var BookingCancellationPolicyRepositoryInterface $policies */
        $policies = $container->get(BookingCancellationPolicyRepositoryInterface::class);
        /** @var BookingHotelRoomRepositoryInterface $rooms */
        $rooms = $container->get(BookingHotelRoomRepositoryInterface::class);

        $customer = PartyAccount::createOrganization(
            'Tier Cust '.$label.' '.$suffix,
            Email::fromString('tier.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'Tier Off '.$label.' '.$suffix,
            Email::fromString('tier.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create('TIER-'.$suffix, (int) $customer->id(), (int) $office->id());
        $folders->save($folder);
        $unitOfWork->commit();

        $booking = (new CreateBookingHandler($bookings, new BookingReferentialValidator($connection), $unitOfWork))(
            new CreateBookingCommand(
                folderId: (int) $folder->id(),
                serviceTypeCode: 'hotel',
                statusCode: 'draft',
                customerAccountId: (int) $customer->id(),
                supplierAccountId: null,
                officeAccountId: (int) $office->id(),
                startDate: '2026-09-01',
                endDate: '2026-09-03',
                achatCurrencyCode: 'TND',
                venteCurrencyCode: 'TND',
                achatExchangeRate: '1',
                venteExchangeRate: '1',
                totalAchatAmount: 10_000,
                totalVenteAmount: 12_000,
                margeAgenceAmount: 1_500,
                margeDistributeurAmount: 500,
                paidAmount: 0,
            ),
        );

        $policy = (new CreateBookingCancellationPolicyHandler($policies, $rooms, $unitOfWork))(
            new CreateBookingCancellationPolicyCommand((int) $booking->id()),
        );

        return [
            'publicId' => $booking->publicId()->toString(),
            'policyId' => (int) $policy->id(),
        ];
    }
}

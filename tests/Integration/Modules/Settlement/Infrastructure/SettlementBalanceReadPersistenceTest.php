<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Settlement\Infrastructure;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentCommand;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentHandler;
use App\Modules\Settlement\Application\PostSettlementCreditFromInstrument\PostSettlementCreditFromInstrumentCommand;
use App\Modules\Settlement\Application\PostSettlementCreditFromInstrument\PostSettlementCreditFromInstrumentHandler;
use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Repository\SettlementBalanceRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementPaymentMethodRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Settlement\Infrastructure\Persistence\DoctrineSettlementBalanceRepository;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — lecture settlement_balance + cohérence trigger ↔ SUM à froid.
 */
final class SettlementBalanceReadPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private SettlementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private SettlementEntryTypeRepositoryInterface $entryTypeRepository;

    private SettlementInstrumentRepositoryInterface $instrumentRepository;

    private SettlementLedgerEntryRepositoryInterface $ledgerRepository;

    private SettlementBalanceRepositoryInterface $balanceRepository;

    private CreateSettlementInstrumentHandler $createInstrumentHandler;

    private PostSettlementCreditFromInstrumentHandler $postCreditHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var SettlementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(SettlementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var SettlementEntryTypeRepositoryInterface $entryTypeRepository */
        $entryTypeRepository = $container->get(SettlementEntryTypeRepositoryInterface::class);
        $this->entryTypeRepository = $entryTypeRepository;

        /** @var SettlementInstrumentRepositoryInterface $instrumentRepository */
        $instrumentRepository = $container->get(SettlementInstrumentRepositoryInterface::class);
        $this->instrumentRepository = $instrumentRepository;

        /** @var SettlementLedgerEntryRepositoryInterface $ledgerRepository */
        $ledgerRepository = $container->get(SettlementLedgerEntryRepositoryInterface::class);
        $this->ledgerRepository = $ledgerRepository;

        /** @var SettlementBalanceRepositoryInterface $balanceRepository */
        $balanceRepository = $container->get(SettlementBalanceRepositoryInterface::class);
        $this->balanceRepository = $balanceRepository;

        $this->createInstrumentHandler = new CreateSettlementInstrumentHandler(
            $this->instrumentRepository,
            new SettlementReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->postCreditHandler = new PostSettlementCreditFromInstrumentHandler(
            $this->instrumentRepository,
            $this->entryTypeRepository,
            $this->ledgerRepository,
            $this->unitOfWork,
        );
    }

    public function test_end_to_end_balance_matches_ledger_sum_after_obligation_and_credits(): void
    {
        $partyId = $this->createOrg('BalRead');
        $role = InstrumentPartyRole::Customer->value;
        $currency = 'TND';

        $obligationType = $this->entryTypeRepository->findByCode('customer_obligation');
        self::assertNotNull($obligationType);

        $obligation = SettlementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Customer,
            currencyCode: $currency,
            entryTypeId: (int) $obligationType->id(),
            amountMinor: 100_000,
            effectiveDate: new DateTimeImmutable('today'),
            bookingId: 1,
        );
        $this->ledgerRepository->append($obligation);
        $this->unitOfWork->commit();

        $this->postCreditForParty($partyId, 60_000);

        $afterFirstCredit = $this->balanceRepository->findBalance($partyId, $role, $currency);
        self::assertNotNull($afterFirstCredit);
        self::assertSame(40_000, $afterFirstCredit['balanceMinor']);
        self::assertSame(2, $afterFirstCredit['entryCount']);

        $this->postCreditForParty($partyId, 40_000);

        $final = $this->balanceRepository->findBalance($partyId, $role, $currency);
        self::assertNotNull($final);
        self::assertSame(0, $final['balanceMinor']);
        self::assertSame(3, $final['entryCount']);
        self::assertNotNull($final['lastEntryId']);
        self::assertInstanceOf(DateTimeImmutable::class, $final['updatedAt']);

        $coldSumRaw = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(amount_minor), 0)
             FROM settlement_ledger_entry
             WHERE party_account_id = :party_account_id
               AND party_role = :party_role
               AND currency_code = :currency_code',
            [
                'party_account_id' => $partyId,
                'party_role' => $role,
                'currency_code' => $currency,
            ],
        );
        self::assertTrue(is_int($coldSumRaw) || is_numeric($coldSumRaw));
        $coldSum = is_int($coldSumRaw) ? $coldSumRaw : (int) $coldSumRaw;
        self::assertSame(0, $coldSum);
        self::assertSame($coldSum, $final['balanceMinor']);

        $books = $this->balanceRepository->findAllBalancesForParty($partyId);
        self::assertCount(1, $books);
        self::assertSame($role, $books[0]['partyRole']);
        self::assertSame($currency, $books[0]['currencyCode']);
        self::assertSame(0, $books[0]['balanceMinor']);
        self::assertSame(3, $books[0]['entryCount']);
    }

    public function test_balance_repository_never_writes_settlement_balance(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(DoctrineSettlementBalanceRepository::class))->getFileName() ?: '',
        );
        self::assertStringNotContainsString('INSERT INTO settlement_balance', $source);
        self::assertStringNotContainsString('UPDATE settlement_balance', $source);
        self::assertStringNotContainsString('DELETE FROM settlement_balance', $source);
        self::assertStringNotContainsString('INSERT INTO', $source);
        self::assertStringNotContainsString('UPDATE ', $source);
        self::assertStringNotContainsString('DELETE FROM', $source);
    }

    private function postCreditForParty(int $partyId, int $amountMinor): void
    {
        $method = $this->paymentMethodRepository->findByCode('CB');
        self::assertNotNull($method);

        $instrument = ($this->createInstrumentHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: $partyId,
            partyRole: 'customer',
            currencyCode: 'TND',
            paymentMethodId: (int) $method->id(),
            amountMinor: $amountMinor,
        ));

        ($this->postCreditHandler)(new PostSettlementCreditFromInstrumentCommand((int) $instrument->id()));
    }

    private function createOrg(string $label): int
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString('bal.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        return (int) $account->id();
    }
}

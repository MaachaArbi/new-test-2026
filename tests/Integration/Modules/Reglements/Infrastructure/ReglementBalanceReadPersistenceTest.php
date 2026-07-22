<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Reglements\Infrastructure;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Reglements\Application\CreateReglementInstrument\CreateReglementInstrumentCommand;
use App\Modules\Reglements\Application\CreateReglementInstrument\CreateReglementInstrumentHandler;
use App\Modules\Reglements\Application\PostReglementCreditFromInstrument\PostReglementCreditFromInstrumentCommand;
use App\Modules\Reglements\Application\PostReglementCreditFromInstrument\PostReglementCreditFromInstrumentHandler;
use App\Modules\Reglements\Application\ReglementReferentialValidator;
use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Repository\ReglementBalanceRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementEntryTypeRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementPaymentMethodRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Reglements\Infrastructure\Persistence\DoctrineReglementBalanceRepository;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — lecture reglement_balance + cohérence trigger ↔ SUM à froid.
 */
final class ReglementBalanceReadPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private ReglementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private ReglementEntryTypeRepositoryInterface $entryTypeRepository;

    private ReglementInstrumentRepositoryInterface $instrumentRepository;

    private ReglementLedgerEntryRepositoryInterface $ledgerRepository;

    private ReglementBalanceRepositoryInterface $balanceRepository;

    private CreateReglementInstrumentHandler $createInstrumentHandler;

    private PostReglementCreditFromInstrumentHandler $postCreditHandler;

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

        /** @var ReglementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(ReglementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var ReglementEntryTypeRepositoryInterface $entryTypeRepository */
        $entryTypeRepository = $container->get(ReglementEntryTypeRepositoryInterface::class);
        $this->entryTypeRepository = $entryTypeRepository;

        /** @var ReglementInstrumentRepositoryInterface $instrumentRepository */
        $instrumentRepository = $container->get(ReglementInstrumentRepositoryInterface::class);
        $this->instrumentRepository = $instrumentRepository;

        /** @var ReglementLedgerEntryRepositoryInterface $ledgerRepository */
        $ledgerRepository = $container->get(ReglementLedgerEntryRepositoryInterface::class);
        $this->ledgerRepository = $ledgerRepository;

        /** @var ReglementBalanceRepositoryInterface $balanceRepository */
        $balanceRepository = $container->get(ReglementBalanceRepositoryInterface::class);
        $this->balanceRepository = $balanceRepository;

        $this->createInstrumentHandler = new CreateReglementInstrumentHandler(
            $this->instrumentRepository,
            new ReglementReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->postCreditHandler = new PostReglementCreditFromInstrumentHandler(
            $this->instrumentRepository,
            $this->entryTypeRepository,
            $this->ledgerRepository,
            $this->unitOfWork,
        );
    }

    public function test_end_to_end_balance_matches_ledger_sum_after_obligation_and_credits(): void
    {
        $partyId = $this->createOrg('BalRead');
        $role = InstrumentPartyRole::Client->value;
        $currency = 'TND';

        $obligationType = $this->entryTypeRepository->findByCode('obligation_vente');
        self::assertNotNull($obligationType);

        $obligation = ReglementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Client,
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
             FROM reglement_ledger_entry
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

    public function test_balance_repository_never_writes_reglement_balance(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(DoctrineReglementBalanceRepository::class))->getFileName() ?: '',
        );
        self::assertStringNotContainsString('INSERT INTO reglement_balance', $source);
        self::assertStringNotContainsString('UPDATE reglement_balance', $source);
        self::assertStringNotContainsString('DELETE FROM reglement_balance', $source);
        self::assertStringNotContainsString('INSERT INTO', $source);
        self::assertStringNotContainsString('UPDATE ', $source);
        self::assertStringNotContainsString('DELETE FROM', $source);
    }

    private function postCreditForParty(int $partyId, int $amountMinor): void
    {
        $method = $this->paymentMethodRepository->findByCode('CB');
        self::assertNotNull($method);

        $instrument = ($this->createInstrumentHandler)(new CreateReglementInstrumentCommand(
            partyAccountId: $partyId,
            partyRole: 'client',
            currencyCode: 'TND',
            paymentMethodId: (int) $method->id(),
            amountMinor: $amountMinor,
        ));

        ($this->postCreditHandler)(new PostReglementCreditFromInstrumentCommand((int) $instrument->id()));
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

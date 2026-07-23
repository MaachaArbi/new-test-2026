<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\CashManagement\Infrastructure;

use App\Modules\CashManagement\Application\CashSessionPartyAccountValidator;
use App\Modules\CashManagement\Application\CloseCashSession\CloseCashSessionCommand;
use App\Modules\CashManagement\Application\CloseCashSession\CloseCashSessionHandler;
use App\Modules\CashManagement\Application\OpenCashSession\OpenCashSessionCommand;
use App\Modules\CashManagement\Application\OpenCashSession\OpenCashSessionHandler;
use App\Modules\CashManagement\Domain\Exception\CashSessionAlreadyOpenException;
use App\Modules\CashManagement\Domain\Exception\CashSessionNotFoundOrAlreadyClosedException;
use App\Modules\CashManagement\Domain\Exception\CashSessionReferencedAccountNotFoundException;
use App\Modules\CashManagement\Domain\Repository\CashSessionRepositoryInterface;
use App\Modules\CashManagement\Domain\ValueObject\CashSessionStatus;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — pivot cash_session (open/close via fonctions SQL).
 */
final class CashSessionPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private CashSessionRepositoryInterface $sessionRepository;

    private OpenCashSessionHandler $openHandler;

    private CloseCashSessionHandler $closeHandler;

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

        /** @var CashSessionRepositoryInterface $sessionRepository */
        $sessionRepository = $container->get(CashSessionRepositoryInterface::class);
        $this->sessionRepository = $sessionRepository;

        $validator = new CashSessionPartyAccountValidator($this->connection);
        $this->openHandler = new OpenCashSessionHandler($this->connection, $validator);
        $this->closeHandler = new CloseCashSessionHandler($this->connection, $validator);
    }

    public function test_open_session_persists_and_find_by_id_hydrates(): void
    {
        $accounts = $this->seedAccounts('Open');

        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
            officeAccountId: $accounts['officeId'],
            openedBy: $accounts['openerId'],
        ));

        $session = $this->sessionRepository->findById($sessionId);
        self::assertNotNull($session);
        self::assertSame($sessionId, $session->id());
        self::assertSame($accounts['holderId'], $session->holderAccountId());
        self::assertSame($accounts['officeId'], $session->officeAccountId());
        self::assertSame(CashSessionStatus::Open, $session->statusCode());
        self::assertSame($accounts['openerId'], $session->openedBy());
        self::assertNull($session->closedAt());
        self::assertNull($session->closedBy());
        self::assertNotSame('', $session->publicId()->toString());
    }

    public function test_open_rejects_missing_holder_account(): void
    {
        try {
            ($this->openHandler)(new OpenCashSessionCommand(
                holderAccountId: 999_999_977,
            ));
            self::fail('Expected CashSessionReferencedAccountNotFoundException');
        } catch (CashSessionReferencedAccountNotFoundException $exception) {
            self::assertSame('cash_session.holder_account_not_found', $exception->errorCode());
        }
    }

    public function test_open_rejects_missing_office_account(): void
    {
        $accounts = $this->seedAccounts('MissOffice');

        try {
            ($this->openHandler)(new OpenCashSessionCommand(
                holderAccountId: $accounts['holderId'],
                officeAccountId: 999_999_973,
            ));
            self::fail('Expected CashSessionReferencedAccountNotFoundException');
        } catch (CashSessionReferencedAccountNotFoundException $exception) {
            self::assertSame('cash_session.office_account_not_found', $exception->errorCode());
        }
    }

    public function test_open_rejects_missing_opened_by_account(): void
    {
        $accounts = $this->seedAccounts('MissOpener');

        try {
            ($this->openHandler)(new OpenCashSessionCommand(
                holderAccountId: $accounts['holderId'],
                openedBy: 999_999_972,
            ));
            self::fail('Expected CashSessionReferencedAccountNotFoundException');
        } catch (CashSessionReferencedAccountNotFoundException $exception) {
            self::assertSame('cash_session.opened_by_not_found', $exception->errorCode());
        }
    }

    public function test_open_rejects_second_open_for_same_holder(): void
    {
        $accounts = $this->seedAccounts('DblOpen');

        ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
            officeAccountId: $accounts['officeId'],
            openedBy: $accounts['openerId'],
        ));

        try {
            ($this->openHandler)(new OpenCashSessionCommand(
                holderAccountId: $accounts['holderId'],
            ));
            self::fail('Expected CashSessionAlreadyOpenException');
        } catch (CashSessionAlreadyOpenException $exception) {
            self::assertSame('cash_session.already_open', $exception->errorCode());
            self::assertSame($accounts['holderId'], $exception->context()['holder_account_id']);
        }
    }

    public function test_close_session_then_reopen_allowed(): void
    {
        $accounts = $this->seedAccounts('CloseReopen');

        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
            officeAccountId: $accounts['officeId'],
            openedBy: $accounts['openerId'],
        ));

        ($this->closeHandler)(new CloseCashSessionCommand(
            sessionId: $sessionId,
            closedBy: $accounts['closerId'],
        ));

        $closed = $this->sessionRepository->findById($sessionId);
        self::assertNotNull($closed);
        self::assertSame(CashSessionStatus::Closed, $closed->statusCode());
        self::assertNotNull($closed->closedAt());
        self::assertSame($accounts['closerId'], $closed->closedBy());

        $reopenedId = ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
            officeAccountId: $accounts['officeId'],
            openedBy: $accounts['openerId'],
        ));

        self::assertNotSame($sessionId, $reopenedId);
        $reopened = $this->sessionRepository->findById($reopenedId);
        self::assertNotNull($reopened);
        self::assertSame(CashSessionStatus::Open, $reopened->statusCode());
    }

    public function test_close_missing_or_already_closed_raises_domain_exception(): void
    {
        try {
            ($this->closeHandler)(new CloseCashSessionCommand(sessionId: 999_999_976));
            self::fail('Expected CashSessionNotFoundOrAlreadyClosedException');
        } catch (CashSessionNotFoundOrAlreadyClosedException $exception) {
            self::assertSame('cash_session.not_found_or_already_closed', $exception->errorCode());
        }

        $accounts = $this->seedAccounts('CloseTwice');
        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
        ));
        ($this->closeHandler)(new CloseCashSessionCommand(sessionId: $sessionId));

        try {
            ($this->closeHandler)(new CloseCashSessionCommand(sessionId: $sessionId));
            self::fail('Expected CashSessionNotFoundOrAlreadyClosedException');
        } catch (CashSessionNotFoundOrAlreadyClosedException $exception) {
            self::assertSame('cash_session.not_found_or_already_closed', $exception->errorCode());
            self::assertSame($sessionId, $exception->context()['session_id']);
        }
    }

    public function test_close_rejects_missing_closed_by_account(): void
    {
        $accounts = $this->seedAccounts('CloseByMiss');
        $sessionId = ($this->openHandler)(new OpenCashSessionCommand(
            holderAccountId: $accounts['holderId'],
        ));

        try {
            ($this->closeHandler)(new CloseCashSessionCommand(
                sessionId: $sessionId,
                closedBy: 999_999_975,
            ));
            self::fail('Expected CashSessionReferencedAccountNotFoundException');
        } catch (CashSessionReferencedAccountNotFoundException $exception) {
            self::assertSame('cash_session.closed_by_not_found', $exception->errorCode());
        }
    }

    public function test_find_by_id_returns_null_when_missing(): void
    {
        self::assertNull($this->sessionRepository->findById(999_999_974));
    }

    /**
     * @return array{holderId: int, officeId: int, openerId: int, closerId: int}
     */
    private function seedAccounts(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $holder = PartyAccount::createOrganization(
            $label.' Holder '.$suffix,
            Email::fromString('cash.holder.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Office '.$suffix,
            Email::fromString('cash.office.'.$suffix.'@example.com'),
        );
        $opener = PartyAccount::createPerson(
            $label.' Opener '.$suffix,
            Email::fromString('cash.opener.'.$suffix.'@example.com'),
        );
        $closer = PartyAccount::createPerson(
            $label.' Closer '.$suffix,
            Email::fromString('cash.closer.'.$suffix.'@example.com'),
        );

        $this->accountRepository->save($holder);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();
        $this->accountRepository->save($opener);
        $this->unitOfWork->commit();
        $this->accountRepository->save($closer);
        $this->unitOfWork->commit();

        return [
            'holderId' => (int) $holder->id(),
            'officeId' => (int) $office->id(),
            'openerId' => (int) $opener->id(),
            'closerId' => (int) $closer->id(),
        ];
    }
}

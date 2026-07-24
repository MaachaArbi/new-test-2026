<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Command;

use App\Modules\Party\Application\SetPartyAccountOffice\SetPartyAccountOfficeCommand;
use App\Modules\Party\Application\SetPartyAccountOffice\SetPartyAccountOfficeHandler;
use App\Modules\Party\Application\SetPartyAccountOrganizationIdentity\SetPartyAccountOrganizationIdentityCommand;
use App\Modules\Party\Application\SetPartyAccountOrganizationIdentity\SetPartyAccountOrganizationIdentityHandler;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountOfficeRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountOrganizationIdentityRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bootstrap idempotent du compte organisation agence + identity + office.
 * phone_primary ignoré : colonne non mappée (scope Domain volontaire).
 */
#[AsCommand(
    name: 'app:party:bootstrap-agency',
    description: 'Crée le compte organisation agence myGO (identity + office) s\'il manque',
)]
final class BootstrapAgencyAccountCommand extends Command
{
    private const DISPLAY_NAME = 'myGO';

    private const EMAIL = 'booking@mygo.pro';

    private const TAX_ID = '14455455AM000';

    private const WEBSITE = 'https://www.mygo.co';

    private const OFFICE_CODE = 'MYGO-2023';

    private const DEFAULT_CURRENCY_CODE = 'TND';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly PartyAccountOrganizationIdentityRepositoryInterface $identityRepository,
        private readonly PartyAccountOfficeRepositoryInterface $officeRepository,
        private readonly SetPartyAccountOrganizationIdentityHandler $setIdentityHandler,
        private readonly SetPartyAccountOfficeHandler $setOfficeHandler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $account = $this->findAgencyAccount();

        if ($account instanceof PartyAccount) {
            $io->warning(sprintf(
                'Compte agence déjà présent (id=%s, public_id=%s) — aucune création.',
                (string) $account->id(),
                $account->publicId()->toString(),
            ));
        } else {
            $account = PartyAccount::createOrganization(
                self::DISPLAY_NAME,
                Email::fromString(self::EMAIL),
            );

            $this->partyAccountRepository->save($account);
            $this->unitOfWork->commit();

            $io->success(sprintf(
                'Compte agence créé : id=%s, public_id=%s, email=%s, nature=%s',
                (string) $account->id(),
                $account->publicId()->toString(),
                self::EMAIL,
                $account->nature()->value,
            ));
        }

        $accountId = (int) $account->id();

        $this->ensureOrganizationIdentity($io, $accountId);
        $this->ensureOffice($io, $accountId);

        return Command::SUCCESS;
    }

    private function findAgencyAccount(): ?PartyAccount
    {
        $existing = $this->unitOfWork->createQueryBuilder()
            ->select('p')
            ->from(PartyAccount::class, 'p')
            ->where('p.displayName = :displayName')
            ->setParameter('displayName', self::DISPLAY_NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $existing instanceof PartyAccount ? $existing : null;
    }

    private function ensureOrganizationIdentity(SymfonyStyle $io, int $accountId): void
    {
        if ($this->identityRepository->existsByAccountId($accountId)) {
            $io->note(sprintf(
                'organization_identity déjà présente pour account_id=%d — skip.',
                $accountId,
            ));

            return;
        }

        ($this->setIdentityHandler)(new SetPartyAccountOrganizationIdentityCommand(
            accountId: $accountId,
            taxId: self::TAX_ID,
            tradeRegister: null,
            legalFormCode: null,
            website: self::WEBSITE,
        ));

        $io->success(sprintf(
            'organization_identity créée (tax_id=%s, website=%s).',
            self::TAX_ID,
            self::WEBSITE,
        ));
    }

    private function ensureOffice(SymfonyStyle $io, int $accountId): void
    {
        if ($this->officeRepository->existsByAccountId($accountId)) {
            $io->note(sprintf(
                'office déjà présent pour account_id=%d — skip.',
                $accountId,
            ));

            return;
        }

        ($this->setOfficeHandler)(new SetPartyAccountOfficeCommand(
            accountId: $accountId,
            officeCode: self::OFFICE_CODE,
            defaultCurrencyCode: self::DEFAULT_CURRENCY_CODE,
        ));

        $io->success(sprintf(
            'office créé (office_code=%s, default_currency_code=%s).',
            self::OFFICE_CODE,
            self::DEFAULT_CURRENCY_CODE,
        ));
    }
}

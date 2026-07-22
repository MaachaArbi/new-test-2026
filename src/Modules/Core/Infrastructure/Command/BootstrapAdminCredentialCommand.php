<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Command;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bootstrap idempotent d'un CoreCredential local pour le compte agence myGO.
 * Mot de passe fourni en argument — jamais en dur dans le code.
 */
#[AsCommand(
    name: 'app:core:bootstrap-admin-credential',
    description: 'Crée un CoreCredential local pour le compte agence (idempotent)',
)]
final class BootstrapAdminCredentialCommand extends Command
{
    private const AGENCY_EMAIL = 'booking@mygo.pro';

    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly CoreCredentialRepositoryInterface $coreCredentialRepository,
        private readonly PasswordHasherInterface $passwordHasher,
        private readonly UnitOfWork $unitOfWork,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'password',
            InputArgument::REQUIRED,
            'Mot de passe en clair du credential local (hashé avant persistance)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $plainPassword = $input->getArgument('password');
        if (!is_string($plainPassword) || $plainPassword === '') {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        $account = $this->partyAccountRepository->findByEmail(
            Email::fromString(self::AGENCY_EMAIL),
        );

        if (!$account instanceof PartyAccount || $account->id() === null) {
            $io->error(sprintf(
                'Compte agence introuvable (email=%s). Exécutez d\'abord app:party:bootstrap-agency.',
                self::AGENCY_EMAIL,
            ));

            return Command::FAILURE;
        }

        $accountId = (int) $account->id();

        foreach ($this->coreCredentialRepository->findActiveByAccountId($accountId) as $existing) {
            if (CredentialProvider::Local === $existing->provider()) {
                $io->warning(sprintf(
                    'Credential local déjà présent pour account_id=%d (credential_id=%s) — skip.',
                    $accountId,
                    (string) $existing->id(),
                ));

                return Command::SUCCESS;
            }
        }

        $credential = CoreCredential::createLocal(
            accountId: $accountId,
            passwordHash: $this->passwordHasher->hash($plainPassword),
            isPrimary: true,
        );
        $this->coreCredentialRepository->save($credential);
        $this->unitOfWork->commit();

        $io->success(sprintf(
            'Credential local créé : credential_id=%s, account_id=%d, email=%s',
            (string) $credential->id(),
            $accountId,
            self::AGENCY_EMAIL,
        ));

        return Command::SUCCESS;
    }
}

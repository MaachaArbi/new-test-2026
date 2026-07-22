<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure;

use App\Modules\Party\Application\CreatePartyAccountGroup\CreatePartyAccountGroupCommand;
use App\Modules\Party\Application\CreatePartyAccountGroup\CreatePartyAccountGroupHandler;
use App\Modules\Party\Domain\Exception\PartyAccountGroupNameAlreadyUsedException;
use App\Modules\Party\Domain\Repository\PartyAccountGroupRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — jamais SQLite.
 */
final class PartyAccountGroupPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountGroupRepositoryInterface $groupRepository;

    private CreatePartyAccountGroupHandler $createHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var PartyAccountGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(PartyAccountGroupRepositoryInterface::class);
        $this->groupRepository = $groupRepository;

        $this->createHandler = new CreatePartyAccountGroupHandler($this->groupRepository, $this->unitOfWork);

        $this->em->getConnection()->executeStatement(
            "INSERT INTO party_account_group_type (code, sort_order) VALUES ('zone', 1) ON CONFLICT (code) DO NOTHING",
        );
        // Raccourci de test : 'zone' n'est pas semé par le schéma (seul
        // 'commercial' l'est). Hors périmètre Application — nécessaire pour
        // tester l'unicité (group_type_code, name) cross-type.
    }

    public function test_create_round_trip_persists_mapped_fields(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $group = ($this->createHandler)(new CreatePartyAccountGroupCommand(
            groupTypeCode: 'commercial',
            name: 'RoundTrip '.$suffix,
        ));

        $id = $group->id();
        self::assertNotNull($id);
        $publicId = $group->publicId()->toString();

        $this->em->clear();

        $reloaded = $this->groupRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($publicId, $reloaded->publicId()->toString());
        self::assertSame('commercial', $reloaded->groupTypeCode()->toString());
        self::assertSame('RoundTrip '.$suffix, $reloaded->name());
    }

    public function test_rename_is_persisted(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $group = ($this->createHandler)(new CreatePartyAccountGroupCommand(
            'commercial',
            'Rename Before '.$suffix,
        ));
        $id = (int) $group->id();

        $group->rename('Rename After '.$suffix);
        $this->groupRepository->save($group);
        $this->unitOfWork->commit();

        $this->em->clear();

        $reloaded = $this->groupRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame('Rename After '.$suffix, $reloaded->name());
    }

    public function test_handler_rejects_duplicate_name_in_same_type_before_sql(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $name = 'Dup Name '.$suffix;

        ($this->createHandler)(new CreatePartyAccountGroupCommand('commercial', $name));
        self::assertTrue(
            $this->groupRepository->existsByTypeAndName(
                PartyAccountGroupTypeCode::fromString('commercial'),
                $name,
            ),
        );

        try {
            ($this->createHandler)(new CreatePartyAccountGroupCommand('commercial', $name));
            self::fail('Expected PartyAccountGroupNameAlreadyUsedException');
        } catch (PartyAccountGroupNameAlreadyUsedException $exception) {
            self::assertSame('party_account_group.name_already_used', $exception->errorCode());
            self::assertSame('commercial', $exception->context()['group_type_code']);
            self::assertSame($name, $exception->context()['name']);
        }

        self::assertSame(1, $this->countGroupsByTypeAndName('commercial', $name));
    }

    public function test_same_name_allowed_for_different_types(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $name = 'Shared Label '.$suffix;

        $commercial = ($this->createHandler)(new CreatePartyAccountGroupCommand('commercial', $name));
        $zone = ($this->createHandler)(new CreatePartyAccountGroupCommand('zone', $name));

        self::assertNotSame($commercial->id(), $zone->id());
        self::assertSame(1, $this->countGroupsByTypeAndName('commercial', $name));
        self::assertSame(1, $this->countGroupsByTypeAndName('zone', $name));
        self::assertTrue(
            $this->groupRepository->existsByTypeAndName(
                PartyAccountGroupTypeCode::fromString('zone'),
                $name,
            ),
        );
    }

    private function countGroupsByTypeAndName(string $type, string $name): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(g.id)')
            ->from(\App\Modules\Party\Domain\Entity\PartyAccountGroup::class, 'g')
            ->where('g.groupTypeCode = :type')
            ->andWhere('g.name = :name')
            ->setParameter('type', $type)
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

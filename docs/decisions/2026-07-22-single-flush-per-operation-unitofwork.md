# Décision — Un seul flush() par opération métier (UnitOfWork)

**Date :** 2026-07-22  
**Statut :** adopté  
**Priorité :** PERFORMANCE (#1) — cf. `2026-07-22-performance-first-review-criterion.md`

## Décision

Chaque opération métier complète (Handler / Command applicatif) ne doit
provoquer **qu’un seul** `EntityManager::flush()`.

Mécanisme :

1. **`UnitOfWork`** (`Shared\Infrastructure\Persistence\UnitOfWork`) — seul
   détenteur autorisé de `EntityManagerInterface` :
   - `persist(object)` → délègue `persist()` **sans** flush
   - `commit()` → seul `flush()` du projet
   - `find()` / `createQueryBuilder()` — délégations ORM pour le chemin
     **load→mutate→persist→commit** (signalé : hors API minimale
     persist/commit du prompt, indispensables tant que des lectures ORM
     légitimes existent ; ADR-003 pour les lectures pures reste DBAL)

2. **Deptrac** — couche `DoctrineEntityManager` isolée ; seule la couche
   `UnitOfWork` peut en dépendre. Les Repositories / Handlers dépendent de
   `UnitOfWork` (et `Connection` pour le DBAL).

3. **Règle PHPStan custom**
   `App\PHPStan\Rules\NoEntityManagerFlushOutsideUnitOfWorkRule` —
   toute méthode `->flush()` dont le récepteur est typé
   `EntityManager(Interface)` hors `UnitOfWork::commit()` = erreur
   bloquante.

## Pourquoi une discipline documentée seule ne suffisait pas

Le legacy a ralenti parce que chaque `save()` faisait `persist+flush`.
Une convention « n’appelez pas flush » se dilue en revue. Les deux
garde-fous mécaniques (Deptrac + PHPStan) rendent l’erreur **impossible
à merger** : `deptrac analyse` et `phpstan analyse` échouent.

## Conséquence Handlers

Après tous les `repository->save()/assign()/revoke()/delete()`,
**un seul** `$this->unitOfWork->commit()` en fin de `__invoke()`.

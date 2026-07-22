# Journal — Commande de purge des comptes de test

Date : 2026-07-21

## Contexte

Les tests d'intégration créent des `party_account` persistants (jamais
nettoyés). La base locale devenait illisible pour le front jetable / démo.

## Commande

`app:party:purge-test-accounts`
(`src/Modules/Party/Infrastructure/Command/PurgeTestPartyAccountsCommand.php`)

- **ENV** : refuse immédiatement si `APP_ENV` ∉ `{dev, test}` (aucun flag de
  contournement).
- **Dry-run par défaut** : compte les candidats, n'écrit rien.
- **`--execute`** : soft-delete réel (transaction ORM).
- **Confirmation** : taper exactement `yes`, sauf si `--force`.
- **Usage** : CLI manuelle uniquement — **jamais HTTP**, jamais un cron /
  listener / pipeline automatique.

## Choix du critère (sûreté)

### Rejeté — purge par `created_at`

Agence `myGO` et un compte hors-test (`m.arbi.maacha@gmail.com`) partagent
le même jour calendaire que la masse de tests.

### Retenu — email `@example.com` + préfixes CoreCredential sans email

```sql
SELECT pa.id
FROM party_account pa
WHERE pa.deleted_at IS NULL
  AND pa.display_name <> 'myGO'
  AND (pa.email IS DISTINCT FROM 'booking@mygo.pro')
  AND (
    pa.email ILIKE '%@example.com'
    OR (
      pa.email IS NULL
      AND pa.display_name ~ '^(LocalCred|OAuthCred|FindByIdentity|MultiCred) [0-9a-f]{8}$'
    )
  );
```

## Mutation — soft-delete Domain (réécriture)

**Hard delete en cascade abandonné** : incompatible avec l'append-only des
assignations (`revoke` / `valid_to` uniquement) et inventait un DELETE SQL
inexistant ailleurs.

Approche retenue :

1. Domain `PartyAccount::delete()` pose `deletedAt` (`deleted_at`)
2. `PartyAccountRepositoryInterface::delete()` flush uniquement
3. Boucle candidats → `findById` → `delete()` → `repository->delete()`
4. **Aucune** touche à role / function / group_member / office / credentials

Distinction `disable()` vs `delete()` :
`docs/decisions/2026-07-21-soft-delete-vs-disable-party-account.md`.

La liste (`ListPartyAccountsHandler`) filtre déjà `deleted_at IS NULL`.

## Vérification

`PurgeTestPartyAccountsCommandTest` : refuse `prod` ; dry-run conserve ;
`--execute --force` → `deleted_at IS NOT NULL` sur les candidats, ligne
toujours présente, myGO intact, recherche liste → 0 résultat.

## Usage

```bash
docker compose exec php php bin/console app:party:purge-test-accounts
docker compose exec php php bin/console app:party:purge-test-accounts --execute
docker compose exec php php bin/console app:party:purge-test-accounts --execute --force
```

## Reprise à froid

Journal — Commande de purge des comptes de test.
Les tests d'intégration créent des `party_account` persistants (jamais nettoyés). La base locale devenait illisible pour le front jetable / démo.
Date : 2026-07-21
Les tests d'intégration créent des `party_account` persistants (jamais

## Origine

```
# TASK — Commande de purge des comptes de test (dev/local uniquement)

## Objectif
Nettoyer les centaines de PartyAccount créés par les tests d'intégration
(persistent en base, jamais nettoyés après coup) pour retrouver un
environnement lisible côté front jetable/démo.

## Commande
src/Modules/Party/Infrastructure/Command/PurgeTestPartyAccountsCommand.php
(app:party:purge-test-accounts)

## Sécurité — NE JAMAIS exécutable en environnement autre que dev/test
- Vérifier explicitement APP_ENV au début de la commande : si différent de
  'dev' ou 'test', refuser immédiatement avec un message clair, exit code
  d'erreur. Aucune option pour forcer le contournement.
- Confirmation interactive obligatoire (demander à l'utilisateur de taper
  'yes' ou équivalent) sauf si un flag --force explicite est passé — pour
  éviter un accident en ligne de commande.

## Critère de sélection à purger
Les comptes créés par les tests suivent des conventions de nommage
reconnaissables dans le displayName (suffixes hex aléatoires, préfixes comme
"RoundTrip", "Role ", "Fn ", "Grp ", "Http ", etc. — regarder les patterns
réels utilisés dans les tests existants). PROTÉGER explicitement le compte
agence 'myGO' (jamais purgé, quel que soit le pattern). Documenter le
pattern SQL exact utilisé dans le journal pour qu'il soit vérifiable.

Alternative plus sûre si le pattern de nommage est trop risqué à généraliser :
purger uniquement les comptes créés APRÈS une date de référence (le début des
tests d'aujourd'hui), en excluant explicitement l'agence par son public_id
connu. Choisis l'approche la plus sûre, documente le choix.

## Vérification
Compter et afficher le nombre de comptes qui seront supprimés AVANT toute
suppression (dry-run par défaut, --execute pour vraiment supprimer). Après
exécution, réafficher le total restant.

## Test
Test d'intégration qui crée quelques comptes de test reconnaissables + le
compte agence, exécute la purge, vérifie que seuls les comptes de test ont
disparu et que myGO est intact.

## Documentation
- docs/journal/2026-07-2X-purge-test-accounts.md
- docs/STATUS.md mis à jour
- Note claire dans le README : cette commande ne doit JAMAIS être exposée
  via HTTP, ni appelée automatiquement — usage manuel CLI uniquement

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral et les
résultats. Si le pattern de sélection s'avère ambigu ou risqué au moment
d'écrire la requête, ARRÊTE-TOI et documente le dilemme plutôt que de choisir
un critère trop large.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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

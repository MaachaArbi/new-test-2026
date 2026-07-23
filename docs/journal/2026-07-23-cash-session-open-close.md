## Reprise à froid

Journal — 2026-07-23 — Cash Management : pivot cash_session (open/close).
`cash_session` + `cash_open_session()` + `cash_close_session()` uniquement. Hors vague : `cash_movement`, balances/counts, `cash_validate_session`, autres fonctions PL/pgSQL, HTTP.
Migration `Version20260723000000` — alignée `reference/schemas/schema-cash-management-v1.sql` (§3 + open/close). Preuve DB (après migrate) : \d cash_session → colonnes…

## Origine

```
là il vient de rectifier
# TASK — Cash Management : pivot cash_session (ouverture/fermeture uniquement)

## Lecture obligatoire
1. reference/schemas/schema-cash-management-v1.sql, lignes 156-193
   (cash_session), 374-382 (cash_open_session), 612-620 (cash_close_session)
2. src/Modules/Reglements/Application/PostReglementTransfer/PostReglementTransferHandler.php
   — PATTERN À RÉPLIQUER À L'IDENTIQUE : appel direct de la fonction SQL via
   DBAL (Connection::fetchOne), pas de UnitOfWork, pas d'entité ORM mappée
   en écriture. Le commentaire de ce fichier explique pourquoi.
3. src/Modules/Reglements/Application/ReglementReferentialValidator.php —
   PATTERN À RÉPLIQUER pour la validation d'existence : Connection::fetchOne
   DBAL explicite (ex. assertActivePaymentMethod), jamais un find() ORM.
   Ne PAS citer "même niveau que les autres Handlers CashManagement" —
   vérifié : aucun Handler CashManagement existant ne valide de
   party_account à ce jour, ce serait une fausse justification par
   précédent. Le précédent réel est côté Règlements.
4. src/Modules/CashManagement/Domain/ValueObject/InstrumentTrackingMode.php
   — PATTERN À RÉPLIQUER pour statusCode (voir section Domain ci-dessous) :
   enum PHP natif fermé, PAS un VO type BookingStatusCode/OpenReferentialCode
   (celui-ci est fait pour un référentiel ouvert en table, pas un CHECK SQL
   fermé — cas différent, ne pas suivre ce modèle ici)
5. tests/Integration/Shared/Translation/ErrorsTranslationCatalogueTest.php
   — comprendre le mécanisme de liste manuelle avant d'y toucher

## Portée volontairement réduite — LA PLUS ÉTROITE POSSIBLE
UNIQUEMENT : ouvrir une session, fermer une session. AUCUN mouvement
(cash_movement), AUCUNE lecture de solde (cash_session_balance), AUCUNE
validation par le caissier central (cash_validate_session), AUCUN comptage
de clôture (cash_count_session_currency — fonction séparée, hors vague),
AUCUN endpoint HTTP. Une session fermée dans cette vague reste dans un état
"coquille vide" — c'est normal et voulu, pas un manque.

## Migration — PRÉREQUIS, à faire EN PREMIER

`cash_session`, `cash_open_session()`, `cash_close_session()` N'EXISTENT PAS
ENCORE en base — vérifié : la migration Cash Management existante
(Version20260722200000) les exclut explicitement de son périmètre
("Hors périmètre : cash_session, cash_movement, fonctions PL/pgSQL").

Nouvelle migration slice (même pattern que Version20260722200000) :
- CREATE TABLE cash_session (lignes 156-193 du schéma, telles quelles)
- CREATE FUNCTION cash_open_session (lignes 374-382)
- CREATE FUNCTION cash_close_session (lignes 612-620)
RIEN d'autre du schéma (pas cash_movement, pas cash_movement_type, pas
cash_session_balance, pas les autres fonctions) — strictement ces 3 objets.

Avant d'écrire le moindre code PHP : exécute la migration, vérifie par une
requête directe (\d cash_session, \df cash_open_session, \df
cash_close_session) que les 3 objets existent réellement en base. Documente
cette vérification dans le journal avec la sortie réelle obtenue.

## Domain

src/Modules/CashManagement/Domain/ValueObject/CashSessionStatus.php
- Enum PHP natif fermé, MÊME PATTERN QUE InstrumentTrackingMode (pas
  OpenReferentialCode, pas BookingStatusCode — cas différent, voir lecture
  obligatoire point 4) : Open = 'open', Closed = 'closed', Validated = 'validated'

src/Modules/CashManagement/Domain/Entity/CashSession.php
- Reconstruction en lecture SEULEMENT (via repository, depuis une ligne DB) :
  id, publicId, holderAccountId, officeAccountId (nullable), statusCode
  (CashSessionStatus), openedAt, openedBy (nullable), closedAt (nullable),
  closedBy (nullable).
- PAS de validatedAt/validatedBy dans cette vague (colonnes existent en DB,
  toujours NULL ici — ne pas les exposer sur l'entité tant qu'aucune vague
  ne les alimente, éviter le code mort).
- PAS de factory create() ni de méthode close() sur l'entité — l'écriture
  ne passe PAS par l'ORM ici (voir section Doctrine ci-dessous). L'entité
  sert uniquement à LIRE l'état d'une session existante.

## PAS DE MAPPING DOCTRINE XML pour CashSession

Précédent vérifié : ReglementTransfer (même situation — écriture
exclusivement via fonction SQL) N'A AUCUN fichier .orm.xml dans
config/doctrine/mappings/Reglements/. Suis le même principe : aucun
config/doctrine/mappings/CashManagement/CashSession.orm.xml. La lecture
(findById) passe entièrement par DBAL, jamais par l'EntityManager pour
cette entité.

## POINT D'ATTENTION CRITIQUE — une seule session ouverte par titulaire

`uq_cash_session_one_open_per_holder` (index unique partiel WHERE status_code
= 'open') n'est PAS vérifié par `cash_open_session()` elle-même — c'est un
simple INSERT. Si un titulaire a déjà une session ouverte et qu'on appelle
`cash_open_session()` une seconde fois pour lui, PostgreSQL lèvera une
violation de contrainte unique brute.

Le Handler DOIT capturer cette violation spécifique (pas une erreur générique
500) et la traduire en exception métier dédiée
(`CashSessionAlreadyOpenException` ou nom équivalent cohérent avec les
conventions du module). Teste ce scénario explicitement — ouvrir deux fois
pour le même titulaire — et documente dans le journal le mécanisme exact de
détection utilisé (code SQLSTATE PostgreSQL pour violation unique = 23505 ;
vérifier que c'est bien CETTE contrainte et pas une autre avant de lever
l'exception dédiée, pas juste "toute erreur 23505 = déjà ouverte").

## Application

OpenCashSession/{Command,Handler}
- holderAccountId (int), officeAccountId (?int), openedBy (?int)
- ZÉRO TOLÉRANCE : valider l'existence des QUATRE comptes AVANT l'appel SQL,
  chacun via DBAL explicite (pattern ReglementReferentialValidator), pas de
  find() ORM, pas de confiance laissée à la FK SQL seule :
  - holderAccountId : obligatoire, doit exister dans party_account
  - officeAccountId : si non NULL, doit exister dans party_account
  - openedBy : si non NULL, doit exister dans party_account
  Une exception métier dédiée par cas d'échec (ou une exception commune
  paramétrée par le nom du champ concerné — à toi de choisir la forme la
  plus cohérente avec les conventions existantes du module, documente ton
  choix dans le journal).
- Appelle cash_open_session() via DBAL, retourne l'id créé

CloseCashSession/{Command,Handler}
- sessionId (int), closedBy (?int)
- ZÉRO TOLÉRANCE : si closedBy non NULL, valider son existence dans
  party_account AVANT l'appel SQL, même pattern DBAL explicite
- Appelle cash_close_session() via DBAL
- Le RAISE EXCEPTION de la fonction SQL ("Session introuvable ou déjà
  fermée") doit être traduit en exception métier dédiée, pas remonter tel
  quel

## Repository (lecture seule pour cette vague)
CashSessionRepositoryInterface : findById(int $id): ?CashSession — DBAL pur
(ADR-003), construit l'entité en lecture depuis une ligne cash_session.
PAS de méthode save()/create() sur ce repository dans cette vague (l'écriture
passe par les fonctions SQL, pas par le repository).

## Traductions d'erreurs — OBLIGATOIRE pour chaque nouvelle exception

Pour CHAQUE nouvelle DomainException créée dans cette vague (session déjà
ouverte, session introuvable/déjà fermée, ET chacune des validations de
compte inexistant — holder/office/openedBy/closedBy) :

1. Ajouter l'entrée `errorCode() → message` dans LES TROIS fichiers :
   translations/errors.fr.yaml, translations/errors.en.yaml,
   translations/errors.ar.yaml — même clé exacte que errorCode(), un message
   dans chaque langue (pas de copier-coller du français en anglais/arabe).
2. Ajouter la classe dans tests/Integration/Shared/Translation/
   ErrorsTranslationCatalogueTest.php (import `use` + entrée dans le
   provider de données existant).

Ce test échouera si un errorCode() n'a pas sa traduction dans les 3 langues
— vérifie qu'il passe avant de considérer la vague terminée, ne te fie pas
uniquement à phpunit global.

## Tests (PostgreSQL réel)
- Migration exécutée avec succès, 3 objets vérifiés présents (voir section
  Migration)
- Ouverture simple, toutes FK valides → id retourné, ligne cash_session
  vérifiée (status='open', closed_at NULL)
- Ouverture avec holderAccountId inexistant → exception dédiée, PAS de
  violation FK brute
- Ouverture avec officeAccountId inexistant → exception dédiée
- Ouverture avec openedBy inexistant → exception dédiée
- Ouverture d'une 2e session pour le même holderAccountId pendant que la 1ère
  est encore ouverte → exception métier dédiée, PAS une erreur 500 générique
- Fermeture d'une session ouverte, closedBy valide → status='closed',
  closed_at renseigné
- Fermeture avec closedBy inexistant → exception dédiée, avant l'appel SQL
- Fermeture d'une session déjà fermée → exception métier dédiée
- Fermeture d'une session inexistante → même exception ou une exception
  distincte si tu juges la distinction utile (documente ton choix)
- Après fermeture, un nouvel appel d'ouverture pour le même holder → réussit
- findById sur une session existante → tous les champs corrects, y compris
  officeAccountId NULL géré proprement

## Documentation
- docs/journal/2026-07-2X-cash-session-pivot.md — OBLIGATOIRE : preuve de
  la migration exécutée (sortie \d/\df réelle), mécanisme SQLSTATE 23505,
  forme retenue pour les 4 validations de compte, décision de ne pas mapper
  validatedAt/validatedBy cette vague, liste des codes de traduction ajoutés
- docs/STATUS.md : "Cash Management : session pivot (ouverture/fermeture)
  faite, appel direct des fonctions SQL (pattern Règlements), 4 FK validées
  systématiquement. Reste : mouvements, comptage/clôture réelle, validation
  caissier central, fonctions PL/pgSQL restantes, banque, dépôts,
  rapprochement, HTTP."
- docs/backlog/todo.md

Si un point de cette vague est ambigu et touche à une règle métier (pas un
détail technique), ARRÊTE-TOI et signale-le précisément plutôt que de
deviner.

Relance phpstan/deptrac/phpcpd/phpunit.

## Remontée
Pousse sur main, donne-moi la liste des fichiers créés/modifiés (noms
seulement). Je vérifie moi-même dans mon propre clone avant toute clôture.
Vérifie de nouveau avant exécution la qualité de prompt et s'il manque quelque chose
```

## Décisions prises

Décisions attribuées :
- Mandat de décision délégué par le prompt d'origine (Cursor — à valider)

---

# Journal — 2026-07-23 — Cash Management : pivot cash_session (open/close)

## Portée

`cash_session` + `cash_open_session()` + `cash_close_session()` uniquement.
Hors vague : `cash_movement`, balances/counts, `cash_validate_session`,
autres fonctions PL/pgSQL, HTTP.

## Schéma / migration

Migration `Version20260723000000` — alignée
`reference/schemas/schema-cash-management-v1.sql` (§3 + open/close).

Preuve DB (après migrate) :

```text
\d cash_session
→ colonnes holder/office/status/opened_*/closed_*/validated_*
→ Indexes : uq_cash_session_one_open_per_holder (partial WHERE open),
  idx_cash_session_office, uq_cash_session_public_id
→ Check : status_code IN (open,closed,validated) + chk_session_lifecycle

\df cash_open_session / cash_close_session
→ signatures (bigint,bigint,bigint) → bigint / (bigint,bigint) → void
```

Les fonctions sont **auto-suffisantes** (pas de dépendance à
`cash_movement` ni tables satellites).

## Pattern d'écriture (miroir PostReglementTransfer)

| Opération | Mécanisme | UnitOfWork / ORM |
|---|---|---|
| Open | `SELECT cash_open_session(...)` DBAL | Non |
| Close | `SELECT cash_close_session(...)` DBAL | Non |
| Lecture | `DbalCashSessionRepository::findById` | Non (pas de `.orm.xml`) |

`CashSession` Domain = **reconstruction lecture seule**
(`fromPersistence`) — pas de `create()` / `close()` Domain.

## Mapping erreurs SQL → Domain

| Situation | SQLSTATE / signal | Exception / code |
|---|---|---|
| 2ᵉ session open même holder | **23505** + nom contrainte `uq_cash_session_one_open_per_holder` uniquement (pas tout 23505) | `CashSessionAlreadyOpenException` → `cash_session.already_open` |
| Close introuvable ou déjà fermée | RAISE `Session % introuvable ou déjà fermée` | `CashSessionNotFoundOrAlreadyClosedException` → `cash_session.not_found_or_already_closed` (une seule exception, miroir SQL) |
| Compte party manquant (holder / office / opened_by / closed_by) | Pré-contrôle DBAL `party_account` | `CashSessionReferencedAccountNotFoundException` — **une classe, quatre** `errorCode()` |

## Traductions (errors fr/en/ar)

- `cash_session.already_open`
- `cash_session.not_found_or_already_closed`
- `cash_session.holder_account_not_found`
- `cash_session.office_account_not_found`
- `cash_session.opened_by_not_found`
- `cash_session.closed_by_not_found`

## Tests

Integration PostgreSQL : open + hydrate, holder manquant, double-open,
close + reopen, close missing/already closed, closed_by manquant,
`findById` null.

## Qualité

- phpstan : OK
- deptrac : 0 violations
- phpunit : 384 tests, 2591 assertions (2 notices préexistants)
- phpcpd : 5 clones acceptés (todo) — aucun nouveau clone Cash session

Note : `validated_at` / `validated_by` existent en SQL mais ne sont **pas**
exposés sur l'entité Domain cette vague.

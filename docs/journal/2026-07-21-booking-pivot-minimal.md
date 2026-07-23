## Reprise à froid

Journal — 2026-07-21 — Pivot Booking minimal (Domain + Infrastructure).
Vague volontairement réduite du pivot `booking` (partitionné) : colonnes minimales uniquement (pas montants, pas workflow, pas extensions). Références : `schema-booking-v1.sql` L268-460, décisions conceptuelles #1…
Vague volontairement réduite du pivot `booking` (partitionné) : colonnes
minimales uniquement (pas montants, pas workflow, pas extensions).

## Origine

```
# TASK — Module Booking : pivot Booking minimal (Domain + Infrastructure)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, table booking (lignes 287-460) —
   colonnes réelles, commentaire sur le partitionnement (lignes 268-286)
2. reference/conceptual-models/modele-conceptuel-booking.md, décisions #1,
   #2, #3, #13
3. Le pattern OpenReferentialCode déjà validé (3 cas Party) — à répliquer
   pour service_type_code/status_code/channel_code

## Portée volontairement réduite (PAS les 40+ colonnes du schéma)
Uniquement : id, publicId, bookingDate (clé de partition), folderId,
serviceTypeCode, statusCode, customerAccountId, supplierAccountId
(NULLABLE — cas réel "Sans Fournisseur", confirmé décision #du modèle
conceptuel, ne jamais le rendre obligatoire), officeAccountId, startDate,
endDate (nullable), channelCode (défaut 'backoffice').
PAS dans ce prompt : montants/devises, price_breakdown, workflow
(is_on_request, assigned_agent...), extensions de service. Vagues séparées.

## Domain

src/Modules/Booking/Domain/ValueObject/
├── BookingServiceTypeCode.php   — extends OpenReferentialCode (maxLength 30)
├── BookingStatusCode.php        — extends OpenReferentialCode (maxLength 30)
└── BookingChannelCode.php       — extends OpenReferentialCode (maxLength 30)
(3 nouvelles exceptions Domain associées, même structure que
InvalidPartyRoleCodeException — empty/tooLong)

src/Modules/Booking/Domain/Entity/Booking.php
- Constructeur privé. Factory create(folderId, serviceTypeCode: 
  BookingServiceTypeCode, statusCode: BookingStatusCode, customerAccountId,
  ?supplierAccountId, officeAccountId, startDate: DateTimeImmutable,
  ?endDate, channelCode: BookingChannelCode = déjà résolu en 'backoffice' par
  défaut côté Application, pas de valeur par défaut cachée dans le Domain)
- Validation : si endDate fourni, doit être >= startDate (miroir du CHECK
  DB ck_booking_dates — même logique que la validation email : le Domain
  porte la vraie règle, la contrainte DB n'est qu'un filet)
- bookingDate : posé automatiquement à la date du jour à la création
  (cohérent avec le DEFAULT CURRENT_DATE du schéma — le Domain doit le
  fixer explicitement, pas compter sur un défaut DB implicite qu'il ne
  contrôle pas)
- Getters uniquement pour cette vague (pas de mutation — les transitions de
  statut sont un futur sujet à part entière, pas improvisé ici)

## Repository — POINT D'ATTENTION CRITIQUE
BookingRepositoryInterface : findById(int $id): ?Booking, save(), etc.

ATTENTION : la PK réelle de la table est composite (id, booking_date). 
EntityManager::find() de Doctrine attend normalement la clé complète.
NE PAS supposer que $em->find(Booking::class, $id) fonctionnera avec un
id seul. Teste explicitement ce comportement en premier, AVANT d'écrire le
reste de l'implémentation. Si ça échoue comme attendu, implémente
findById() via une QueryBuilder filtrant sur id seul (l'unicité globale de
id est garantie par la séquence IDENTITY globale, pas par partition — cf.
commentaire du schéma ligne 284). Documente précisément dans le journal ce
qui a été testé et ce qui fonctionne réellement, pas ce qui était supposé
fonctionner.

## Mapping Doctrine XML — le vrai nouveau terrain
config/doctrine/mappings/Booking/Booking.orm.xml
- DEUX éléments <id> : id (bigint, generator IDENTITY) ET bookingDate (date,
  PAS de generator — fait partie de la clé mais n'est jamais auto-généré)
- folderId : mapping simple bigint (FK SQL réelle vers booking_folder,
  contrairement aux futures tables filles de booking elle-même)
- serviceTypeCode/statusCode/channelCode : Doctrine Types custom (même
  pattern que party_role_code) à créer dans
  src/Modules/Booking/Infrastructure/Doctrine/Type/
- NE PAS marquer publicId unique="true" seul dans le mapping (la vraie
  contrainte DB est composite (public_id, booking_date), pas publicId
  seul — éviter une déclaration Doctrine qui ne correspond pas à la
  réalité SQL, même si ça n'a pas d'effet runtime puisqu'on ne génère
  jamais de schéma depuis Doctrine)

Ne PAS créer les partitions (déjà faites dans schema-booking-v1.sql /
migration à venir) — ce prompt ne touche qu'au Domain/Infrastructure PHP.

## Migration
Une nouvelle migration slice (comme booking_folder) : uniquement la table
booking + ses partitions déjà définies dans le schéma (booking_y2026m07/08/09
+ booking_default), le référentiel booking_service_type/status/channel
minimal nécessaire (avec leurs valeurs déjà seedées dans le schéma). PAS le
reste du fichier schema-booking-v1.sql (extensions, charges, etc.).

## Tests (PostgreSQL réel)
- Création avec supplierAccountId NULL → OK (cas réel confirmé, pas une
  anomalie)
- endDate < startDate → rejeté par le Domain, AVANT toute tentative SQL
- Round-trip complet : create, save, findById (documenter précisément la
  méthode qui fonctionne), vérifier chaque champ
- Deux booking créés à la même date : vérifier qu'ils peuvent coexister
  (pas de contrainte d'unicité sur booking_date elle-même, juste la clé de
  partition)

## Documentation
- docs/journal/2026-07-2X-booking-pivot-minimal.md — OBLIGATOIRE : section
  dédiée documentant précisément ce qui a été testé sur le comportement
  find()/composite PK, avec le résultat réel observé (pas supposé)
- docs/STATUS.md : "Booking : booking_folder + pivot booking (colonnes
  minimales) faits. Reste : montants, extensions par service, workflow,
  charges/settlements, HTTP."
- docs/backlog/todo.md

Si le comportement composite PK pose un problème que tu ne sais pas
résoudre proprement (pas juste "difficile", mais un vrai blocage
architectural), ARRÊTE-TOI et documente précisément le blocage plutôt que
de contourner avec un id artificiel ou une structure qui s'écarterait du
schéma figé.

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Pivot Booking minimal (Domain + Infrastructure)

## Contexte

Vague volontairement réduite du pivot `booking` (partitionné) : colonnes
minimales uniquement (pas montants, pas workflow, pas extensions).
Références : `schema-booking-v1.sql` L268-460, décisions conceptuelles #1
(partitionnement), #2 (FK applicatives filles), #3 (`is_on_request`
orthogonal — hors vague), #13 (`trip_type` — hors vague).
`supplier_account_id` NULLABLE confirmé (cas réel « Sans Fournisseur »).

## Sonde PK composite / Doctrine — résultats observés (pas supposés)

Environnement : Doctrine ORM 3.x + PostgreSQL 16, table `booking`
`PARTITION BY RANGE (booking_date)`, PK `(id, booking_date)`.

### Tentative 1 — mapping `<id>` + `<generator strategy="IDENTITY"/>`

**Résultat réel** : `Doctrine\ORM\Mapping\MappingException` au chargement
des métadonnées / premier `persist` :

> Single id is not allowed on composite primary key in entity
> App\Modules\Booking\Domain\Entity\Booking

Doctrine appelle `getSingleIdentifierFieldName()` pour finaliser un
générateur IDENTITY — incompatible avec une PK composite. **Bloquant**
pour le couple « 2 × `<id>` + IDENTITY ».

### Tentative 2 — PK composite sans generator + `DateTimeImmutable` sur
`bookingDate`

Après passage à generator NONE + pré-assignation `nextval` :

**Résultat réel** à `persist()` :

> Object of class DateTimeImmutable could not be converted to string
> (UnitOfWork::getIdHashByIdentifier)

Doctrine ne hashe les identifiants composites qu'en `string|int` (enums
BackedEnum supportés ; `DateTimeInterface` non). Limitation connue ORM
(cf. issues doctrine/orm #10831, #7627). **Bloquant** pour
`date_immutable` en `<id>`.

### Solution retenue (sans inventer un id artificiel hors schéma)

1. **Deux `<id>`** : `id` (bigint) + `bookingDate` (**string Y-m-d**, colonne
   SQL `DATE` inchangée). Getter Domain `bookingDate(): DateTimeImmutable`
   reconstruit depuis la string.
2. **Pas de generator Doctrine** — `id` pré-assigné dans
   `DoctrineBookingRepository::save()` via
   `nextval(pg_get_serial_sequence('booking', 'id'))` + reflection.
3. **Migration slice** : `GENERATED BY DEFAULT AS IDENTITY` (écart vs
   `ALWAYS` du schéma figé) pour autoriser l'INSERT avec id explicite.
   Sémantique séquence globale inchangée.
4. **`findById(int $id)`** : QueryBuilder `WHERE booking.id = :id` —
   **fonctionne** (unicité globale IDENTITY, commentaire schéma L284).

### Sonde runtime `EntityManager::find()` (test d'intégration)

| Appel | Résultat observé |
|---|---|
| `$em->find(Booking::class, $id)` (scalaire) | **`Doctrine\ORM\ORMInvalidArgumentException`** : « Binding an entity with a composite primary key to a query is not supported. You should split the parameter into the explicit fields and bind them separately. » — **ne retourne pas** l'entité |
| `$em->find(Booking::class, ['id' => $id, 'bookingDate' => 'Y-m-d'])` | **OK** — entité rechargée |
| QueryBuilder `WHERE id = :id` (`BookingRepository::findById`) | **OK** — méthode retenue pour le port Domain |

Test : `BookingCompositePrimaryKeyProbeTest`.

## Autres écarts slice migration vs schéma figé

- `achat_currency_code` / `vente_currency_code` : `DEFAULT 'TND'` (ORM
  n'insère pas les colonnes non mappées ; schéma de référence NOT NULL sans
  DEFAULT — jusqu'à la vague montants/devises).
- `pointvente_*` : BIGINT sans `REFERENCES pointvente` (module non importé).
- Référentiels créés : `booking_service_type`, `booking_channel`,
  `booking_status`, `booking_processing_status` (+ seeds). Partitions
  `y2026m07/08/09` + `booking_default`.

## Faits Domain / Application / Infra

- VOs `BookingServiceTypeCode` / `StatusCode` / `ChannelCode` extends
  `OpenReferentialCode` (max 30) + 3 exceptions empty/tooLong
- `Booking::create(...)` fixe `bookingDate = today`, valide
  `endDate >= startDate`, `supplierAccountId` nullable, `channelCode`
  obligatoire (défaut `'backoffice'` uniquement dans
  `CreateBookingCommand`)
- Mapping XML + 3 types DBAL + repo Doctrine
- Tests : Unit + Integration (NULL supplier, dates invalides Domain,
  round-trip `findById`, deux booking même `booking_date`)

## Hors périmètre

Montants/devises, `price_breakdown`, workflow (`is_on_request`, agent…),
extensions service, charges/settlements, HTTP, transitions de statut.

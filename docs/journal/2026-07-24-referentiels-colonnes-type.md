## Reprise à froid

Création de 4 référentiels pour 5 colonnes VARCHAR libres (document_type ×2,
address_type, event_type ×2) + renommage board_type → board_type_snapshot
(texte libre volontaire). Migration runtime pour tables présentes.
Chaîne attendue : 293 + 6 = 299 tables.

## Origine

```
TASK — Colonnes de type en chaîne libre : créer les référentiels manquants (6 colonnes)

CONTEXTE
Six colonnes VARCHAR libres dont les valeurs ne vivent que dans un commentaire SQL.
Contraire au principe §19 (« table de référence, jamais une chaîne libre »).

Règle de décision (utilisateur, 24/07) :
  - RÉFÉRENTIEL si montré à l'utilisateur (traduit) ou extensible sans code
  - CHECK si ensemble fermé et technique
  - JAMAIS un VARCHAR nu avec les valeurs en commentaire

LES 6 DÉCISIONS
1+2+3. document_type → ref_document_type partagé (modèle A) Party+Booking
4. address_type → party_address_type (modèle A)
5. log_activity.event_type → log_event_type (modèle B)
6. pricing_rule_log.event_type → pricing_log_event_type (modèle B, liste séparée)
7. board_type → RESTE TEXTE LIBRE, renommé board_type_snapshot + commentaire corrigé

STRUCTURE : modèle A (party_role) / modèle B (log_entity_type simplifié).
ORDRE : ref_document_type dans schema-ref-common.sql (étape 1).
SEEDS : recenser DISTINCT runtime + grep src/tests ; signaler valeurs hors commentaire.
PORTÉE : reference/ + migration runtime si tables existent + backend.
VÉRIFS : chaîne 299 ; FK sur 5 colonnes ; rejet INSERT invalide ; board_type_snapshot
sans FK ; qualité ; journal + commit + PUSH.
```

## Décisions prises

- Liste partagée pour document_type, listes séparées pour event_type (utilisateur, 24/07)
- board_type reste un texte libre volontaire — libellé commercial fournisseur (utilisateur, 24/07)
- Modèle A vs B selon visibilité utilisateur (architecte DB)

---

# Journal — 2026-07-24 — référentiels colonnes de type

## Recensement valeurs

| Colonne | Runtime DISTINCT | Code src/tests | Seed final |
|---|---|---|---|
| document_type | `passport` (+ NULL travelers) | `'passport'` | passport, cin, driving_license, contract, logo, other |
| address_type | (table vide) | — | legal, billing, delivery, domiciliation, other |
| log event_type | table absente | — | created, status_change, **processing_status_change**, notification_supplier, notification_client, payment, loyalty_points |
| pricing event_type | table absente | pricing-test-data: created, updated | created, updated, activated, deactivated, deleted |
| board_type | `half_board` (65) | `'half_board'` | N/A (texte libre) |

**Signalé :** `processing_status_change` absent du commentaire incomplet de `log_activity.event_type`,
mais documenté dans `schema-booking-v1.sql` / sujets-reportés §19 — **ajouté au seed**.

## Runtime tables

| Table | Présente | Action migration |
|---|---|---|
| party_account_document | oui | FK → ref_document_type |
| booking_traveler | oui | FK → ref_document_type |
| party_account_address | oui | FK → party_address_type |
| booking_accommodation_detail | oui | RENAME board_type → board_type_snapshot |
| log_activity | non | reference/ seul (+ log_event_type) |
| pricing_rule_log | non | reference/ seul (+ pricing_log_event_type) |

Migration : `Version20260724150000` (BEGIN/COMMIT atomique).

## Backend

- `BookingAccommodationDetail` : `boardType` → `boardTypeSnapshot` (entité, mapping, command, handler, test)
- documentType inchangé côté PHP (string) — contrainte portée par la FK DB

## Vérification 1 — chaîne 16/16

```text
=== STEP 1/16 … 16/16 === OK
 tables 
--------
    299
```

## Vérification 2 — FK des 5 colonnes converties

```text
          tbl           |      col      |          ref           
------------------------+---------------+------------------------
 booking_traveler       | document_type | ref_document_type
 log_activity           | event_type    | log_event_type
 party_account_address  | address_type  | party_address_type
 party_account_document | document_type | ref_document_type
 pricing_rule_log       | event_type    | pricing_log_event_type
```

## Vérification 3 — rejet INSERT invalide (brut)

```text
ERROR: … party_account_document … Key (document_type)=(not_a_real_type) is not present in table "ref_document_type".
ERROR: … party_account_address … Key (address_type)=(Legal) is not present in table "party_address_type".
ERROR: … log_activity … Key (event_type)=(not_an_event) is not present in table "log_event_type".
ERROR: … pricing_rule_log … Key (event_type)=(not_an_event) is not present in table "pricing_log_event_type".
ERROR: … booking_traveler … Key (document_type)=(not_a_real_type) is not present in table "ref_document_type".
```

## Vérification 4 — board_type_snapshot

```text
     column_name     | fk_count 
---------------------+----------
 board_type_snapshot |        0
```

## Vérification 5 — qualité

```text
phpstan : OK
php-cs-fixer : OK (fichiers touchés)
deptrac : Violations 0
phpunit : OK — Tests: 397, Assertions: 2681, PHPUnit Notices: 2
```

## Écarts

Aucun écart structurel. Note : `document_type` permissions (`document_type` catalogue templates)
reste une table distincte — homonymie de nom, périmètres différents (signalé, non fusionné).

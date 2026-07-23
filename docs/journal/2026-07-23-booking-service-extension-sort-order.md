## Reprise à froid

Ajout de `sort_order` sur `booking_service_extension` — migration seule.
Décision pilote DB du 23/07 : ce n’est pas une dette différée.
Avant : 3 colonnes (code, label, created_at). Après : + `sort_order`
(accommodation=0, transport_segment=1, car_rental=2). Pas de mapping ORM.

## Origine

```
# TASK — booking_service_extension : ajouter sort_order (migration seule)

## Contexte
Décision tranchée avec le pilote DB (23/07) : sort_order doit être ajouté
en base, ce n'est pas une dette différée. Vérification préalable requise
avant d'appliquer quoi que ce soit.

## Étape 1 — Confirmer l'état actuel (OBLIGATOIRE avant toute écriture)
\d booking_service_extension
Doit confirmer l'ABSENCE de sort_order (3 colonnes attendues : code, label,
created_at). Si sort_order existe déjà, ARRÊTE-TOI et signale-le au lieu de
continuer — ce serait en contradiction avec ce qu'on a établi.

## Étape 2 — Nouvelle migration (ne PAS modifier Version20260722100000,
## qui est déjà appliquée)

ALTER TABLE booking_service_extension ADD COLUMN sort_order SMALLINT NOT NULL DEFAULT 0;

UPDATE booking_service_extension SET sort_order = 0 WHERE code = 'accommodation';
UPDATE booking_service_extension SET sort_order = 1 WHERE code = 'transport_segment';
UPDATE booking_service_extension SET sort_order = 2 WHERE code = 'car_rental';

## Étape 3 — Vérification post-migration
\d booking_service_extension (confirmer la colonne présente)
SELECT code, sort_order FROM booking_service_extension ORDER BY sort_order;
(confirmer les 3 valeurs exactes : accommodation=0, transport_segment=1,
car_rental=2)

## Portée
UNIQUEMENT cette migration. Aucun mapping Doctrine à créer/modifier — cette
table n'a aucun mapping ORM aujourd'hui (vérifié : consommée uniquement en
DBAL par AssertBookingServiceType). Aucun autre fichier touché.

## Documentation
- docs/journal/2026-07-23-booking-service-extension-sort-order.md : coller
  les sorties réelles des étapes 1 et 3. APPLIQUER LA CONVENTION ACTÉE LE
  23/07 (docs/decisions/2026-07-23-journal-convention-origine.md) : en-tête
  avec "Reprise à froid" (3-5 lignes), "Origine" (ce prompt collé verbatim),
  "Décisions prises" (attribution explicite — ici, tout vient du pilote DB
  + de l'utilisateur, à taguer en conséquence), AVANT le corps habituel.
- Pas de changement à STATUS.md/todo.md (pas une feature, une correction de
  schéma ponctuelle)

## Remontée
Pousse sur main, donne-moi le nom du commit. Je vérifie moi-même.
```

## Décisions prises

- Ajouter `sort_order` en base maintenant (pas dette différée) (architecte)
- Valeurs seed : accommodation=0, transport_segment=1, car_rental=2 (architecte)
- Nouvelle migration dédiée, ne pas retoucher `Version20260722100000` (utilisateur)
- Portée migration seule, aucun mapping Doctrine / STATUS / todo (utilisateur)

---

# Journal — 2026-07-23 — booking_service_extension.sort_order

## Migration

`migrations/Version20260723120000.php`

## Étape 1 — état avant (sortie réelle)

```text
                Table "public.booking_service_extension"
   Column   |           Type           | Collation | Nullable | Default 
------------+--------------------------+-----------+----------+---------
 code       | character varying(30)    |           | not null | 
 label      | character varying(100)   |           | not null | 
 created_at | timestamp with time zone |           | not null | now()
Indexes:
    "booking_service_extension_pkey" PRIMARY KEY, btree (code)
Referenced by:
    TABLE "booking_service_type_extension" CONSTRAINT "booking_service_type_extension_extension_code_fkey" FOREIGN KEY (extension_code) REFERENCES booking_service_extension(code)
```

Absence de `sort_order` confirmée → migration appliquée.

## Étape 3 — état après (sorties réelles)

```text
                Table "public.booking_service_extension"
   Column   |           Type           | Collation | Nullable | Default 
------------+--------------------------+-----------+----------+---------
 code       | character varying(30)    |           | not null | 
 label      | character varying(100)   |           | not null | 
 created_at | timestamp with time zone |           | not null | now()
 sort_order | smallint                 |           | not null | 0
Indexes:
    "booking_service_extension_pkey" PRIMARY KEY, btree (code)
Referenced by:
    TABLE "booking_service_type_extension" CONSTRAINT "booking_service_type_extension_extension_code_fkey" FOREIGN KEY (extension_code) REFERENCES booking_service_extension(code)
```

```text
       code        | sort_order 
-------------------+------------
 accommodation     |          0
 transport_segment |          1
 car_rental        |          2
(3 rows)
```

## Hors périmètre

Mapping Doctrine, Domain, STATUS.md, todo.md — non touchés.

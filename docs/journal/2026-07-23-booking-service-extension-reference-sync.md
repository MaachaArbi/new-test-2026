## Reprise à froid

Alignement documentaire de `reference/schemas/schema-booking-v1.sql` :
ajout de `sort_order` sur `booking_service_extension` uniquement, pour
coller à la production (migration `Version20260723120000`). Pas de code
ni de nouvelle migration.

## Origine

```
# TASK — Ajouter sort_order à reference/schemas/schema-booking-v1.sql (doc seule)

## Contexte
reference/schemas/schema-booking-v1.sql est déjà correct sur label
(VARCHAR(100), contenu anglais = production, vérifié inchangé lors du
dernier push). Il lui manque uniquement sort_order, ajouté en production
le 23/07 (migration Version20260723120000) mais jamais reporté dans ce
fichier de référence.

## Modification chirurgicale — booking_service_extension uniquement
Ajouter la colonne et les valeurs, sans toucher au reste du fichier :

CREATE TABLE booking_service_extension (
    code        VARCHAR(30) PRIMARY KEY,
    label       VARCHAR(100) NOT NULL, -- libellé technique (anglais), pas destiné à l'UI
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO booking_service_extension (code, label, sort_order) VALUES
    ('accommodation',     'Accommodation detail / hotel rooms', 0),
    ('transport_segment', 'Transport segments',                 1),
    ('car_rental',        'Car rental detail',                  2);

## Portée
UNIQUEMENT ce bloc dans reference/schemas/schema-booking-v1.sql. Ne touche
à AUCUN autre fichier, AUCUNE autre table de ce schéma. Documentation
seule — aucun code, aucune migration (déjà appliquée), aucun test.

## Documentation
docs/journal/2026-07-23-booking-service-extension-reference-sync.md —
convention du 23/07 (Reprise à froid / Origine / Décisions).

## Remontée
Pousse sur main, donne-moi le nom du commit.
```

## Décisions prises

- Reporter `sort_order` dans le schéma de référence booking (utilisateur)
- Ne toucher qu’au bloc `booking_service_extension` (utilisateur)
- Valeurs 0 / 1 / 2 alignées production (utilisateur)

---

# Journal — 2026-07-23 — schema-booking-v1 : sort_order reference sync

## Changement

`reference/schemas/schema-booking-v1.sql` — table `booking_service_extension` :
colonne `sort_order SMALLINT NOT NULL DEFAULT 0` + seeds
accommodation=0, transport_segment=1, car_rental=2.

Label reste `VARCHAR(100)` / anglais (inchangé).

## Hors périmètre

Code, migrations, tests, autres tables du schéma booking.

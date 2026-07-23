## Reprise à froid

Synchronisation documentaire du package BDD reçu le 23/07 (44 fichiers).
26 fichiers copiés tels quels dans `reference/` ; `schema-booking-v1.sql`
exclu (version reçue incorrecte). Pas de feature code — lecture seule
`reference/` respectée.

## Origine

```
# TASK — Organiser et pousser la documentation de référence BDD (26/44 fichiers copiés)

RÈGLE ABSOLUE (reference/README.md, que tu connais déjà) : reference/ est en
lecture seule pour les agents — copier tel quel, ne jamais éditer/reformuler/
compléter. Toute question de contenu remonte à l'utilisateur.

## Étape 1 — Localiser les fichiers reçus
Les 44 fichiers sont dans var/incoming/reference-package-2026-07-23/
(chemin fixe, pas à deviner). Confirme leur présence (ls, doit lister 44
fichiers) avant de continuer — si le dossier est vide, incomplet, ou le
compte ne correspond pas, ARRÊTE-TOI et signale-le plutôt que de supposer.

## ⚠️ EXCLUSION EXPLICITE — schema-booking-v1.sql
NE PAS copier schema-booking-v1.sql dans reference/schemas/ dans cette
tâche. Le fichier reçu contient encore l'ancienne version de
booking_service_extension (label VARCHAR(80), contenu français) au lieu de
la version corrigée convenue (VARCHAR(100), contenu anglais = production).
Il sera remplacé séparément une fois la version corrigée reçue. Laisse le
reference/schemas/schema-booking-v1.sql actuel du repo totalement
inchangé.

## Étape 2 — Mapping exact (26 fichiers à copier)

reference/meta/ (remplace 1, ajoute 3) :
  00-INDEX.md                    -> REMPLACE reference/meta/00-INDEX.md
  01-architecture_decisions.md   -> NOUVEAU reference/meta/01-architecture_decisions.md
  00-project_overview.md         -> NOUVEAU reference/meta/00-project_overview.md
  sujets-reportes.md             -> NOUVEAU reference/meta/sujets-reportes.md

reference/schemas/ (remplace 1, ajoute 10 schémas + 11 diffs) :
  schema-cash-management-v1.sql      -> REMPLACE
  schema-core-identity-v1.sql        -> NOUVEAU
  schema-invoicing-v1.sql            -> NOUVEAU
  schema-log-v1.sql                  -> NOUVEAU
  schema-party-account-v1.sql        -> NOUVEAU
  schema-permissions-config-v1.sql   -> NOUVEAU
  schema-pointvente-v1.sql           -> NOUVEAU
  schema-product-catalogue-v1.sql    -> NOUVEAU
  schema-provider-integration-v1.sql -> NOUVEAU
  schema-ref-static-v1.sql           -> NOUVEAU
  pricing-test-data.sql              -> NOUVEAU
  diff-booking-log-generalization.diff    -> NOUVEAU
  diff-booking-reouverture-20-07.diff     -> NOUVEAU
  diff-core-auth-avancee.sql              -> NOUVEAU
  diff-party-franchise.sql                -> NOUVEAU
  diff-pricing-payment-modality.sql       -> NOUVEAU
  party-account-group-extension.diff      -> NOUVEAU
  ref-common-hebergement-extension.diff   -> NOUVEAU
  ref-static-accommodation-links.diff     -> NOUVEAU
  ref-static-airline-cabin-extension.diff -> NOUVEAU
  ref-static-country-group-extension.diff -> NOUVEAU
  reglements-currency_code-fix.diff       -> NOUVEAU

## Étape 3 — Fichiers reçus mais À NE RIEN FAIRE avec (17 fichiers)
- schema-pricing-v1.sql, schema-reglements-v1.sql, schema-ref-common.sql :
  DÉJÀ vérifiés identiques (byte pour byte) à reference/schemas/. Ne pas
  copier, ne pas toucher.
- Les 12 modele-conceptuel-*.md : DÉJÀ présents dans
  reference/conceptual-models/, identiques. Ne pas copier, ne pas toucher.
- 00-EXPERT-REVIEW.md, starter-prompt-cloture-permissions.md : aucune
  valeur pour un agent backend. Ne pas placer dans reference/.

## Étape 4 — Exécution
1. Copie chaque fichier de l'Étape 2 vers sa destination exacte (cp, pas mv).
2. git add reference/ — vérifie que schema-booking-v1.sql N'APPARAÎT PAS
   dans le diff de ce commit avant de committer.
3. git commit -m "docs(reference): sync BDD reference package 23/07/2026
   (26 fichiers — schema-booking-v1.sql exclu, en attente correction pilote)"
4. git push

## Étape 5 — Vérification post-push (OBLIGATOIRE avant de clôturer)
Re-clone ou git pull dans un dossier temporaire. Rapporte précisément :
- Les 26 fichiers de l'Étape 2 sont bien présents à la bonne destination
- schema-booking-v1.sql n'a PAS changé (confirme son contenu actuel est
  toujours l'ancienne version, pas la nouvelle)
- Compte exact reçu vs traité : 44 fichiers reçus au total = 26 copiés +
  3 déjà identiques (pricing/reglements/ref-common) + 12 conceptual-models
  déjà identiques + 2 exclus (EXPERT-REVIEW/starter-prompt) + 1 exclu ce
  round (booking). Rapporte ce compte exact, pas un chiffre approximatif.
- Tout écart constaté par rapport à ce qui précède

Ne clôture pas cette tâche sans ce rapport de vérification complet.

## Documentation
- docs/journal/2026-07-23-reference-sync-44-fichiers.md — appliquer la
  convention du 23/07 (Reprise à froid / Origine verbatim / Décisions
  attribuées). Inclure le rapport de vérification de l'Étape 5 en entier.
- Pas de changement à STATUS.md/todo.md (synchronisation documentaire,
  pas une feature).

## Remontée
Donne-moi le nom du commit une fois poussé. Je vérifie moi-même dans mon
propre clone avant de considérer cette tâche close.
```

## Décisions prises

- `reference/` lecture seule : copier tel quel, ne jamais éditer (utilisateur)
- Exclure `schema-booking-v1.sql` ce round (version reçue incorrecte) (utilisateur)
- Mapping exact 26 fichiers / ne pas toucher les 17 restants (utilisateur)
- Message de commit imposé (utilisateur)

---

# Journal — 2026-07-23 — Sync reference package BDD (26/44)

## Étape 1 — Paquet reçu

Chemin : `var/incoming/reference-package-2026-07-23/`  
Compte : **44 fichiers** (confirmé avant copie).

## Étape 4 — Commit

Message :
`docs(reference): sync BDD reference package 23/07/2026 (26 fichiers — schema-booking-v1.sql exclu, en attente correction pilote)`

Avant commit : `git status reference/` ne montrait **pas** `schema-booking-v1.sql`.

## Étape 5 — Rapport de vérification post-push

Clone temporaire : `git clone --depth 1 git@github.com:MaachaArbi/new-test-2026.git`  
HEAD vérifié : `ae10fe9`

### Les 26 fichiers de l'Étape 2 — présents (missing=0)

- `reference/meta/00-INDEX.md`
- `reference/meta/01-architecture_decisions.md`
- `reference/meta/00-project_overview.md`
- `reference/meta/sujets-reportes.md`
- `reference/schemas/schema-cash-management-v1.sql`
- `reference/schemas/schema-core-identity-v1.sql`
- `reference/schemas/schema-invoicing-v1.sql`
- `reference/schemas/schema-log-v1.sql`
- `reference/schemas/schema-party-account-v1.sql`
- `reference/schemas/schema-permissions-config-v1.sql`
- `reference/schemas/schema-pointvente-v1.sql`
- `reference/schemas/schema-product-catalogue-v1.sql`
- `reference/schemas/schema-provider-integration-v1.sql`
- `reference/schemas/schema-ref-static-v1.sql`
- `reference/schemas/pricing-test-data.sql`
- `reference/schemas/diff-booking-log-generalization.diff`
- `reference/schemas/diff-booking-reouverture-20-07.diff`
- `reference/schemas/diff-core-auth-avancee.sql`
- `reference/schemas/diff-party-franchise.sql`
- `reference/schemas/diff-pricing-payment-modality.sql`
- `reference/schemas/party-account-group-extension.diff`
- `reference/schemas/ref-common-hebergement-extension.diff`
- `reference/schemas/ref-static-accommodation-links.diff`
- `reference/schemas/ref-static-airline-cabin-extension.diff`
- `reference/schemas/ref-static-country-group-extension.diff`
- `reference/schemas/reglements-currency_code-fix.diff`

### `schema-booking-v1.sql` — non modifié

- Absent du commit `ae10fe9` (non stagé).
- `cmp` package reçu vs repo : **différents** (exclusion correcte).
- Repo (conservé) : `label VARCHAR(100)`, libellés techniques anglais, **pas** de `sort_order` dans le CREATE TABLE de référence.
- Package reçu (non copié) : `label VARCHAR(80)`, contenu français, avec `sort_order`.
- SHA-256 repo : `67425b1c29bd904c1d2e969e5c7dc49d73e85b4ae1e63b2d780789ddeef8d5eb`
- SHA-256 reçu : `9116c4c6e6a2718ab29c52913af381ebcee08c82259a9999677ca9f3c91ff426`

### Compte exact 44

| Catégorie | Nombre |
|---|---|
| Copiés (Étape 2) | 26 |
| Déjà identiques schemas (pricing / reglements / ref-common) | 3 |
| Déjà identiques conceptual-models (`modele-conceptuel-*.md`) | 12 |
| Exclus agent (`00-EXPERT-REVIEW.md`, `starter-prompt-cloture-permissions.md`) | 2 |
| Exclu ce round (`schema-booking-v1.sql`) | 1 |
| **Total** | **44** |

Vérifications annexes : les 3 schemas « déjà identiques » et les 12 conceptual-models sont bien `cmp`-identiques byte pour byte ; les 2 exclus agent sont absents de `reference/`.

### Écarts

Aucun écart par rapport au mapping demandé.
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

*(rempli après push — voir section ci-dessous mise à jour)*

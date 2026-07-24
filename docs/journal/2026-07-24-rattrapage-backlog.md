## Reprise à froid

Rattrapage documentaire du backlog `sujets-reportes.md` : §39 clos, ajout §64–68
(journée du 24/07 manquante). Mise à jour `00-INDEX.md` (68 points, 299 tables).
Documentation pure — aucun SQL.

## Origine

```
TASK — Rattrapage du backlog sujets-reportes.md (6 entrées manquantes)

CONTEXTE
reference/meta/sujets-reportes.md resté au 23/07 (63 points). Journée du 24/07 manquante.
Cause : prompts demandent un journal, jamais la mise à jour du backlog. Responsabilité
pilote DB. Corrigé pour l'avenir : chaque prompt futur demandera explicitement cette MAJ.

A. Remplacer §39 (résolu périmètre A)
B. Ajouter §64–68 en fin de fichier
C. Mettre à jour 00-INDEX.md (68 points, 299 tables, Cash §64)

VÉRIFS : 68 points ; §39 sans « reporté » ; INDEX sans « 63 points » ni « 293 tables » ;
aucun SQL modifié. Journal + commit + PUSH.
```

## Décisions prises

- Rattrapage des 6 entrées manquantes du backlog + règle : tout prompt futur doit demander
  la mise à jour de sujets-reportes.md (architecte DB)

---

# Journal — 2026-07-24 — rattrapage backlog

## Fichiers

- `reference/meta/sujets-reportes.md` — §39 remplacé ; §64–68 ajoutés
- `reference/meta/00-INDEX.md` — 68 points, 299 tables, mention §64 Cash Management
- Aucun fichier `.sql` touché

## Vérifications

```text
grep -c '^## [0-9]\+\.' sujets-reportes.md  → 68
§39 titre : … ✅ RÉSOLU le 24/07/2026 (périmètre A : identifiants) — pas de « reporté »
00-INDEX.md : 0× « 63 points », 0× « 293 tables »
git diff --name-only | grep '\.sql$' → (vide)
```

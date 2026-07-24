## Reprise à froid

§68 — retirer « MyGo » comme nom du produit dans la documentation.
Reformulation phrase par phrase (pas de rechercher-remplacer). Données client
et archives volontairement conservées. Chaîne 16/16, 299 tables.

## Origine

```
TASK — §68 : retirer « MyGo » comme nom du produit dans la documentation

Décision utilisateur (24/07) : formulation NEUTRE (« ce projet » / « le système »)
plutôt qu'un nom de produit. INTERDICTION de rechercher-remplacer.

CATÉGORIE 1 — NE PAS TOUCHER : données client (src/, tests/, pricing-test-data.sql)
CATÉGORIE 2 — NE PAS TOUCHER : archives + explications (§65/§68, journals, diffs,
  00-project_overview clarification)
CATÉGORIE 3 — À REFORMULER : ~17 occurrences (schemas, modèles, cadrage, backlog actif)

Clôturer §68. Vérifs + journal + commit + PUSH.
```

## Décisions prises

- Formulation neutre « ce projet » / « le système » plutôt qu'un nom de produit (utilisateur, 24/07)
- Données client et archives datées volontairement conservées (architecte DB)

---

# Journal — 2026-07-24 — nom produit neutre (§68)

## Phrases réécrites (relecture)

| Fichier | Avant → Après |
|---|---|
| `00-backend-project-overview.md` L1 | `(OS-TRAVEL / MyGo)` → `(ERP Tourisme)` |
| `01-backend-architecture-decisions.md` L1 | idem |
| `schema-ref-static-v1.sql` | `PROPRE à MyGo` → `PROPRE à ce projet` |
| idem COMMENT ON TABLE | `interne MyGo` → `interne à ce projet` |
| idem CMS | `hors périmètre MyGo` → `hors périmètre de ce projet` |
| `schema-product-catalogue-v1.sql` | `hors périmètre MyGo` → `hors périmètre de ce projet` |
| idem | `local à MyGo` → `local à ce projet` |
| `schema-provider-integration-v1.sql` | `MyGo interroge` → `Le système interroge` |
| `modele-conceptuel-ref-static.md` | `structurelle MyGo` → `propre à ce projet` ; `interne à MyGo` → `interne à ce projet` |
| `modele-conceptuel-product-catalogue.md` | `hors périmètre MyGo` → `hors périmètre de ce projet` |
| `modele-conceptuel-provider-integration.md` | `(hors MyGo)` → `(hors périmètre de ce projet)` ; `dans MyGo` → `dans le système` ; `MyGo ne stocke` → `le système ne stocke` ; `connu de MyGo` → `connu du système` |
| `modele-conceptuel-booking.md` | `périmètre financier de MyGo` → `… de ce projet` |
| `sujets-reportes.md` (~300,462,666,702) | mêmes reformulations neutres (hors §65/§68) |

Aucune tournure du type « hors périmètre le système » / « propre à le projet ».

## Vérifications

```text
git diff --stat src/ tests/ migrations/ → (vide)
grep -rin mygo src/  → 11 (inchangé)
grep -rin mygo tests/ → 18 (inchangé)
grep -rin mygo reference/schemas/ → pricing-test-data.sql (4) + diff historique (1)
Chaîne 16/16, 299 tables
§68 → ✅ RÉSOLU ; compteur backlog = 68
```

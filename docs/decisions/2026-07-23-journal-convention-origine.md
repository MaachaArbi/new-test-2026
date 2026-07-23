# Convention journal — Reprise à froid / Origine / Décisions

**Date :** 2026-07-23  
**Statut :** applicable à tout nouveau `docs/journal/*.md` et rétrofit des fichiers existants (2026-07-23).

## En-tête obligatoire (avant le contenu métier)

### 1. Reprise à froid (3-5 lignes)
Ce qui a été demandé, pourquoi, où ça en est — pour quelqu'un sans aucun
contexte qui doit décider en 30 secondes si ce journal le concerne.
Compressé depuis le contenu du journal (rétrofit) ou rédigé à chaud (nouveaux).

### 2. Origine
Le prompt d'origine collé **verbatim** depuis l'historique de chat Cursor
de la tâche précise — jamais reformulé, jamais résumé.

Si le prompt n'est pas retrouvable avec certitude :
`Origine : introuvable dans l'historique Cursor disponible`

### 3. Décisions prises
Chaque décision taguée : `(architecte)` / `(utilisateur)` /
`(Cursor — à valider)`. Ne jamais laisser une décision sans attribution.

Si l'attribution n'est pas explicite dans le prompt ou le corps :
`Décisions attribuées : non déterminable avec certitude`

## Corps du journal
Le format existant (contexte, preuves, tests, hors périmètre, qualité…)
**ne change pas** — les 3 blocs s'ajoutent en tête uniquement.

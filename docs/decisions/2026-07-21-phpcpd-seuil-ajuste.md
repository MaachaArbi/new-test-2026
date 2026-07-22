# Décision — seuil phpcpd recalibré

**Date** : 2026-07-21  
**Statut** : accepté

## Contexte

Le bootstrap avait fixé phpcpd à `--min-lines 5 --min-tokens 3`. Ce seuil
n’avait jamais été confronté à un vrai corpus d’agrégats Domain : dès
`CoreCredential` (getters triviaux d’une ligne, factories symétriques),
l’outil a produit des faux positifs et poussé à déformer le code
(variables intermédiaires, état désactivé temporaire, constantes
« fingerprint ») pour contourner la détection.

## Décision

Nouveau seuil :

```bash
php tools/phpcpd.phar --min-lines 10 --min-tokens 20 src/
```

Répercuté dans `composer.json` (`scripts.phpcpd`) et `README.md`.

## Pourquoi ces valeurs

| Critère | Ancien (5 / 3) | Nouveau (10 / 20) |
|---|---|---|
| Getters `return $this->x;` entre entités | faux positifs | ignorés |
| Vraie duplication de logique métier (≥ ~10 lignes / ≥ ~20 tokens) | détectée | toujours détectée |
| Contournements dans le Domain | nécessaires | inutiles |

La duplication de getters triviaux entre agrégats **n’est pas** une
vraie duplication à éliminer : c’est le prix normal d’entités
explicites sans base commune. C’est la détection qui était mal calibrée
depuis le bootstrap.

## Non-décision

Pas de désactivation de phpcpd. Pas d’extraction d’une classe de base
« EntityWithId » pour faire taire l’outil.

## Conséquence

Le Domain (`CoreCredential`, Party) revient à des getters / factories
directs. Les contournements introduits pour le seuil 5/3 sont retirés.

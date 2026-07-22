# Décision — Traduction des erreurs en YAML (domain Symfony « errors »)

**Date :** 2026-07-21  
**Statut :** acceptée

## Contexte

`DomainException::errorCode()` fournit un code stable machine-readable. Il faut
exposer un message lisible en langue (en / fr / ar, aligné sur `ref_language`)
sans encore de listener HTTP (aucun Controller).

## Décision

Stocker les libellés dans des fichiers YAML versionnés sous `translations/`
(`errors.{locale}.yaml`, domain Symfony `errors`), résolus via le Translator
natif Symfony — **pas** via une table BDD.

## Pourquoi YAML plutôt qu’une table BDD

1. **Résolution sans requête SQL** — message système disponible immédiatement
   (logs, futurs payloads API) sans I/O base.
2. **Fichiers versionnés et revus comme du code** — toute modification passe
   par MR / revue, pas par édition runtime.
3. **Messages système, pas données métier** — ce ne sont pas des contenus
   éditables par l’utilisateur final (contrairement aux libellés métier
   éventuellement liés à `ref_language` côté BDD).

## Conséquences

- Locales configurées : `en` (pivot / fallback), `fr`, `ar` uniquement.
- Domain `errors` distinct de `messages` (UI) pour éviter tout mélange.
- Le listener HTTP qui consommera ce mécanisme reste prévu pour la vague
  Infrastructure / Controllers.

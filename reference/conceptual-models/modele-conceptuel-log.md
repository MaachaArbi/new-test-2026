# Modèle Conceptuel — Module Log (log_) — transverse

**Statut** : Figé (V1.0) — 20 juillet 2026, testé sur PostgreSQL réel (sandbox)
**Généralise** : `booking_log` (Booking, `sujets-reportes.md` §19), après 3 signaux réels convergents : Booking, futur Provider Integration (logs API IN/OUT), `pricing_rule_log` (Pricing, resté local — pattern similaire mais non fusionné, voir note ci-dessous)
**Convention de nommage** : préfixe `log_`, transverse — volontairement **pas** rattaché à `core_`/`party_`/`booking_`
**Dépend de** : `party_` (acteur)
**Livrables associés** : `schema-log-v1.sql`, `diff-booking-log-generalization.diff`

## Pourquoi ce module, et pourquoi maintenant

`booking_log` avait été conçu volontairement local à Booking (`sujets-reportes.md` §19, décision d'origine) — la règle actée était : extraire un module transverse seulement quand **2 cas d'usage réels** le justifient, pas par anticipation. Ce seuil est atteint : le futur module Provider Integration exprime le même besoin (centraliser les appels API IN/OUT), et `pricing_rule_log` (Pricing, déjà figé) reproduit indépendamment le même patron structurel (événement/JSONB avant-après/acteur). Trois occurrences convergentes du même besoin justifient l'extraction, cohérent avec le principe déjà établi pour éviter la sur-généralisation prématurée.

**`pricing_rule_log` n'est pas fusionné ici.** Il reste un journal local au module Pricing (audit trail de règles de marge, append-only, `rule_id` en FK applicative car une règle peut être physiquement supprimée). Le fusionner aurait nécessité de rouvrir Pricing sans bénéfice clair — à réévaluer plus tard si un vrai besoin de requêtage transverse (across Pricing et Log) apparaît.

## Deux tables, deux finalités, jamais confondues

| | `log_activity` | `log_audit` |
|---|---|---|
| **Répond à** | "Qui a fait quoi, quand, pourquoi" | "Quelle ligne a changé, avant/après" |
| **Lecteur cible** | Équipe, parfois client (voucher, historique de résa) | Investigation technique, conformité, sécurité |
| **Alimentation** | Explicite, par le code applicatif Symfony | Automatique, par `log_audit_trigger()` posé sur les tables critiques |
| **Contenu** | Texte lisible + métadonnées structurées ciblées | Snapshot JSONB complet avant/après de la ligne |
| **Origine** | `booking_log` (généralisé tel quel) | ADR-006 (décidée il y a 6 mois, jamais construite avant cette session) |

Les deux partagent `entity_type`/`entity_id`/`created_at` pour permettre une lecture croisée ("tout ce qui concerne la réservation #453722, activité et audit confondus"), sans être la même table — un `UPDATE` anodin (ex: `updated_at` technique) peut légitimement finir dans `log_audit` sans jamais apparaître dans `log_activity`, et inversement un événement métier composite ("chambres disponibles et solde suffisant") n'a pas de traduction ligne-par-ligne en `log_audit`.

## Entités

| Table | Rôle |
|---|---|
| `log_entity_type` | Référentiel des entités journalisées (table, pas ENUM). Porte aussi la rétention configurable, distincte par nature de log |
| `log_activity` | Journal métier, remplace `booking_log` à l'identique dans son comportement |
| `log_audit` | Traçabilité technique ligne par ligne, alimentée exclusivement par trigger |
| `log_audit_trigger()` | Fonction générique réutilisable, un seul paramètre (`entity_type_code`) à la pose sur chaque table critique |

## Décisions clés et justification

1. **`entity_type` est une table de référence**, pas un `VARCHAR` libre — cohérent avec le principe anti-ENUM du projet, extensible sans migration à chaque nouveau module qui journalise.
2. **`status_code_snapshot` (colonne dédiée sur `booking_log`) déplacé dans `metadata` JSONB** — c'était un besoin propre à Booking (reconstruire l'historique de statut), pas un concept transverse à toutes les entités futures. Comportement préservé à l'identique côté applicatif (`{"status_code_snapshot": "confirmed"}`), juste déplacé.
3. **Index composite `(entity_type, entity_id, created_at DESC)`** sur les deux tables — c'est le pattern de lecture principal ("l'historique de CETTE entité, du plus récent au plus ancien").
4. **`entity_id` est une FK applicative**, jamais une FK SQL — un même `entity_id` référence des tables cibles différentes selon `entity_type` (impossible à exprimer en FK SQL polymorphe), et certaines cibles (`booking`) sont elles-mêmes partitionnées.
5. **`log_audit_trigger()` est une exception documentée à ADR-002** (logique métier hors DB) : le trigger ne fait qu'une capture mécanique avant/après, aucune règle métier, aucun calcul — ADR-006 autorise explicitement ce cas précis.
6. **Découverte en testant (sandbox)** : sur une table partitionnée (`booking`), `TG_TABLE_NAME` retourne le nom de la **partition physique** (`booking_y2026m07`), pas le nom logique `booking`. Toute requête de reporting sur `log_audit.table_name` doit normaliser ce préfixe côté Application plutôt que de s'attendre à une valeur unique.
7. **`core_auth_attempt` reste séparée, volontairement** — isole le bruit d'un brute-force avec sa propre rétention courte déjà gérée localement (module Permissions/Franchises/Config, figé). Ne jamais fusionner dans `log_activity`/`log_audit`.
8. **Rétention configurable par `entity_type`**, distincte entre activité et audit (`log_entity_type.activity_retention_days`/`audit_retention_days`, tous deux nullable = conservation indéfinie) — mécanisme générique posé ici plutôt que dupliqué par futur module. **Le job de purge périodique lui-même n'est pas construit** (pas de CRON en base, cohérent ADR-002) — seulement la configuration ; à construire côté Application quand le volume le justifiera.

## Migration de `booking_log`

`booking_log` est supprimée (`diff-booking-log-generalization.diff`). Toute ligne existante devient conceptuellement une ligne `log_activity` avec `entity_type='booking'`, `entity_id=booking_id`, et `status_code_snapshot` déplacé dans `metadata`. Aucune donnée réelle en production à ce jour — pas de script de migration de données nécessaire, seulement le changement de structure.

## Hors périmètre (reporté)

- **Job de purge automatique** selon la rétention configurée — sujet Application, pas DB.
- **Fusion de `pricing_rule_log`** — à réévaluer si un vrai besoin de requêtage transverse apparaît.
- **Écriture explicite de `log_audit` par le code applicatif** — volontairement exclue : `log_audit` n'est alimentée QUE par le trigger, jamais par un `INSERT` applicatif direct (sinon la distinction avec `log_activity` s'érode).

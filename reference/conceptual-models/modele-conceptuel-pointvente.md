# Modèle conceptuel — Point de vente

**Version** : 1.0 — figée le 17/07/2026
**Statut** : ✅ Conception validée sur PostgreSQL 16 réel. Aucune donnée réelle de points de vente disponible à ce jour (legacy `ost_pointvente` non fourni) — à reconfronter dès qu'un export sera transmis.

---

## Objet

Un point de vente est un site physique secondaire (généralement une ville différente du siège) rattaché à un `party_account_office` (bureau), sans identité fiscale propre.

## Décision structurante : pas un `party_account`

Question posée explicitement en session (17/07) : pourquoi ne pas simplement utiliser `party_account` avec un attribut, puisqu'un point de vente est "une ligne comme le bureau principal" ?

**Réponse actée : non.** `party_account` est la clé universelle de tout ce qui est transactionnel/financier dans le système (`party_account_office_relation`, `booking.customer_account_id`/`supplier_account_id`, le grand livre Règlements, `cash_session.holder_account_id`...). Un point de vente n'est **acteur de rien** : il n'achète, ne vend, ne doit rien, n'a pas d'identité fiscale, et aucune règle métier ne contraint son usage. Le faire entrer dans `party_account` ouvrirait une porte sans raison métier : rien n'empêcherait techniquement de l'utiliser comme `customer_account_id` sur une résa ou de lui associer une fonction/permission.

`party_account` reste donc strictement "acteur économique". Le point de vente est une **table de référence légère**, dimension d'affichage et de reporting, sans aucun rôle transactionnel.

## Usages réels confirmés (et un seul usage écarté)

Exposés en session le 17/07 :

1. **Affichage client** : liste des points de vente proposée sur le site web lorsque le client choisit de payer à l'agence. Aucune contrainte métier associée — le client peut ensuite se rendre dans un point de vente différent de celui choisi, sans conséquence.
2. **Dimension de reporting** : permettre de regrouper les réservations par site pour mesurer un "rendement" par point de vente.

**Usage écarté explicitement** : rattachement d'un client précis à un point de vente. Confirmé inutile (17/07) — le point de vente ne concerne que la réservation/le paiement, jamais le tiers lui-même.

**Enrichissements évalués et écartés** : horaires d'ouverture, géolocalisation lat/lng. Proposés en session comme pistes de valeur ajoutée côté client, explicitement refusés faute de besoin fonctionnel réel identifié — pas de valeur artificielle ajoutée pour "faire module".

## Le sujet du "rendement par point de vente" — hors périmètre, documenté séparément

Le point 2 ci-dessus (dimension de reporting) a ouvert un sujet plus large : un rapport de performance agents/points de vente pour le calcul de primes, jamais résolu côté legacy. Exposé en détail par l'utilisateur (17/07) : le traitement d'une résa se décompose en plusieurs opérations (contact client, contact fournisseur pour confirmation sur demande, encaissement — potentiellement partiel et multi-agent —, annulation/remboursement), chacune pouvant impliquer plusieurs agents. La question "qui touche la prime, ou comment la répartir par tâche" n'est **pas tranchée côté politique métier**.

Ce sujet est volontairement **hors périmètre de ce module** : il touche les agents (utilisateurs), donc relève du module 7, et dépend d'une décision métier non prise. Voir `sujets-reportes.md` pour le détail complet, y compris le trou identifié dans `booking_payment` (aucune attribution d'agent/caissier encaisseur aujourd'hui).

**Conséquence pour ce module** : aucune colonne supplémentaire n'est nécessaire sur `pointvente` pour ce futur rapport — un simple `GROUP BY pointvente.id` sur les réservations suffira, une fois la politique de prime tranchée et le module 7 en place.

## Structure

Table unique `pointvente` (pas de séparation "vente"/"paiement" au niveau structurel — voir ci-dessous).

| Colonne | Rôle |
|---|---|
| `id` / `public_id` | Identifiants (pattern ADR-018) |
| `office_account_id` | FK vers `party_account(id)` — doit porter `party_account_office` (règle applicative) |
| `name` | Nom d'affichage du site |
| `address_line1/2`, `city`, `postal_code`, `country_id` | Fiche adresse propre (confirmé distincte de celle du bureau) |
| `phone`, `contact_email` | Contact propre |
| `is_active` | Désactivation simple, jamais de suppression physique |
| `created_at`/`updated_at`/`created_by`/`updated_by` | Audit standard |

**Cardinalité point de vente ↔ bureau** : N par bureau, sans limite (confirmé "très variable selon le bureau"). Un bureau peut n'avoir aucun point de vente.

**Pas de `code` court** : aucun besoin réel confirmé aujourd'hui (numérotation facturation, recherche interne, intégration externe — aucun de ces trois cas n'est avéré). Colonne nullable facile à ajouter plus tard sans migration douloureuse.

**Désactivation** : `is_active` booléen simple, jamais de `deleted_at` — une résa passée doit toujours pouvoir résoudre son point de vente, même fermé depuis.

## Lien avec Booking — deux rôles, une seule table

Confirmé sur données réelles (hôtel ET maritime) : une réservation porte deux FK distinctes vers le concept "point de vente" — un point de vente de **vente** (où la résa a été prise) et un point de vente de **paiement** (où l'argent a été physiquement encaissé), pouvant diverger.

Ce module ne modifie **pas** Booking. La structure prévue pour la session dédiée Booking :

```sql
-- À ajouter côté Booking, session ultérieure, PAS ici :
ALTER TABLE booking ADD COLUMN pointvente_id BIGINT REFERENCES pointvente(id);
ALTER TABLE booking ADD COLUMN pointvente_paiement_id BIGINT REFERENCES pointvente(id);
```

Les deux colonnes sont nullables et indépendantes.

## Lien avec Cash Management — vérifié, aucun recouvrement

`cash_session` est scopée par `holder_account_id` (le caissier), avec `office_account_id` purement informatif. Aucun lien structurel au point de vente aujourd'hui. Le rôle "paiement" du point de vente reste une métadonnée de la résa, indépendante de la mécanique caisse (session/mouvement/validation). Vérifié explicitement le 17/07/2026 — pas d'ajustement nécessaire côté `schema-cash-management-v1.sql`.

## Tests réalisés (sandbox PostgreSQL 16)

- Chargement du schéma sans erreur, dépendances `schema-ref-common.sql` + `schema-party-account-v1.sql`.
- Bootstrap d'un bureau (`party_account` nature `organization` + `party_account_office`).
- Insertion de deux points de vente sur le même bureau → cardinalité N confirmée.
- `UPDATE ... is_active = false` → trigger `updated_at` déclenché correctement, `created_at` inchangé.
- Index partiel `idx_pointvente_office` (`WHERE is_active = true`) vérifié via `pg_indexes`.

## Points restés ouverts

- Aucune donnée réelle de points de vente disponible à ce jour — structure à reconfronter dès qu'un export legacy (`ost_pointvente`) sera fourni.
- Ajustement FK côté Booking (`pointvente_id`, `pointvente_paiement_id`) à faire dans une session dédiée.
- Rapport de rendement agents/points de vente — voir `sujets-reportes.md`, dépend du module 7 et d'une politique de prime non tranchée.
- Gap identifié dans `booking_payment` (pas d'attribution agent/caissier encaisseur) — à signaler au chat pilote Booking.

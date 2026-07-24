# Métier — Référentiels (hébergement, géographie, communs)

**Pas encore balayé.** Compilé depuis `modele-conceptuel-ref-static.md`.

---

## Ce que le module décrit

Les données de référence partagées : pays, villes, régions, hébergements, catégories,
équipements, langues, devises.

## Comment le métier fonctionne réellement

### La plupart viennent d'ailleurs

Ces données sont produites par **OctaSoft Static Data**, un produit séparé de l'éditeur,
comparable aux référentiels du marché mais plus large. Le système en consomme une copie locale
et garde un **code de rapprochement** pour rester aligné.

Trois familles de référentiels :

- **fermés** — entièrement fournis, aucun ajout local possible ;
- **ouverts** — fournis, mais le client peut ajouter ses propres entrées ;
- **purement locaux** — propres au système, jamais fournis.

### Louer une chambre ou louer le bien entier

Distinction structurelle introduite dès le cadrage : un hôtel se vend **par chambre**, une
villa ou un appartement se vend **en entier**. Le legacy contournait ce manque en déclarant
une villa comme une chambre unique de type « S+1 » ou « S+2 ».

Ce mode est déterminé par la **catégorie du bien** — un hôtel implique la vente par chambre,
une villa la vente entière — plutôt que saisi manuellement, ce qui éviterait une villa
marquée par erreur comme se vendant à la chambre.

### Les adresses des tiers ne sont pas structurées

Le référentiel géographique sert à localiser les **hébergements**, pour la recherche. Les
adresses des clients et fournisseurs restent du texte libre : personne ne cherche un client
par sa ville.

---

## À compléter

- **La fréquence de mise à jour** depuis OctaSoft : quotidienne, à la demande ? Que se passe-t-il
  si un hébergement disparaît du référentiel alors que des réservations le référencent ?
- **Les entités absentes d'OctaSoft** (§33) : aéroports, compagnies aériennes et maritimes ne
  sont pas encore fournis. Comment fait-on en attendant ?
- **Le contenu riche** (§32) : descriptions, photos, équipements — reportés.

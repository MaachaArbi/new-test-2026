# Métier — Journal d'activité et audit

**Pas encore balayé.** Compilé depuis `modele-conceptuel-log.md`.

---

## Ce que le module décrit

Deux journaux de nature différente, souvent confondus :

**Le journal d'activité** raconte la vie métier d'un objet, dans un langage lisible par un
agent : « réservation créée par ENERGIE VOYAGES suite à disponibilité chambres », « email
envoyé au fournisseur », « paiement reçu ». C'est ce que voit l'utilisateur à l'écran, et
potentiellement ce qu'il montre à un client.

**L'audit technique** enregistre les modifications ligne à ligne — valeur avant, valeur après —
sur les tables sensibles. Il sert aux enquêtes de sécurité et aux litiges, pas à l'affichage.

Même forme, deux finalités : ne jamais les confondre.

## Comment le métier fonctionne réellement

### Un journal unique, pas un journal par module

Le legacy avait des tables séparées par type d'événement (notifications hôtel, annulations…).
L'écran montrait pourtant un **flux unique**. Le journal est donc unifié : un seul fil
d'événements, quel que soit l'objet concerné.

### Une durée de conservation par nature d'objet

Toutes les traces n'ont pas la même valeur dans le temps. La configuration est par type
d'objet : l'audit d'une réservation se garde un an, d'autres traces indéfiniment.

⚠️ La configuration existe, mais **le nettoyage périodique n'est pas construit** — il devra
être fait par l'application quand le volume le justifiera.

### Les tentatives de connexion à part

Elles ont leur propre table, volontairement séparée : une attaque par force brute génère un
bruit énorme qui ne doit pas polluer le journal métier, et sa durée de conservation est
beaucoup plus courte.

---

## À compléter

- **Que doit voir un client** dans le journal d'une réservation, par rapport à un agent ?
- **Quels événements** doivent réellement être tracés ? La liste actuelle vient du legacy.
- **Les journaux d'API** (§44) : leur volume est jugé incompatible avec cette base ; un
  stockage séparé est recommandé mais non conçu.

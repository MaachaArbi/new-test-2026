# Métier — Permissions, franchises et configuration

**Pas encore balayé.** Compilé depuis `modele-conceptuel-permissions-franchise-config.md`.

---

## Ce que le module décrit

Qui a le droit de faire quoi, et comment le système se configure.

## Comment le métier fonctionne réellement

### Tout est ouvert, sauf ce qu'on ferme explicitement

Le principe est inversé par rapport à l'habitude : une action **n'existe pas** comme
permission tant que personne ne l'a définie, et elle est donc autorisée. **Dès qu'une
permission est créée en base, l'action devient fermée** jusqu'à ce qu'on l'accorde.

Conséquence opérationnelle importante : pour une fonctionnalité sensible, la définition de la
permission doit être déployée **en même temps** que le code — sinon l'action reste ouverte à
tous entre les deux.

### Les droits ne filtrent pas les données

Le système de permissions gère l'accès aux **actions** : afficher un écran, cliquer un bouton.
Il ne dit pas *quelles lignes* un utilisateur peut voir. Restreindre un utilisateur à son
propre bureau ou à son portefeuille relève d'un autre mécanisme.

### Les franchises

Une franchise est un acteur économique à part entière, avec ses propres utilisateurs qu'elle
administre elle-même. Certaines permissions sensibles sont marquées **non déléguables** : un
administrateur de franchise ne peut jamais les accorder, quel que soit son propre niveau de
droits. C'est un plafond universel.

Par sécurité, une permission est **non déléguable par défaut** — un oubli bloque une action
légitime, ce qui se voit immédiatement, plutôt que d'ouvrir silencieusement une élévation de
privilèges.

### La configuration de l'installation

Un serveur par client, donc une seule ligne de configuration, avec des colonnes explicites
plutôt qu'un système clé/valeur générique. Ajouter un paramètre est une migration additive.

Premier paramètre : le **nom affiché dans l'application d'authentification** lors de
l'activation de la double authentification. Il est vide à l'installation et le client doit le
renseigner — un nom en dur exposerait le nom d'un autre client.

---

## À compléter

- **Quels rôles existent réellement** dans une agence : qui fait quoi au quotidien ?
- **Les droits de la fiche utilisateur legacy** : « ne pas autoriser à créer des réservations »,
  « masquer l'achat et la commission », « crée des réservations en attente de validation d'un
  responsable » — comment s'articulent-ils avec le système de permissions ?
- **La configuration des emails sortants** (§42) : qui reçoit quoi, dans quelles circonstances ?
- **La personnalisation des documents** (§41) : quel niveau de liberté laisse-t-on au client ?

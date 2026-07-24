# Métier — Party (tiers)

**Balayé le 24/07/2026** — confronté aux écrans legacy : fiche client, fiche contact,
fiche fournisseur, fiche agence, fiche utilisateur, écran « autre config ».

---

## Ce que le module décrit

Toute personne ou organisation avec qui l'agence est en relation : clients particuliers,
agences partenaires, entreprises, amicales, fournisseurs, et le personnel interne.

**Une seule table pivot pour tous.** Un compte est soit une personne, soit une organisation.
Ce qu'il *est* pour l'agence (client, fournisseur, franchise…) n'est pas une propriété du
compte mais un **rôle** qu'on lui attribue — et il peut en cumuler plusieurs.

C'est le point le plus important à comprendre : **une même agence partenaire peut être à la
fois cliente et fournisseur.** On lui vend des séjours, on lui achète des prestations. Elle a
alors deux grands livres distincts, sans compensation automatique entre eux.

## Comment le métier fonctionne réellement

### Les agences en réseau

Une agence maître peut ouvrir des **sous-agences** (les « affiliés » du legacy). La
particularité : **c'est l'agence maître qui reçoit la facture** et porte le risque commercial.
Les affiliés réservent, la maison mère paie.

### Les amicales

Une amicale (comité d'entreprise, association) achète pour ses adhérents. Le cas courant :
l'amicale paie une part, l'employé paie le reste. Chacun a alors sa propre dette dans le grand
livre — l'agence peut relancer l'un sans relancer l'autre.

Dans le legacy, l'amicale était rattachée à la fiche du client. C'est faux : un même employé
peut réserver une fois via son amicale, une fois à titre personnel. Le rattachement se fait
donc **au niveau de la réservation**, pas du compte.

### Le découvert autorisé

Une agence partenaire ne paie pas comptant. On lui accorde un **plafond** — une autorisation
de découvert. Un plafond de 20 000 signifie qu'elle peut aller jusqu'à 20 000 au-delà de ce
qu'elle a réellement versé.

Ponctuellement (haute saison, gros groupe), elle demande une **rallonge temporaire** avec une
date d'expiration. Elle s'ajoute au plafond puis disparaît d'elle-même, sans que personne ait
à la retirer.

> **capacité = solde du grand livre + plafond + rallonges valides**

Le paiement **libère de la capacité** : c'est un découvert, pas un quota de consommation
cumulée. Un client qui réserve puis paie retrouve sa marge de manœuvre.

Le plafond est **par devise et jamais converti** — un plafond en euros se compare à un solde
en euros.

**Il peut se décliner par service.** Une agence peut avoir 10 000 de découvert sur
l'hébergement et 5 000 sur l'aérien. Attention à ce qui est ventilé et ce qui ne l'est pas :

- le **solde réel reste global et partagé** — jamais ventilé par service ;
- seul le **découvert accordé** se décline.

Ainsi, un client avec 30 000 de solde, 10 000 sur l'hébergement et 5 000 sur l'aérien dispose
de 40 000 sur l'hébergement et 35 000 sur l'aérien. S'il consomme et que son solde descend à
−5 000, il lui reste 5 000 sur l'hébergement et plus rien sur l'aérien.

Le mécanisme **se régule tout seul** : inutile de savoir quel service a creusé le découvert,
le solde global descend et chaque service perd de la capacité. Le service au plafond le plus
faible se ferme en premier.

Conséquence utile : **le risque total est borné par le plus grand plafond**, jamais par leur
somme. 10 000 sur l'hébergement et 5 000 sur l'aérien, c'est 10 000 d'exposition maximale.

Quand une ligne générale et une ligne par service coexistent, **la plus précise l'emporte** —
elles ne s'additionnent pas. Un service sans ligne dédiée utilise la ligne générale.

### Les politiques commerciales

Certains clients ont un traitement particulier, décidé commercialement :

- **Toujours en demande** — aucune de ses réservations n'est confirmée automatiquement, même
  si son solde le permet. Un agent doit valider.
- **Bloqué si solde insuffisant** — sans cette règle, un client sans solde peut quand même
  réserver « en demande ». Avec elle, il ne peut plus rien créer du tout.

Quand les deux s'appliquent, le blocage l'emporte : s'il n'y a pas de réservation, la mise en
demande n'a pas lieu d'être.

### La fiscalité du tiers

Un client peut être **exonéré de TVA** ou **de timbre fiscal**, indépendamment l'un de
l'autre. L'exonération repose généralement sur une **attestation administrative datée** qui
expire et se renouvelle ; elle peut aussi être permanente.

Elle couvre toute l'activité — pas de distinction par type de prestation.

### Les responsables de portefeuille

Chaque client est suivi par des personnes de l'équipe : un ou plusieurs **commerciaux**, un ou
plusieurs **chargés de recouvrement**. L'affectation est globale au client (pas par bureau) et
**historisée** — quand un commercial change de portefeuille, on ferme la période au lieu de
l'écraser.

Cet historique conditionne le futur module de rendement et primes : sans lui, impossible de
savoir rétroactivement qui suivait quoi.

### Les bureaux

Un « bureau » est une **entité légale par pays**, avec son matricule fiscal et sa devise par
défaut. Un tiers est rattaché aux bureaux avec lesquels il travaille.

Environ 20 % des clients travaillent avec plusieurs bureaux — le plus souvent Algérie et
Tunisie ; l'un d'eux exerce dans six pays. Le rattachement dit donc quelle entité légale
traite avec qui, ce qui compte pour la facturation et le régime fiscal.

Il n'y a **aucune approbation préalable** : un agent crée un client et réserve dans la foulée.

### Les groupes de comptes

Les clients se regroupent pour plusieurs usages distincts : négocier un contrat commun,
appliquer une même marge, organiser le recouvrement, éditer des rapports.

Le legacy n'avait **qu'une seule catégorie de groupe**, si bien que tout se mélangeait : un
agent de recouvrement créant ses groupes les rendait visibles aux agents de tarification, avec
le risque d'affecter une marge au mauvais groupe par simple ressemblance de nom. Les
utilisateurs avaient inventé leur propre parade — préfixer les noms
(« Contracting_AgencesAlgerienne », « Recouvrement_Sud »).

Les groupes sont désormais **typés** — contracting, tarification, recouvrement, reporting —
ce qui règle le problème à la source : chaque métier ne voit que ses propres groupes. Un
compte peut appartenir à plusieurs groupes de types différents.

### Les contacts

Une organisation cliente a des interlocuteurs — directeur, comptable, assistante. Chacun est
un compte à part entière, relié à l'organisation par une fonction. Ils n'ont ni solde, ni
réservation : c'est un carnet d'adresses.

*(Un cache Redis est envisagé plus tard pour alléger la base sur ce type de données.)*

---

## Problèmes du legacy résolus

| Problème legacy | Cause | Résolution |
|---|---|---|
| Amicale figée sur la fiche client | Rattachement au mauvais niveau | Rattachement à la réservation (`booking_payer_split`) |
| ~25 paramètres dans un JSON fourre-tout (`autre_config`) | Ajouts successifs jamais restructurés | Chaque paramètre rejoint son module propriétaire, avec un vrai typage |
| Identifiants API de franchise dans ce JSON | Aucun endroit prévu | Provider Integration |
| Devise unique par client + case « Forcer » | Contrainte artificielle nécessitant une échappatoire | Grand livre multi-devise natif : la contrainte disparaît, l'exception aussi |
| Assujettissement TVA sur les seules organisations | Colonne mal placée | Table d'exonérations ouverte à tout tiers |

## Ce qui a été délibérément écarté

RIB et coordonnées bancaires des tiers · gouvernorat · source de contact · titre de civilité ·
lieu et date de naissance · fax · distinction fournisseur local/étranger (déductible du pays) ·
type d'activité fournisseur · restriction des produits visibles par agence · commentaire libre.

Motif commun : aucun usage réel identifié, ou information déductible d'ailleurs.

---

## À compléter

*(Les trois questions ouvertes à la rédaction initiale ont été tranchées au balayage du
24/07 : l'approbation de rattachement est retirée, un affilié ne change jamais d'agence
maître — donc pas d'historisation nécessaire — et les types de groupe sont désormais définis.)*

Rien d'identifié à ce jour. À enrichir si un usage réel révèle un manque.

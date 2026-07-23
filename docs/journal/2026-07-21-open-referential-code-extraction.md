## Reprise à froid

Journal — Extraction OpenReferentialCode.
Le 3ᵉ VO string sur référentiel ouvert (`PartyAccountGroupTypeCode`) a confirmé le déclencheur de la décision `docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md`.
Date : 2026-07-21
Le 3ᵉ VO string sur référentiel ouvert (`PartyAccountGroupTypeCode`) a confirmé

## Origine

```
# TASK — Extraction OpenReferentialCode (clôture décision VO string ouvert)

## Contexte
3ème cas confirmé (PartyRoleCode, PartyFunctionCode, PartyAccountGroupTypeCode)
— cf. docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md, qui
désignait explicitement ce déclencheur. Décision : extraire maintenant.

## 1. Classe abstraite commune
src/Shared/Domain/ValueObject/OpenReferentialCode.php
- abstract class, readonly string $value (ou pattern équivalent)
- fromString() générique : trim, rejet vide, rejet > maxLength() — maxLength()
  méthode abstraite protégée que chaque sous-classe définit (30 pour les 3
  cas actuels, mais pas figé en dur dans la classe mère)
- toString()
- Lever l'exception via une méthode abstraite protégée emptyException()/
  tooLongException() que chaque sous-classe implémente en retournant SA propre
  exception (garder les 3 DomainException existantes telles quelles, avec
  leurs errorCode() propres — ne rien changer côté traduction/catalogue)

## 2. Faire hériter les 3 VO existants
PartyRoleCode, PartyFunctionCode, PartyAccountGroupTypeCode héritent de
OpenReferentialCode. Réduire chacun à : la déclaration de classe, maxLength()
= 30, et les deux méthodes qui retournent leurs exceptions existantes (aucun
changement de errorCode(), aucun changement de translations/errors.*.yaml,
aucun changement de comportement observable — pure réduction de duplication).

## 3. Vérifier zéro régression
Tous les tests déjà existants sur les 3 VO doivent passer sans modification
(même comportement exact, juste la logique interne factorisée). Si un test
doit changer, ARRÊTE-TOI et signale — ce prompt ne doit pas changer de
comportement, seulement la structure interne.

## Documentation
- docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md : ajouter une
  section "Mise à jour 2026-07-2X" indiquant que le 3ème cas a motivé
  l'extraction, avec un lien vers cette clôture (ne pas réécrire le fichier
  original, l'annoter)
- docs/journal/2026-07-2X-open-referential-code-extraction.md
- docs/STATUS.md et todo.md : décision VO retirée de la liste des sujets
  ouverts, marquée résolue

Relance phpstan/deptrac/phpcpd/phpunit — le compte de tests/assertions doit
rester IDENTIQUE à avant (99/520), aucune régression, aucun test perdu ou
ajouté sauf strictement nécessaire pour couvrir la classe abstraite elle-même
si tu juges utile d'ajouter un test dédié dessus.

Colle le contenu intégral de OpenReferentialCode.php et des 3 VO modifiés,
plus les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — Extraction OpenReferentialCode

Date : 2026-07-21

## Contexte

Le 3ᵉ VO string sur référentiel ouvert (`PartyAccountGroupTypeCode`) a confirmé
le déclencheur de la décision
`docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md`.

## Changement

- Création de `App\Shared\Domain\ValueObject\OpenReferentialCode` (trim, vide,
  longueur max via `maxLength()`, exceptions via hooks de sous-classe).
- `PartyRoleCode`, `PartyFunctionCode`, `PartyAccountGroupTypeCode` héritent
  et ne gardent que maxLength = 30 + leurs exceptions Domain existantes.

## Non-changé

- Aucun errorCode, aucune traduction, aucun test VO modifié.
- Comportement observable identique (réduction de duplication uniquement).

## Qualité

phpstan / deptrac / phpcpd / phpunit — compte de tests/assertions inchangé.

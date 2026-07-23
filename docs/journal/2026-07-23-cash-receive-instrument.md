## Reprise à froid

Encaissement d’un instrument Règlements dans une session de caisse ouverte
(`cash_receive_instrument`). Migration `cash_movement_type` + `cash_movement`
+ guard + fonction SQL. Cinq validations Application avant l’appel SQL.
Pas d’HTTP, pas de décaissement.

## Origine

```
# TASK — Cash Management : encaisser un instrument en caisse (cash_receive_instrument)

## Lecture obligatoire
1. reference/schemas/schema-cash-management-v1.sql (à jour, vérifié) :
   - lignes 119-150 : cash_movement_type (table + seed, dont le code
     'encaissement_instrument')
   - lignes 199-231 : cash_movement (table + index, dont
     uq_cash_movement_instrument_per_session — commentaire juste au-dessus
     explique le scope volontaire à la session, jamais cross-session)
   - lignes 233-258 : cash_movement_guard() — ATTENTION, ne bloque QUE
     'validated', PAS 'closed' (lire le commentaire en entier avant de
     coder, ce point a été vérifié en profondeur)
   - lignes 384-400 : cash_receive_instrument()
2. src/Modules/Reglements/Application/PostReglementTransfer/PostReglementTransferHandler.php
   — pattern DBAL direct
3. src/Modules/CashManagement/Application/CloseCashSession/CloseCashSessionHandler.php
   — pattern de traduction d'un RAISE EXCEPTION SQL en exception métier
4. src/Modules/Reglements/Domain/Entity/ReglementInstrument.php — expose
   statusCode (ReglementInstrumentStatus), paymentMethodId, currencyCode,
   amountMinor
5. src/Modules/Reglements/Domain/Repository/ReglementInstrumentRepositoryInterface.php
   et CashPaymentMethodRoutingRepositoryInterface.php (déjà existants,
   précédent déjà établi : CashManagement dépend directement des
   interfaces Reglements pour ses lectures cross-module)

## Portée volontairement réduite
UNIQUEMENT recevoir un instrument dans une session ouverte. AUCUN
décaissement, AUCUNE des autres fonctions de posting, AUCUN endpoint HTTP.

## Migration
cash_movement_type et cash_movement ne sont pas encore migrés (vérifié :
absents de migrations/). Nouvelle migration slice reprenant EXACTEMENT le
SQL de reference/schemas/schema-cash-management-v1.sql lignes 119-258
(cash_movement_type, cash_movement, cash_movement_guard + trigger) — ne
pas retaper depuis ce prompt, copier depuis le fichier de référence
maintenant à jour.

## POINTS D'ATTENTION CRITIQUES — 5 validations, ZÉRO TOLÉRANCE
Aucune n'est enforced par la fonction SQL elle-même (sauf la 4, en base).
TOUTES vérifiées côté Application, dans cet ordre, AVANT l'appel SQL :

1. Session strictement `open` — décision utilisateur du 23/07. Le trigger
   cash_movement_guard n'interdit QUE 'validated', PAS 'closed' (réservé
   au futur mécanisme caissier central). Ne pas compter dessus : vérifier
   explicitement status === 'open', sinon exception dédiée, quel que soit
   l'état réel (closed OU validated).
2. Instrument existe et est actif (statusCode = Active) — décision
   utilisateur du 23/07. Introuvable → exception dédiée. Trouvé mais
   inactif → exception dédiée distincte.
3. Mode de règlement routé 'caisse' — décision utilisateur du 23/07.
   Absence de routing configuré = traité comme routing ≠ 'caisse' (rejeté).
4. Pas de doublon même-session — garde-fou STRUCTUREL déjà en base
   (uq_cash_movement_instrument_per_session). Capturer la violation
   (23505, nom de contrainte vérifié précisément — même rigueur que
   uq_cash_session_one_open_per_holder) et traduire. NE PAS dupliquer
   cette vérification en Application.
5. Défensif — le RAISE EXCEPTION "Instrument % introuvable" de la fonction
   SQL elle-même : normalement inatteignable puisque le point 2 valide
   déjà avant l'appel. Documente ce fait explicitement dans le journal.

## Application
ReceiveCashInstrument/{Command,Handler}
- sessionId (int), instrumentId (int), receivedBy (?int)
- Si receivedBy fourni : valider son existence (party_account), même
  pattern DBAL que les FK de OpenCashSession/CloseCashSession
- Ordre des 5 validations ci-dessus, dans cet ordre précis
- Appelle cash_receive_instrument() via DBAL, retourne l'id du mouvement créé

## Repository
Aucun nouveau — réutilise CashSessionRepositoryInterface,
ReglementInstrumentRepositoryInterface, CashPaymentMethodRoutingRepositoryInterface.

## PAS DE MAPPING DOCTRINE XML pour CashMovement
Même précédent que CashSession/ReglementTransfer.

## Traductions d'erreurs — OBLIGATOIRE
Pour chaque nouvelle exception (session non ouverte, instrument
introuvable, instrument non actif, routing non caisse, doublon
même-session) : errorCode() dans translations/errors.{fr,en,ar}.yaml +
entrée dans ErrorsTranslationCatalogueTest.php. Vérifier que ce test passe.

## Tests (PostgreSQL réel)
- Encaissement valide → mouvement créé, montant/devise corrects
- Session closed → exception dédiée (PAS celle du trigger)
- Session validated → exception dédiée
- Instrument introuvable → exception dédiée
- Instrument status='returned' ou 'cancelled' → exception dédiée
- Routing absent pour le mode de paiement → exception dédiée
- Routing présent mais ≠ 'caisse' → exception dédiée
- Même instrument, même session, 2x → 2e tentative rejetée (contrainte traduite)
- Même instrument, 2 sessions DIFFÉRENTES → les DEUX réussissent
- receivedBy inexistant → exception dédiée, avant l'appel SQL

## Documentation
- docs/journal/2026-07-2X-cash-receive-instrument.md — convention du
  23/07 (Reprise à froid / Origine verbatim / Décisions attribuées :
  (utilisateur) pour les 3 règles métier tranchées le 23/07, (architecte)
  pour la traduction en prompt, (Cursor — à valider) pour les choix
  d'implémentation laissés ouverts)
- docs/STATUS.md : "Cash Management : cash_movement_type + cash_movement
  migrés, encaissement d'instrument fait, 5 validations métier. Reste :
  décaissement, transferts, conversions, comptage/clôture, validation
  caissier central, banque, dépôts, rapprochement, HTTP."
- docs/backlog/todo.md

Si un point est ambigu et touche au métier, ARRÊTE-TOI et signale-le.
Relance phpstan/deptrac/phpcpd/phpunit.

## Remontée
Pousse sur main, donne-moi le nom du commit. Je vérifie moi-même.
```

## Décisions prises

- Session strictement `open` côté Application (pas seulement le trigger) (utilisateur)
- Instrument doit exister et être `active` (utilisateur)
- Routing `caisse` obligatoire ; absence = rejet (utilisateur)
- Doublon même-session porté par `uq_cash_movement_instrument_per_session` uniquement (architecte)
- Cross-session du même instrument autorisé (architecte / schéma)
- Codes d’erreur `cash_session.not_open` / `cash_receive.*` (Cursor — à valider)
- Migration : slice sans `CREATE cash_session` (déjà migré) + `cash_receive_instrument` (Cursor — à valider)

---

# Journal — 2026-07-23 — cash_receive_instrument

## Migration

`Version20260723130000` — SQL copié depuis `schema-cash-management-v1.sql` :
`cash_movement_type`, `cash_movement` (+ indexes dont
`uq_cash_movement_instrument_per_session`), `cash_movement_guard` + trigger,
`cash_receive_instrument()`. **Sans** `CREATE TABLE cash_session` (déjà
présent via `Version20260723000000`).

## Cinq validations Application (ordre)

1. Session `status === open` → `CashSessionNotOpenException`
2. Instrument trouvé + `Active` → not_found / not_active
3. Routing `caisse` (null ⇒ rejet) → `routing_not_caisse`
4. Doublon → catch 23505 `uq_cash_movement_instrument_per_session`
5. RAISE SQL « Instrument % introuvable » : **défensif, normalement
   inatteignable** car le point 2 a déjà validé l’existence avant l’appel ;
   mappé vers `CashReceiveInstrumentNotFoundException` si jamais atteint.

## Trigger vs Application

`cash_movement_guard` n’interdit que `validated` (commentaire schéma :
`closed` reste inscriptible pour la future validation caissier central).
L’Application refuse `closed` **et** `validated` avec la même exception
métier `cash_session.not_open`.

## Qualité

- phpstan : OK
- deptrac : 0 violations
- phpunit : 397 tests, 2680 assertions (2 notices préexistants)
- phpcpd : 5 clones acceptés (todo) — aucun nouveau clone Cash receive

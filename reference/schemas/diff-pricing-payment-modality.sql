--- a/schema-pricing-v1.sql
+++ b/schema-pricing-v1.sql
@@ (additif pur -- aucune ligne existante modifiée ou supprimée)
+-- ============================================================
+-- Réouverture ciblée documentée (19/07/2026), validée avec le chat
+-- pilote -- ferme le point 43 de sujets-reportes.md ("Activation/
+-- désactivation des modalités de paiement par service/période").
+--
+-- Origine : une "modalité de paiement" hôtelière est une combinaison
+-- nommée déterminant (1) la répartition acompte/solde entre agence et
+-- fournisseur (qui encaisse quoi), (2) au nom de qui la facture hôtel
+-- est établie (client ou agence). Le ciblage (hôtel/groupe hôtel,
+-- chambre, affilié/groupe affilié, dates réservation, dates arrivée)
+-- est ENTIÈREMENT couvert par le moteur de ciblage existant de
+-- pricing_rule -- aucune nouvelle table de ciblage créée ici.
+-- ============================================================
+
+-- Ajout additif au référentiel de nature existant -- même famille que
+-- margin/commission, même garde-fou de non-mélange (FK composite).
+INSERT INTO pricing_rule_nature (code, sort_order) VALUES ('payment_modality', 2);
+
+-- ------------------------------------------------------------
+-- pricing_payment_party_role : petit référentiel dédié -- les valeurs
+-- possibles (agence/fournisseur/client) ne sont PAS des party_role
+-- (qui portent des rôles réels de tiers externes vis-à-vis d'un
+-- bureau), c'est une bascule interne propre à la modalité de paiement.
+-- Table plutôt qu'ENUM, cohérent avec la convention du projet, même
+-- pour un petit ensemble fixe (précédent direct : pricing_value_type).
+-- ------------------------------------------------------------
+CREATE TABLE pricing_payment_party_role (
+    code        VARCHAR(40) PRIMARY KEY,
+    sort_order  SMALLINT NOT NULL DEFAULT 0,
+    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+INSERT INTO pricing_payment_party_role (code, sort_order) VALUES
+    ('agency',   0),
+    ('supplier', 1),
+    ('customer',   2);
+
+COMMENT ON TABLE pricing_payment_party_role IS 'Bascule interne agence/fournisseur/client utilisée par pricing_payment_modality_detail -- distinct de party_role (rôles de tiers externes). ''customer'' n''est valide que pour invoiced_to_code (facturation), jamais pour un collecteur acompte/solde (CHECK dédié).';
+
+-- ------------------------------------------------------------
+-- pricing_payment_modality_detail : même pattern FK composite que
+-- pricing_margin_detail/pricing_commission_detail -- empêche
+-- structurellement le mélange avec margin/commission (même garde-fou
+-- déjà validé 2 fois en sandbox, voir sujets-reportes.md §53).
+--
+-- Répartition modélisée comme UN SEUL pourcentage d'acompte (le solde
+-- = 100 - acompte, pas de redondance) + un collecteur pour chacune des
+-- deux jambes (agence ou fournisseur, jamais client -- CHECK dédié).
+-- deposit_percentage=100 est un cas valide (tout payé d'avance à un
+-- seul collecteur, pas de jambe solde réelle) -- balance_collector_code
+-- reste néanmoins NOT NULL pour rester simple (pas de branchement
+-- conditionnel), sa valeur est alors non significative pour le Domain.
+-- ------------------------------------------------------------
+CREATE TABLE pricing_payment_modality_detail (
+    rule_id                 BIGINT PRIMARY KEY REFERENCES pricing_rule(id),
+    -- Dénormalisé depuis pricing_rule, verrouillé à 'payment_modality'
+    -- par CHECK -- même garde-fou que margin/commission.
+    rule_nature_code        VARCHAR(20) NOT NULL DEFAULT 'payment_modality',
+
+    label                   VARCHAR(200) NOT NULL, -- libellé de la modalité (ex: "Avance agence + solde hôtel")
+
+    deposit_percentage      NUMERIC(5,2) NOT NULL, -- % du total qui constitue l'acompte, le reste = solde
+    deposit_collector_code  VARCHAR(20) NOT NULL REFERENCES pricing_payment_party_role(code),
+    balance_collector_code  VARCHAR(20) NOT NULL REFERENCES pricing_payment_party_role(code),
+
+    invoiced_to_code        VARCHAR(20) NOT NULL REFERENCES pricing_payment_party_role(code), -- au nom de qui la facture hôtel est établie
+
+    CONSTRAINT chk_pricing_payment_modality_nature CHECK (rule_nature_code = 'payment_modality'),
+    CONSTRAINT fk_pricing_payment_modality_rule_nature FOREIGN KEY (rule_id, rule_nature_code) REFERENCES pricing_rule(id, rule_nature_code),
+    CONSTRAINT chk_pricing_payment_modality_percentage CHECK (deposit_percentage > 0 AND deposit_percentage <= 100),
+    -- Les collecteurs acompte/solde ne peuvent être que agence ou
+    -- fournisseur, jamais client (le client est celui qui PAIE, pas un
+    -- collecteur -- CHECK dédié, distinct du domaine élargi de la
+    -- table pricing_payment_party_role).
+    CONSTRAINT chk_pricing_payment_modality_deposit_collector CHECK (deposit_collector_code IN ('agency', 'supplier')),
+    CONSTRAINT chk_pricing_payment_modality_balance_collector CHECK (balance_collector_code IN ('agency', 'supplier')),
+    -- La facture est établie au nom du client ou de l'agence, jamais
+    -- du fournisseur (ce serait la facture D'ACHAT, hors périmètre --
+    -- Pricing ne touche jamais l'achat).
+    CONSTRAINT chk_pricing_payment_modality_invoiced_to CHECK (invoiced_to_code IN ('customer', 'agency'))
+);
+
+COMMENT ON TABLE pricing_payment_modality_detail IS 'Modalité de paiement hôtelière -- répartition acompte/solde entre agence et fournisseur (qui encaisse quoi) + au nom de qui la facture hôtel est établie. Le ciblage (hôtel/chambre/affilié/dates) est entièrement porté par le moteur pricing_rule existant, aucun ciblage propre ici. rule_nature_code+FK composite empêche structurellement le mélange avec margin/commission (même garde-fou déjà validé en sandbox, sujets-reportes.md §53).';
+
+-- ------------------------------------------------------------
+-- NOTE HORS PÉRIMÈTRE (confirmé en session) : l'impact texte sur les
+-- documents générés (voucher) reste dans le module Documents (déjà
+-- figé, Permissions/Franchises/Config) -- pas de FK ajoutée ici. Si un
+-- besoin réel émerge de faire varier un template selon la modalité de
+-- paiement, la FK potentielle serait document_trigger_rule ->
+-- pricing_payment_modality_detail(rule_id), à ajouter côté Documents
+-- par réouverture ponctuelle de CE module-là, pas de Pricing.
+-- ------------------------------------------------------------

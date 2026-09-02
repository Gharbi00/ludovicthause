# ROADMAP — Simulateur de devis Thause Tourisme (LTT)

> Fichier de route pour le développement. À placer dans `docs/` du repo et à donner
> en contexte à Claude Code, avec le cahier des charges (`Cahier_des_charges_ThauseDevis.docx`)
> et l'ERD (`ERD_ThauseDevis.png`). On avance **phase par phase**.

---

## 1. Contexte en 30 secondes

Outil de chiffrage de prestations de transport en autocar pour LTT (transport occasionnel
de voyageurs + agence de voyages, Varennes-Vauzelles). Objectif : **chiffrer au coût de revient réel**
(charges issues des fichiers Excel) avec un **garde-fou de marge** à l'édition de chaque devis.
Coexiste avec ABC (ne le remplace pas). Voyages **France + Europe**.

---

## 2. Décisions figées (ne pas rediscuter sans raison)

- **Pas de Dolibarr.** Application web autonome.
- **Stack : Laravel (PHP 8.2+) + MariaDB**, front réactif **Livewire / Alpine**.
- **Moteur de calcul = service isolé** derrière une API interne (testable, réutilisable).
- **Front client = choix d'une CATÉGORIE** (par nb de places). Le **véhicule réel est affecté par la secrétaire**.
- **Aucun prix ni calcul côté client.** Le calcul (et les appels API payants) ne se déclenchent
  qu'à la validation par la secrétaire.
- **Écran secrétaire = marge visible avant édition** (garde-fou anti-perte).
- **APIs :**
  - **HERE Routing v8** = routage + trafic horaire + **péage Europe + vignettes** (natif, en €).
    Paramètres clés : `transportMode` (bus/truck), `return=tolls`, `tolls[vignettes]=all`,
    `via` (étapes), `departureTime` (trafic). **Gratuit au volume de LTT** (~300 devis/mois).
  - **prix-carburants.gouv.fr** (open data) = prix du gasoil. Gratuit.
  - **BAN + INSEE** = communes / géocodage. Gratuit.
  - **Repli péage** si HERE imprécis sur le profil autocar : **TollGuru Starter ~80 $/mois** ;
    **PTV** en dernier recours (sur devis).
- **Budget API réaliste : 0 à ~74 €/mois** (0 si HERE suffit pour le péage).
- **TVA transport voyageurs = 10 %.**
- **Hébergement : mutualisé LWS pour la démo, VPS pour la production.**

---

## 3. Fichiers livrés — où les placer

| Fichier | Destination | Nature |
|---|---|---|
| `2026_06_15_0000XX_*.php` (× 9) | `database/migrations/` | Code — schéma de la base |
| `ParametreSeeder.php` | `database/seeders/` | Code — paramètres par défaut |
| `Cahier_des_charges_ThauseDevis.docx` (**v0.3**) | `docs/` | Référence (pas du code) |
| `ERD_ThauseDevis.png` | `docs/` | Référence (schéma de la base) |
| `ROADMAP_ThauseDevis.md` (ce fichier) | `docs/` | Référence |

**À télécharger :** uniquement les versions ci-dessus (cahier des charges **v0.3**, l'ERD,
les 9 migrations, le seeder). Les versions v0.1 / v0.2 du cahier des charges sont **remplacées**
par la v0.3 ; les images de schéma d'archi sont déjà **intégrées dans le .docx** — inutile de les reprendre.

**Après avoir posé les migrations et le seeder :**
1. Enregistrer le seeder dans `database/seeders/DatabaseSeeder.php` :
   `$this->call(ParametreSeeder::class);`
2. `php artisan migrate --seed`

---

## 4. Décisions en attente (à trancher avant/pendant le dev)

- [ ] **Taux horaire chauffeur : global ou par véhicule ?** Actuellement paramètre global
      (`parametres.taux_horaire_chauffeur`, semé à 0 → à renseigner). Si LTT différencie selon
      le type de car → ajouter une colonne d'override sur `vehicules`.
- [ ] **Mapping exact des colonnes de la grille Excel → table `vehicules`** (à figer à l'import).
- [ ] **Lier `depot_commune_id`** (Varennes-Vauzelles) après l'import des communes.
- [ ] **Seuils RSE exacts** (561/2006 + dérogations occasionnel 2024/1258) à figer au codage du bloc RSE.

---

## 5. Roadmap par phases

### Phase 0 — Mise en place
- [ ] Créer le projet Laravel (PHP 8.2+), configurer `.env` (base MariaDB), `php artisan key:generate`.
- [ ] Installer Livewire + Alpine.
- [ ] Poser les 9 migrations + le seeder, `php artisan migrate --seed`.
- [ ] Vérifier que la table `users` de base existe (la migration `add_role` s'appuie dessus).

### Phase 1 — Modèles & référentiels
- [ ] Modèles Eloquent : `Categorie`, `Vehicule`, `Commune`, `Demande`, `Etape`, `Devis`,
      `Planning`, `Parametre`, `User` — avec relations et casts (décimaux, booléens, `calcul_payload` en JSON).
- [ ] Helper `Parametre::get('cle')` avec typage (cast selon `type`).
- [ ] Commande d'import **communes** depuis la BAN / INSEE (`php artisan communes:import`), en chunks.
- [ ] Commande d'import **véhicules** depuis le `.xlsm` (fige le mapping colonnes → `vehicules`).
- [ ] Commande d'import **planning** depuis l'Excel de disponibilité.

### Phase 2 — Front public (client)
- [ ] Formulaire de demande : sélection d'une catégorie filtrée par `nb_passagers`.
- [ ] Saisie du trajet + étapes avec **ajout dynamique** d'étapes (bouton « + »).
- [ ] Par étape : commune (autocomplétion sur `communes`), date, heure d'arrivée / de départ,
      cases **« horaire impératif »** (arrivée et départ).
- [ ] Protection anti-spam (captcha + rate-limit), enregistrement de la `demande` + `etapes`,
      **aucun prix / aucun calcul**. Stocker `ip_soumission`.
- [ ] Accusé de réception au client (statut `nouvelle`).

### Phase 3 — Backend secrétaire (authentifié)
- [ ] Authentification + rôles (`admin`, `secretaire`).
- [ ] Liste des demandes + vue détail.
- [ ] **Affectation du véhicule réel** : liste filtrée par catégorie + **contrôle du planning**
      (dispo sur les dates demandées, alerte si conflit).
- [ ] Écran **Réglages** : véhicules + tous les champs de charge **éditables**, `parametres`, clés API.

### Phase 4 — Moteur de calcul (le cœur — service isolé)
- [ ] Service **Itinéraire** (HERE Routing v8) : boucle dépôt → prise en charge → étapes → retour dépôt,
      distinction km chargés / à vide, profil poids-lourd/autocar, `departureTime` (trafic horaire),
      autoroute par défaut + proposition d'alternative plus courte (choix secrétaire).
- [ ] Récupération **péage + vignettes** via HERE (`return=tolls`), classe 3/4 selon `nb_essieux`.
      (Abstraire derrière une interface `PeageProvider` pour permettre le repli TollGuru.)
- [ ] Bloc **RSE 561/2006** (+ dérogations 2024/1258) : à partir du temps de conduite, déduire
      **1 ou 2 chauffeurs**, pauses, **nuitées**.
- [ ] Bloc **coûts** : carburant (conso × distance × prix gasoil), péage, chauffeur
      (taux × durée + attente, × nb chauffeurs), **charges fixes réallouées** (jours d'exploitation
      + saisonnalité), **charges variables /km**.
- [ ] **TVA 10 %**, **marge** (curseur), calcul `marge_montant` et seuil de rentabilité.
- [ ] Persister le détail des postes dans `devis` + `calcul_payload` (réponses API, traçabilité).

### Phase 5 — Devis
- [ ] Écran de **validation** : détail des postes + **marge visible** (garde-fou anti-perte).
- [ ] Génération du **PDF A4** du devis.
- [ ] Cycle de statuts (`brouillon` → `valide` → `envoye` → `accepte`/`refuse`), envoi au client.

### Phase 6 — Validation & déploiement
- [ ] **Test précision péage HERE** sur 2-3 trajets réels LTT, dont **1 transfrontalier (vignette)**,
      comparaison au péage réellement payé → décision **HERE seul (0 €)** vs **TollGuru (~74 €/mois)**.
- [ ] Renseigner les vraies données : `taux_horaire_chauffeur`, clés API, `depot_commune_id`.
- [ ] Déploiement **démo** (mutualisé LWS, terminal web) puis **prod** (VPS).
- [ ] Vérifier : **appels sortants cURL autorisés**, extensions PHP, SSL Let's Encrypt.

### Phase 7 — Options (lignes séparables, après le socle)
- [ ] Tableau de **rentabilité par véhicule** (coût/km, point mort, marge/véhicule).
- [ ] **Empreinte CO₂** estimée (argument appels d'offres).
- [ ] **Comparateur multi-véhicules** + simulation (taux de remplissage, aller simple / A-R).
- [ ] **Multi-énergie** (gazole / GNV / électrique) via `type_energie`.

---

## 6. Points de vigilance

- **Sécurité front public** : aucune logique de prix ni appel API exposé ; captcha + rate-limit obligatoires.
- **Budget API** : les appels payants ne partent QU'À la validation secrétaire.
- **RSE** : c'est le poste qui peut doubler le coût chauffeur (2e chauffeur / nuitée) sur les
  tournées européennes multi-jours — à traiter sérieusement, pas comme un ajustement.
- **Valeurs dérivées** (coût/km, point mort…) : **toujours recalculées**, jamais stockées en dur
  (c'est ce qui a pourri l'Excel de LTT).
- **RGPD** : données client (nom, contact) conservées pour la seule finalité « devis ».

---

## 7. Comment bosser avec Claude Code

1. Mettre `docs/` (cahier des charges + ERD + ce fichier) dans le repo.
2. Démarrer chaque session en pointant Claude Code sur ce ROADMAP et la phase en cours.
3. Avancer **une phase à la fois**, valider, committer, passer à la suivante.
4. Le cahier des charges reste la référence fonctionnelle ; ce fichier, la référence des tâches.

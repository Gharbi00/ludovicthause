<?php

namespace Database\Seeders;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

class ParametreSeeder extends Seeder
{
    /**
     * Paramètres par défaut. Les valeurs marquées « à renseigner » (0 / vide)
     * doivent être complétées en Phase 6 avec les vraies données LTT.
     */
    public function run(): void
    {
        $parametres = [
            // --- Calcul / fiscalité ---
            ['cle' => 'taux_tva',              'valeur' => '10',   'type' => 'decimal', 'groupe' => 'calcul',    'libelle' => 'Taux de TVA transport voyageurs (%)'],
            ['cle' => 'marge_cible',           'valeur' => '15',   'type' => 'decimal', 'groupe' => 'calcul',    'libelle' => 'Marge cible par défaut (%)'],
            ['cle' => 'coef_saisonnalite',     'valeur' => '1.0',  'type' => 'decimal', 'groupe' => 'calcul',    'libelle' => 'Coefficient de saisonnalité (réallocation charges fixes)'],

            // --- Structure / chauffeur ---
            ['cle' => 'taux_horaire_chauffeur', 'valeur' => '23.20', 'type' => 'decimal', 'groupe' => 'structure', 'libelle' => 'Coût horaire chauffeur (€/h) — issu grille LTT (base 2007 +30% charges), à réactualiser'],
            ['cle' => 'depot_commune_id',       'valeur' => null,  'type' => 'integer', 'groupe' => 'structure', 'libelle' => 'Commune du dépôt (Varennes-Vauzelles) — à lier après import communes'],

            // --- Carburant ---
            ['cle' => 'prix_gasoil_litre',     'valeur' => '1.70', 'type' => 'decimal', 'groupe' => 'carburant', 'libelle' => 'Prix du gasoil (€/L) — indexable via prix-carburants.gouv.fr'],

            // --- Péage (tarif au km sur autoroute à péage, par classe autocar) ---
            ['cle' => 'peage_tarif_km_classe3', 'valeur' => '0.20', 'type' => 'decimal', 'groupe' => 'peage', 'libelle' => 'Péage classe 3 (2 essieux) — €/km sur autoroute à péage'],
            ['cle' => 'peage_tarif_km_classe4', 'valeur' => '0.29', 'type' => 'decimal', 'groupe' => 'peage', 'libelle' => 'Péage classe 4 (3 essieux et +) — €/km sur autoroute à péage'],

            // --- Frais de déplacement chauffeur ---
            ['cle' => 'frais_nuitee_chauffeur', 'valeur' => '90', 'type' => 'decimal', 'groupe' => 'structure', 'libelle' => 'Frais de nuitée chauffeur (hôtel + repas, €/nuit/chauffeur)'],

            // --- RSE (règlement 561/2006, seuils à figer au dev du bloc) ---
            ['cle' => 'rse_conduite_journaliere_max_min', 'valeur' => '540', 'type' => 'integer', 'groupe' => 'rse', 'libelle' => 'Conduite journalière max (min) — 9h'],
            ['cle' => 'rse_conduite_avant_pause_min',     'valeur' => '270', 'type' => 'integer', 'groupe' => 'rse', 'libelle' => 'Conduite avant pause obligatoire (min) — 4h30'],
            ['cle' => 'rse_duree_pause_min',              'valeur' => '45',  'type' => 'integer', 'groupe' => 'rse', 'libelle' => 'Durée pause (min) — fractionnable 15+30'],
            ['cle' => 'rse_repos_journalier_min',         'valeur' => '660', 'type' => 'integer', 'groupe' => 'rse', 'libelle' => 'Repos journalier (min) — 11h'],

            // --- Clés API (à renseigner en réglages) ---
            ['cle' => 'here_api_key',      'valeur' => '', 'type' => 'string', 'groupe' => 'api', 'libelle' => 'Clé API HERE (routage + péage ; carte bancaire requise à l\'inscription)'],
            ['cle' => 'ors_api_key',       'valeur' => '', 'type' => 'string', 'groupe' => 'api', 'libelle' => 'Clé API OpenRouteService (routage réel, SANS carte bancaire ; péage estimé)'],
            ['cle' => 'tollguru_api_key',  'valeur' => '', 'type' => 'string', 'groupe' => 'api', 'libelle' => 'Clé API TollGuru (repli péage)'],
            ['cle' => 'peage_provider',    'valeur' => 'here', 'type' => 'string', 'groupe' => 'api', 'libelle' => 'Fournisseur de péage actif : here | tollguru'],

            // --- E-mail de confirmation (accusé de réception client) ---
            ['cle' => 'email_confirmation_active', 'valeur' => '1', 'type' => 'boolean', 'groupe' => 'email', 'libelle' => 'Envoyer un e-mail de confirmation au client'],
            ['cle' => 'email_from_address',        'valeur' => 'contact@doliexpert.fr', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'Adresse e-mail expéditeur'],
            ['cle' => 'email_from_nom',            'valeur' => 'Ludovic Thause Tourisme', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'Nom expéditeur'],
            ['cle' => 'email_confirmation_sujet',  'valeur' => 'Votre demande de devis — Ludovic Thause Tourisme', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'Objet de l\'e-mail'],
            ['cle' => 'email_confirmation_corps',  'valeur' => "Bonjour {nom},\n\nNous vous remercions de nous avoir consultés pour votre projet de transport.\n\nVotre demande (référence {reference}) a bien été enregistrée. Notre équipe l'étudie et vous fera parvenir votre devis dans les plus brefs délais.\n\nCordialement,\nL'équipe Ludovic Thause Tourisme", 'type' => 'string', 'groupe' => 'email', 'libelle' => 'Texte de l\'e-mail (variables : {nom}, {reference})'],

            // --- Mode d'envoi (transport) ---
            ['cle' => 'email_transport',   'valeur' => 'sendmail', 'type' => 'string', 'groupe' => 'email', 'libelle' => "Mode d'envoi : sendmail (rapide) ou smtp (fiable, recommandé)"],
            ['cle' => 'smtp_host',         'valeur' => '', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'SMTP — serveur (ex. mail.doliexpert.fr)'],
            ['cle' => 'smtp_port',         'valeur' => '465', 'type' => 'integer', 'groupe' => 'email', 'libelle' => 'SMTP — port (465 SSL ou 587 TLS)'],
            ['cle' => 'smtp_username',     'valeur' => '', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'SMTP — identifiant (adresse e-mail complète)'],
            ['cle' => 'smtp_password',     'valeur' => '', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'SMTP — mot de passe de la boîte'],
            ['cle' => 'smtp_encryption',   'valeur' => 'ssl', 'type' => 'string', 'groupe' => 'email', 'libelle' => 'SMTP — chiffrement (ssl pour 465, tls pour 587)'],

            // --- Coordonnées société (PDF, pieds de page…) ---
            ['cle' => 'societe_nom',        'valeur' => 'Ludovic Thause Tourisme', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'Raison sociale'],
            ['cle' => 'societe_activite',   'valeur' => 'Transport de voyageurs en autocar', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'Activité (sous-titre)'],
            ['cle' => 'societe_adresse',    'valeur' => '14 rue Jacques Duclos', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'Adresse (n° et rue)'],
            ['cle' => 'societe_cp_ville',   'valeur' => '58640 Varennes-Vauzelles', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'Code postal et ville'],
            ['cle' => 'societe_siret',      'valeur' => '530 024 611 00051', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'SIRET'],
            ['cle' => 'societe_tva',        'valeur' => 'FR84 530 024 611', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'N° TVA intracommunautaire'],
            ['cle' => 'societe_telephone',  'valeur' => '', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'Téléphone'],
            ['cle' => 'societe_email',      'valeur' => '', 'type' => 'string', 'groupe' => 'societe', 'libelle' => 'E-mail de contact'],

            // --- Devis PDF ---
            ['cle' => 'pdf_validite_jours', 'valeur' => '30', 'type' => 'integer', 'groupe' => 'pdf', 'libelle' => 'Durée de validité du devis (jours)'],
            ['cle' => 'pdf_conditions',     'valeur' => 'Devis établi sous réserve de disponibilité à la date de commande. Prix ferme pendant la durée de validité indiquée. TVA sur le transport de voyageurs : 10 %. Règlement à réception de facture.', 'type' => 'string', 'groupe' => 'pdf', 'libelle' => 'Conditions (bas du devis)'],
        ];

        foreach ($parametres as $p) {
            $existant = Parametre::where('cle', $p['cle'])->first();

            if ($existant) {
                // Ne JAMAIS écraser une valeur déjà saisie (clés API, réglages…).
                // On rafraîchit seulement les métadonnées (type, groupe, libellé).
                $existant->update([
                    'type'    => $p['type'],
                    'groupe'  => $p['groupe'],
                    'libelle' => $p['libelle'],
                ]);
            } else {
                Parametre::create($p);
            }
        }
    }
}

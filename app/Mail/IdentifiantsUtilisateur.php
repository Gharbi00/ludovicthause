<?php

namespace App\Mail;

use App\Models\Parametre;
use App\Models\User;
use App\Services\TransportMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie à un utilisateur ses identifiants avec un mot de passe PROVISOIRE
 * qu'il devra changer à sa première connexion.
 */
class IdentifiantsUtilisateur extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $utilisateur,
        public string $motDePasseProvisoire,
    ) {}

    public function envelope(): Envelope
    {
        $societe = (string) Parametre::get('societe_nom', 'Ludovic Thause Tourisme');

        return new Envelope(
            from: new Address(
                TransportMail::adresseExpediteur(),
                (string) Parametre::get('email_from_nom', $societe),
            ),
            subject: 'Vos identifiants d’accès — ' . $societe,
        );
    }

    public function content(): Content
    {
        $societe = (string) Parametre::get('societe_nom', 'Ludovic Thause Tourisme');
        $url = route('login');
        $urlPublic = url('/');

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e293b;line-height:1.6">'
            . 'Bonjour ' . e($this->utilisateur->name) . ',<br><br>'
            . 'Un accès à l’espace secrétariat de <strong>' . e($societe) . '</strong> a été créé pour vous.<br><br>'
            . '<strong>Adresse de connexion :</strong> <a href="' . e($url) . '">' . e($url) . '</a><br>'
            . '<strong>Identifiant (e-mail) :</strong> ' . e($this->utilisateur->email) . '<br>'
            . '<strong>Mot de passe provisoire :</strong> ' . e($this->motDePasseProvisoire) . '<br><br>'
            . '<span style="color:#b45309">Pour votre sécurité, ce mot de passe est provisoire : '
            . 'il vous sera demandé d’en choisir un nouveau dès votre première connexion.</span>'
            . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0">'
            . '<strong>L’application en bref — deux pages :</strong>'
            . '<ul style="padding-left:18px;margin:8px 0">'
            . '<li style="margin:6px 0"><strong>Page client</strong> (publique) : c’est là que les clients saisissent leur demande de devis (trajet, dates, nombre de passagers). '
            . 'Aucun tarif n’y est affiché.<br><a href="' . e($urlPublic) . '">' . e($urlPublic) . '</a></li>'
            . '<li style="margin:6px 0"><strong>Page secrétariat</strong> (privée, vos identifiants ci-dessus) : pour consulter les demandes reçues, '
            . 'affecter un véhicule, calculer le coût et éditer le devis PDF.<br><a href="' . e($url) . '">' . e($url) . '</a></li>'
            . '</ul>'
            . 'Un <strong>mode d’emploi</strong> complet est disponible une fois connecté, via le menu « Mode d’emploi ».'
            . '<br><br>Cordialement,<br>L’équipe ' . e($societe)
            . '</div>';

        return new Content(htmlString: $html);
    }
}

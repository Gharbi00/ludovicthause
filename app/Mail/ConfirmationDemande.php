<?php

namespace App\Mail;

use App\Models\Demande;
use App\Models\Parametre;
use App\Services\TransportMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmationDemande extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Demande $demande) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                TransportMail::adresseExpediteur(),
                (string) Parametre::get('email_from_nom', 'Ludovic Thause Tourisme'),
            ),
            subject: $this->remplacer((string) Parametre::get('email_confirmation_sujet', 'Votre demande de devis')),
        );
    }

    public function content(): Content
    {
        $corps = (string) Parametre::get('email_confirmation_corps', 'Merci de votre demande.');

        return new Content(
            htmlString: '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e293b;line-height:1.6">'
                .nl2br(e($this->remplacer($corps)))
                .'</div>',
        );
    }

    /** Remplace les variables du modèle par les valeurs de la demande. */
    protected function remplacer(string $texte): string
    {
        return strtr($texte, [
            '{nom}' => $this->demande->client_nom,
            '{reference}' => $this->demande->reference,
        ]);
    }
}

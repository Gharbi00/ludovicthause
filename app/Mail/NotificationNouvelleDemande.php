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

class NotificationNouvelleDemande extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Demande $demande) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(TransportMail::adresseExpediteur(), (string) Parametre::get('email_from_nom', 'Ludovic Thause Tourisme')),
            subject: 'Nouvelle demande '.$this->demande->reference,
        );
    }

    public function content(): Content
    {
        $url = route('admin.demande', $this->demande);

        return new Content(
            htmlString: '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e293b;line-height:1.6">'
                .'<p>Une nouvelle demande de devis a été reçue.</p>'
                .'<p><strong>Référence :</strong> '.e($this->demande->reference).'<br>'
                .'<strong>Client :</strong> '.e($this->demande->client_nom).'<br>'
                .'<strong>Type :</strong> '.e((string) $this->demande->type_trajet).'</p>'
                .'<p><a href="'.e($url).'">Ouvrir le dossier dans le secrétariat</a></p>'
                .'</div>',
        );
    }
}

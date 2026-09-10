<?php

namespace App\Livewire\Admin;

use App\Mail\ConfirmationDemande;
use App\Models\Demande;
use App\Models\Parametre;
use App\Services\TransportMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class EmailsReglages extends Component
{
    public bool $actif = true;

    public string $from_address = '';

    public string $from_nom = '';

    public string $sujet = '';

    public string $corps = '';

    // Transport
    public string $transport = 'sendmail';

    public string $smtp_host = '';

    public string $smtp_port = '465';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_encryption = 'ssl';

    public string $testEmail = '';

    public ?string $flash = null;

    public ?string $testInfo = null;

    public function mount(): void
    {
        $this->actif = (bool) Parametre::get('email_confirmation_active', true);
        $this->from_address = (string) Parametre::get('email_from_address', '');
        $this->from_nom = (string) Parametre::get('email_from_nom', '');
        $this->sujet = (string) Parametre::get('email_confirmation_sujet', '');
        $this->corps = (string) Parametre::get('email_confirmation_corps', '');

        $this->transport = (string) Parametre::get('email_transport', 'sendmail');
        $this->smtp_host = (string) Parametre::get('smtp_host', '');
        $this->smtp_port = (string) Parametre::get('smtp_port', '465');
        $this->smtp_username = (string) Parametre::get('smtp_username', '');
        $this->smtp_password = (string) Parametre::get('smtp_password', '');
        $this->smtp_encryption = (string) Parametre::get('smtp_encryption', 'ssl');
    }

    protected function sauver(): void
    {
        Parametre::set('email_confirmation_active', $this->actif ? '1' : '0');
        Parametre::set('email_from_address', $this->from_address);
        Parametre::set('email_from_nom', $this->from_nom);
        Parametre::set('email_confirmation_sujet', $this->sujet);
        Parametre::set('email_confirmation_corps', $this->corps);

        Parametre::set('email_transport', $this->transport);
        Parametre::set('smtp_host', $this->smtp_host);
        Parametre::set('smtp_port', $this->smtp_port);
        Parametre::set('smtp_username', $this->smtp_username);
        Parametre::set('smtp_password', $this->smtp_password);
        Parametre::set('smtp_encryption', $this->smtp_encryption);
    }

    public function enregistrer(): void
    {
        $this->validate([
            'from_address' => ['required', 'email'],
            'from_nom' => ['required', 'string', 'max:100'],
            'sujet' => ['required', 'string', 'max:200'],
            'corps' => ['required', 'string', 'max:3000'],
        ]);

        $this->sauver();
        $this->flash = 'E-mail de confirmation enregistré.';
    }

    public function envoyerTest(): void
    {
        $this->validate(['testEmail' => ['required', 'email']], [
            'testEmail.required' => 'Indiquez une adresse pour le test.',
            'testEmail.email' => 'Adresse de test invalide.',
        ]);

        $this->sauver(); // le test reflète le texte courant

        $demo = new Demande([
            'reference' => 'TEST-0000',
            'client_nom' => 'Client test',
            'client_email' => $this->testEmail,
        ]);

        try {
            Mail::mailer(TransportMail::resoudre())->to($this->testEmail)->send(new ConfirmationDemande($demo));
            $this->testInfo = 'E-mail de test envoyé à '.$this->testEmail.' (mode « '.$this->transport.' ») — vérifiez réception ET spams.';
        } catch (\Throwable $e) {
            $this->testInfo = 'Échec de l’envoi : '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.emails-reglages');
    }
}

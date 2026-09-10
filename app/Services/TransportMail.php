<?php

namespace App\Services;

use App\Models\Parametre;

/**
 * Résout le transport e-mail à utiliser selon les paramètres (sans toucher au .env) :
 *   - 'smtp'     : configure le mailer SMTP à la volée depuis les paramètres.
 *   - 'sendmail' : envoi via le serveur local (rapide, délivrabilité moyenne).
 *   - autre      : renvoyé tel quel (ex. 'log').
 */
class TransportMail
{
    /** Configure le transport et renvoie le nom du mailer à passer à Mail::mailer(). */
    public static function resoudre(): string
    {
        $transport = (string) Parametre::get('email_transport', 'sendmail');

        if ($transport === 'smtp') {
            config([
                'mail.mailers.smtp.host' => (string) Parametre::get('smtp_host'),
                'mail.mailers.smtp.port' => (int) Parametre::get('smtp_port', 465),
                'mail.mailers.smtp.username' => (string) Parametre::get('smtp_username'),
                'mail.mailers.smtp.password' => (string) Parametre::get('smtp_password'),
                'mail.mailers.smtp.encryption' => (string) Parametre::get('smtp_encryption', 'ssl'),
                'mail.mailers.smtp.timeout' => 15,
            ]);

            return 'smtp';
        }

        return $transport;
    }

    /**
     * Adresse d'expéditeur à utiliser, alignée sur la boîte SMTP authentifiée.
     *
     * Gmail (DMARC) attend que l'adresse « From » soit sur le même domaine que
     * la signature DKIM / l'enregistrement SPF, c'est-à-dire le domaine de la
     * boîte SMTP. Si l'adresse configurée est sur un autre domaine, on la
     * remplace par l'identifiant SMTP pour garantir la délivrabilité.
     */
    public static function adresseExpediteur(): string
    {
        $from = trim((string) Parametre::get('email_from_address', ''));

        if ((string) Parametre::get('email_transport', 'sendmail') === 'smtp') {
            $user = trim((string) Parametre::get('smtp_username', ''));
            if ($user !== '' && self::domaine($from) !== self::domaine($user)) {
                return $user;
            }
        }

        return $from !== '' ? $from : 'contact@doliexpert.fr';
    }

    /** Domaine (en minuscules) d'une adresse e-mail, ou '' si absent. */
    private static function domaine(string $email): string
    {
        $at = strrchr($email, '@');

        return $at ? strtolower(substr($at, 1)) : '';
    }
}

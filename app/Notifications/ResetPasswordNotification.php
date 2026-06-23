<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Accedi a Hub Core — imposta o reimposta password')
            ->greeting('Ciao, '.$notifiable->name.'!')
            ->line('Hai richiesto le istruzioni per accedere a **Hub Core**.')
            ->line('Clicca il pulsante qui sotto per impostare la tua password e accedere alla piattaforma.')
            ->action('Imposta password e accedi', $url)
            ->line('Il link è valido per **'.config('auth.passwords.users.expire').' minuti**.')
            ->line('Se non hai richiesto tu questa email, puoi ignorarla in sicurezza.')
            ->salutation('A presto, il team Hub Core');
    }
}

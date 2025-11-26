<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $resetUrl = 'http://localhost:3000/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->correo);

        return (new MailMessage)
            ->subject('Restablecer Contraseña - SmartBuild')
            ->greeting('¡Hola!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer Contraseña', $resetUrl)
            ->line('Este enlace expirará en 60 minutos.')
            ->line('Si no solicitaste restablecer tu contraseña, puedes ignorar este correo.')
            ->salutation('Saludos, El equipo de SmartBuild');
    }
}

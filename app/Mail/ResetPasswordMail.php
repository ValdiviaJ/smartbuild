<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // El link apuntará al frontend (puerto 3000 por defecto)
        $link = "http://localhost:3000/reset-password?token=" . $this->token . "&email=" . urlencode($this->email);

        return $this->subject('Restablecer Contraseña - SmartBuild IA')
                    ->html("
                        <h1>Restablecer Contraseña</h1>
                        <p>Has solicitado restablecer tu contraseña en SmartBuild IA.</p>
                        <p>Haz clic en el siguiente enlace para continuar:</p>
                        <a href='{$link}' style='display:inline-block; padding:10px 20px; background-color:#3b82f6; color:white; text-decoration:none; border-radius:5px;'>Restablecer Contraseña</a>
                        <p>Si no solicitaste esto, puedes ignorar este correo.</p>
                        <p><small>Este enlace expirará en 60 minutos.</small></p>
                    ");
    }
}

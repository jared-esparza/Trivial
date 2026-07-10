<?php

declare(strict_types=1);

final class NativeMailer implements Mailer
{
    public function __construct(private string $from)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $headers = [
            'From: ' . $this->from,
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
            throw new RuntimeException('No se pudo enviar el correo.');
        }
    }
}

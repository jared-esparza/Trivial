<?php

declare(strict_types=1);

final class LocalOutboxMailer implements Mailer
{
    public function __construct(private string $path)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio del outbox local.');
        }
        $message = sprintf(
            "[%s]\nTo: %s\nSubject: %s\n\n%s\n\n",
            gmdate('c'),
            $to,
            $subject,
            $body
        );
        if (file_put_contents($this->path, $message, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el outbox local.');
        }
    }
}

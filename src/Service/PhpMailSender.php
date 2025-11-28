<?php

declare(strict_types=1);

namespace App\Service;

use PHPMailer\PHPMailer\Exception as PhpmailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailSender implements MailSenderInterface
{
    public function __construct(
        private string $defaultFrom,
        private ?string $fromName,
        private string $host,
        private int $port,
        private bool $smtpAuth,
        private ?string $username = null,
        private ?string $password = null,
        private ?string $encryption = null,
        private float $timeout = 5.0,
    ) {
    }

    /**
     * @param array<string,string> $headers
     */
    public function send(string $to, string $subject, string $body, array $headers = []): void
    {
        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = $this->host;
            $mailer->Port = $this->port;
            $mailer->SMTPAuth = $this->smtpAuth;
            $mailer->Timeout = (int) ceil($this->timeout);
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            $mailer->setFrom($this->defaultFrom, $this->fromName ?? '');
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = $body;
            $mailer->isHTML(false);

            if ($this->smtpAuth) {
                $mailer->Username = (string) $this->username;
                $mailer->Password = (string) $this->password;
            }

            if ($this->encryption) {
                $mailer->SMTPSecure = $this->encryption;
            }

            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'from') {
                    continue;
                }
                $mailer->addCustomHeader($name, $value);
            }

            $mailer->send();
        } catch (PhpmailerException $e) {
            throw new RuntimeException('メールの送信に失敗しました: ' . $e->getMessage(), 0, $e);
        }
    }
}

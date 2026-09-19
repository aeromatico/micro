<?php namespace Aero\Sites\Classes\Mail;

use Http;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * ZeptomailTransport sends mail through Zeptomail's HTTP API instead of SMTP.
 *
 * Existe porque SMTP quedó rechazado por Zoho (535 Authentication Failed,
 * confirmado con AUTH LOGIN y PLAIN fuera de Laravel) mientras la misma
 * cuenta sí envía por su API HTTP — ver conversación 2026-09-16.
 */
class ZeptomailTransport extends AbstractTransport
{
    protected string $token;

    protected string $endpoint;

    public function __construct(string $token, string $endpoint = 'https://api.zeptomail.com/v1.1/email')
    {
        parent::__construct();

        $this->token = $token;
        $this->endpoint = $endpoint;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (!$email instanceof Email) {
            throw new RuntimeException('ZeptomailTransport only supports Email messages.');
        }

        $payload = [
            'from' => $this->addressToPayload($email->getFrom()[0] ?? $email->getSender()),
            'to' => array_map(function (Address $address) {
                return ['email_address' => $this->addressToPayload($address)];
            }, $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map(fn (Address $a) => ['email_address' => $this->addressToPayload($a)], $cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = array_map(fn (Address $a) => ['email_address' => $this->addressToPayload($a)], $bcc);
        }

        if ($html = $email->getHtmlBody()) {
            $payload['htmlbody'] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['textbody'] = $text;
        }

        $response = Http::withHeaders([
            'Authorization' => $this->token,
            'Accept' => 'application/json',
        ])->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new TransportException(
                'Zeptomail API error (' . $response->status() . '): ' . $response->body()
            );
        }
    }

    protected function addressToPayload(Address $address): array
    {
        return array_filter([
            'address' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ]);
    }

    public function __toString(): string
    {
        return 'zeptomail+api://api.zeptomail.com';
    }
}

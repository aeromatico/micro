<?php namespace Aero\Wapi\Classes\Exceptions;

use Illuminate\Http\Client\Response;

class WapiApiException extends \RuntimeException
{
    public function __construct(string $message, protected array $body = [])
    {
        parent::__construct($message);
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public static function fromResponse(Response $response): static
    {
        $message = $response->json('message') ?? $response->json('error') ?? "wapi respondió {$response->status()}";

        return new static($message, (array) ($response->json() ?? []));
    }
}

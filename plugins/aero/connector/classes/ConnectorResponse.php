<?php namespace Aero\Connector\Classes;

class ConnectorResponse
{
    public function __construct(
        public bool $successful,
        public ?int $statusCode,
        public array $headers,
        public mixed $body,
        public string $rawBody,
        public int $durationMs,
        public ?string $error = null,
    ) {}

    public static function fromError(string $message, int $durationMs = 0): self
    {
        return new self(
            successful: false,
            statusCode: null,
            headers: [],
            body: null,
            rawBody: '',
            durationMs: $durationMs,
            error: $message,
        );
    }

    public function toArray(): array
    {
        return [
            'successful'  => $this->successful,
            'status_code' => $this->statusCode,
            'headers'     => $this->headers,
            'body'        => $this->body,
            'raw_body'    => $this->rawBody,
            'duration_ms' => $this->durationMs,
            'error'       => $this->error,
        ];
    }
}

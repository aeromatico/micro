<?php namespace Aero\Social\Classes;

use RuntimeException;

/** Error de una red, con mensaje apto para el tenant. */
class SocialException extends RuntimeException
{
    /** El token ya no sirve: hay que reconectar la cuenta. */
    public bool $tokenInvalid = false;

    public static function tokenInvalid(string $message): self
    {
        $e = new self($message);
        $e->tokenInvalid = true;

        return $e;
    }
}

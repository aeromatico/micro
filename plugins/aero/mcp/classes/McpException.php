<?php namespace Aero\Mcp\Classes;

use RuntimeException;

/**
 * Error de protocolo JSON-RPC. El código es el de JSON-RPC (-32xxx).
 */
class McpException extends RuntimeException
{
}

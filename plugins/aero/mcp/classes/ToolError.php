<?php namespace Aero\Mcp\Classes;

use RuntimeException;

/**
 * Error de una tool cuyo mensaje es seguro de mostrar al cliente (validación,
 * "no encontrado"…). Cualquier otra excepción se devuelve como mensaje genérico.
 */
class ToolError extends RuntimeException
{
}

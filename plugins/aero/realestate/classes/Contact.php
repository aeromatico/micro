<?php namespace Aero\Realestate\Classes;

/** Enlaces de contacto del portal. Los teléfonos bolivianos de 8 dígitos (celulares 6/7) reciben el prefijo 591. */
class Contact
{
    public static function whatsappNumber(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 8 && in_array($d[0], ['6', '7'], true)) {
            $d = '591' . $d;
        }

        return strlen($d) >= 10 ? $d : null;
    }

    public static function whatsappUrl(?string $phone, string $text = ''): ?string
    {
        $n = self::whatsappNumber($phone);

        return $n ? 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '') : null;
    }
}

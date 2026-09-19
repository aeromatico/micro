<?php namespace Aero\Sms\Classes;

/**
 * Cuenta segmentos como los cobra el operador: GSM-7 son 160 caracteres (153
 * si el mensaje se concatena), y cualquier carácter fuera de GSM-7 (una
 * tilde en mayúscula, un emoji) baja todo el mensaje a UCS-2: 70 (67).
 */
class Segments
{
    protected const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    protected const GSM_EXTENDED = "^{}\\[~]|€\f";

    /** @return array{segments:int, encoding:string, length:int} */
    public static function count(string $body): array
    {
        $length = 0;
        $gsm = true;

        foreach (mb_str_split($body) as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) !== false) {
                $length++;
            }
            elseif (mb_strpos(self::GSM_EXTENDED, $char) !== false) {
                $length += 2;
            }
            else {
                $gsm = false;
                break;
            }
        }

        if (!$gsm) {
            $length = mb_strlen($body);
            $single = 70;
            $multi = 67;
        }
        else {
            $single = 160;
            $multi = 153;
        }

        return [
            'segments' => max(1, $length <= $single ? 1 : (int) ceil($length / $multi)),
            'encoding' => $gsm ? 'GSM-7' : 'UCS-2',
            'length'   => $length,
        ];
    }
}

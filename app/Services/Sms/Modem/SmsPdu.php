<?php

namespace App\Services\Sms\Modem;

use InvalidArgumentException;

/** SMS-SUBMIT PDU kodlovchi: GSM 7-bit (oddiy lotin matn) yoki UCS2 (kirill, o'zbek ʻ va h.k.), uzun xabar — bo'laklarga. */
class SmsPdu
{
    private const GSM7_SAFE = '/^[A-Za-z0-9 .,!?:;()+\-\/%=*#"\'\n]*$/';

    /** @return list<array{0:string,1:int}> [pdu hex, TPDU uzunligi (AT+CMGS parametri)] */
    public static function encode(string $phone, string $text): array
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            throw new InvalidArgumentException("Telefon raqami noto'g'ri");
        }

        $ucs2 = ! preg_match(self::GSM7_SAFE, $text);
        $parts = $ucs2 ? self::splitUcs2($text) : self::splitGsm7($text);
        $multi = count($parts) > 1;
        $ref = random_int(1, 255);

        $addr = strtoupper(self::swap($digits));
        $out = [];
        foreach ($parts as $i => $part) {
            $udh = $multi ? sprintf('050003%02X%02X%02X', $ref, count($parts), $i + 1) : '';
            $first = $multi ? '41' : '01';   // SMS-SUBMIT (+UDHI)
            $dcs = $ucs2 ? '08' : '00';

            if ($ucs2) {
                $ud = $udh.strtoupper(bin2hex(mb_convert_encoding($part, 'UTF-16BE', 'UTF-8')));
                $udl = strlen($ud) / 2;
            } else {
                $pad = $multi ? 1 : 0;
                $ud = ($multi ? $udh : '').self::packGsm7($part, $pad);
                $udl = ($multi ? 7 : 0) + strlen($part);
            }

            $tpdu = $first.'00'.sprintf('%02X', strlen($digits)).'91'.$addr.'00'.$dcs.sprintf('%02X', $udl).$ud;
            $out[] = ['00'.$tpdu, strlen($tpdu) / 2];
        }

        return $out;
    }

    private static function swap(string $digits): string
    {
        if (strlen($digits) % 2) {
            $digits .= 'F';
        }
        $r = '';
        for ($i = 0; $i < strlen($digits); $i += 2) {
            $r .= $digits[$i + 1].$digits[$i];
        }

        return $r;
    }

    /** @return list<string> */
    private static function splitGsm7(string $t): array
    {
        $len = strlen($t);

        return $len <= 160 ? [$t] : str_split($t, 153);
    }

    /** @return list<string> */
    private static function splitUcs2(string $t): array
    {
        $chars = mb_str_split($t);   // BMP belgilari: 1 belgi = 1 UTF-16 birlik
        if (count($chars) <= 70) {
            return [$t];
        }

        return array_map('implode', array_chunk($chars, 67));
    }

    /** GSM 7-bit paketlash (ASCII ning xavfsiz qismi GSM alifbosi bilan bir xil). $pad — UDH dan keyingi to'ldiruvchi bitlar. */
    private static function packGsm7(string $s, int $pad): string
    {
        $bits = str_repeat('0', $pad);
        foreach (str_split($s) as $ch) {
            $bits .= strrev(str_pad(decbin(ord($ch) & 0x7F), 7, '0', STR_PAD_LEFT));
        }
        // bits: har septet LSB-first ketma-ket
        $hex = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $byte = strrev(str_pad(substr($bits, $i, 8), 8, '0', STR_PAD_RIGHT));
            $hex .= sprintf('%02X', bindec($byte));
        }

        return $hex;
    }
}

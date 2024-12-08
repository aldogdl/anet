<?php

namespace App\Dtos;

class HeaderDto {

    static String $PREF = 'Anet-';

    /** */
    static function includeBody(array $header, bool $value): array
    {
        $header[HeaderDto::$PREF."Down"] = ($value) ? 0 : 1;
        return $header;
    }
    /** */
    static function event(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Event"] = $value;
        return $header;
    }
    /** */
    static function fileName(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Name"] = $value;
        return $header;
    }
    /** */
    static function recived(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Recived"] = $value;
        return $header;
    }
    /** */
    static function waId(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."WaId"] = $value;
        return $header;
    }
    /** */
    static function idItem(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."IdItem"] = $value;
        return $header;
    }
    /** */
    static function idDB(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Iddb"] = $value;
        return $header;
    }
    /** */
    static function context(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Context"] = $value;
        return $header;
    }
    /** */
    static function ownSlug(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."OwnSlug"] = $value;
        return $header;
    }
    /** */
    static function cnxVer(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Router-Version"] = $value;
        return $header;
    }
    /** */
    static function source(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Source"] = $value;
        return $header;
    }
    /** */
    static function wamid(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Wamid"] = $value;
        return $header;
    }
    /** */
    static function setValue(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Value"] = $value;
        return $header;
    }
    /** */
    static function campoValor(array $header, String $campo, String $value): array
    {
        $header[HeaderDto::$PREF.$campo] = $value;
        return $header;
    }
    /** */
    static function anetKey(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."Key"] = $value;
        return $header;
    }
    /** */
    static function sendedidAnet(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."sndidanet"] = $value;
        return $header;
    }
    /** */
    static function sendedWamid(array $header, String $value): array
    {
        $header[HeaderDto::$PREF."sndwamid"] = $value;
        return $header;
    }
}
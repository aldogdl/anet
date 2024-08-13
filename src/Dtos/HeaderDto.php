<?php

namespace App\Dtos;

class HeaderDto {

    static String $PREF = 'Anet-';

    /** */
    static function includeBody(array $header, bool $includeBody): array
    {
        $header[HeaderDto::$PREF."Down"] = ($includeBody) ? 0 : 1;
        return $header;
    }
    /** */
    static function event(array $header, String $eventName): array
    {
        $header[HeaderDto::$PREF."Event"] = $eventName;
        return $header;
    }
    /** */
    static function fileName(array $header, String $fileName): array
    {
        $header[HeaderDto::$PREF."Name"] = $fileName;
        return $header;
    }
    /** */
    static function recived(array $header, String $recived): array
    {
        $header[HeaderDto::$PREF."Recived"] = $recived;
        return $header;
    }
    /** */
    static function waId(array $header, String $waId): array
    {
        $header[HeaderDto::$PREF."WaId"] = $waId;
        return $header;
    }
    /** */
    static function idItem(array $header, String $idItem): array
    {
        $header[HeaderDto::$PREF."IdItem"] = $idItem;
        return $header;
    }
    /** */
    static function ownSlug(array $header, String $ownSlug): array
    {
        $header[HeaderDto::$PREF."OwnSlug"] = $ownSlug;
        return $header;
    }
    /** */
    static function cnxVer(array $header, String $cnxVer): array
    {
        $header[HeaderDto::$PREF."Router-Version"] = $cnxVer;
        return $header;
    }

    /** */
    static function source(array $header, String $source): array
    {
        $header[HeaderDto::$PREF."Source"] = $source;
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
    static function anetKey(array $header, String $anetKey): array
    {
        $header[HeaderDto::$PREF."Key"] = $anetKey;
        return $header;
    }
}
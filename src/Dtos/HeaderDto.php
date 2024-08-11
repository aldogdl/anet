<?php

namespace App\Dtos;

class HeaderDto {

    /** */
    static function includeBody(array $header, bool $includeBody): array
    {
        $header["Anet-Down"] = ($includeBody) ? 0 : 1;
        return $header;
    }
    /** */
    static function event(array $header, String $eventName): array
    {
        $header["Anet-Event"] = $eventName;
        return $header;
    }
    /** */
    static function fileName(array $header, String $fileName): array
    {
        $header["Anet-Name"] = $fileName;
        return $header;
    }
    /** */
    static function recived(array $header, String $recived): array
    {
        $header["Anet-Recived"] = $recived;
        return $header;
    }
    /** */
    static function waId(array $header, String $waId): array
    {
        $header["Anet-WaId"] = $waId;
        return $header;
    }
    /** */
    static function idItem(array $header, String $idItem): array
    {
        $header["Anet-IdItem"] = $idItem;
        return $header;
    }
    /** */
    static function ownSlug(array $header, String $ownSlug): array
    {
        $header["Anet-OwnSlug"] = $ownSlug;
        return $header;
    }
    /** */
    static function cnxVer(array $header, String $cnxVer): array
    {
        $header["Anet-Router-Version"] = $cnxVer;
        return $header;
    }

    /** */
    static function source(array $header, String $source): array
    {
        $header["Anet-Source"] = $source;
        return $header;
    }
    /** */
    static function anetKey(array $header, String $anetKey): array
    {
        $header["Anet-Key"] = $anetKey;
        return $header;
    }
}
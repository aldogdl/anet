<?php

namespace App\Dtos;

class HeaderDto {

    /** */
    static function event(array $header, String $eventName): array
    {
        $header["Anet-Event"] = $eventName;
        return $header;
    }
    /** */
    static function ssePath(array $header, String $ssePath): array
    {
        $header["Anet-Sse"] = $ssePath;
        return $header;
    }
    /** */
    static function metaPath(array $header, String $metaPath): array
    {
        $header["Anet-Meta"] = $metaPath;
        return $header;
    }
    /** */
    static function idItem(array $header, String $idItem): array
    {
        $header["Anet-IdItem"] = $idItem;
        return $header;
    }
    /** */
    static function cnxVer(array $header, String $cnxVer): array
    {
        $header["Anet-Router-Version"] = $cnxVer;
        return $header;
    }
}
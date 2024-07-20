<?php

namespace App\Dtos;

class HeaderDto {

    /** */
    static function down(array $header, bool $down): array
    {
        $header["Anet-Down"] = $down;
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
    static function sessFtp(array $header, String $cnxVer): array
    {
        $header["Anet-ftp-open"] = $cnxVer;
        return $header;
    }
}
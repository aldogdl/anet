<?php

namespace App\Service;

use App\Dtos\HeaderDto;

class HeaderItem {

    public function build(array $data) : array
    {
        $head = [];
        $head['header'] = HeaderDto::event([], $data['type']);
        $head['header'] = HeaderDto::idDB($head['header'], $data['id']);
        $head['header'] = HeaderDto::idItem($head['header'], $data['idItem']);
        $head['header'] = HeaderDto::ownSlug($head['header'], $data['ownSlug']);
        $head['header'] = HeaderDto::waId($head['header'], $data['ownWaId']);
        $head['header'] = HeaderDto::campoValor($head['header'], 'thumbnail', $data['thumbnail']);
        $head['header'] = HeaderDto::source($head['header'], $data['source']);
        $head['header'] = HeaderDto::includeBody($head['header'], false);
        $head['header'] = HeaderDto::campoValor($head['header'], 'checkinSR', $data['checkinSR']);

        return $head;
    }
}
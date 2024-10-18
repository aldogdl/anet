<?php

namespace App\Service\AnetTrack;

use App\Dtos\WaMsgDto;
use App\Service\AnetTrack\WaSender;
use App\Repository\ItemsRepository;

class ConvertCotToItem {

    private ItemsRepository $itemsEM;
    private WaSender $waSender;
    private WaMsgDto $waMsg;
    private array $bait;

    /** */
    public function __construct(ItemsRepository $itemsRepository, WaSender $waS, WaMsgDto $msg, array $bait)
    {
        $this->waSender = $waS;
        $this->waMsg = $msg;
        $this->bait = $bait;
        $this->waSender->setConmutador($this->waMsg);
        $this->itemsEM = $itemsRepository;
    }

    /** */
    public function parse()
    {
        $this->itemsEM->convertCotToItem($this->waMsg->toArray(), $this->bait);
    }
}
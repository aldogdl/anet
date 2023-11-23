<?php

namespace App\Service\ShopCore;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataSimpleMlm {

    private $params;

	public function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
	}
	
    /** */
    public function getCode(String $slug) : array {

        $newRes = [];
        $pathTo = $this->params->get('anetMlm');
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
            $tks  = $this->getTks($slug, false);
            $newRes = array_merge($data, $tks);

        }
        return ['deco' => base64_encode(json_encode($newRes))];
    }
	
    /** */
    public function getTks(String $slug, bool $compress = true) : array {

        $pathTo = $this->params->get('dtaCtc') . $slug . '.json';
        if(is_file($pathTo)) {

            $res = '';
            $data = json_decode(file_get_contents($pathTo), true);
            if($data) {
                $data = [
                    'tokMlm' => $data['tokMlm'],
                    'mlmRef' => $data['mlmRef'],
                    'mlmKdk' => $data['mlmKdk'],
                    'refKdk' => $data['refKdk'],
                ];
                if(!$compress) { return $data; }
                $res = json_encode($data);
            }
        }
        return ['deco' => base64_encode($res)];
    }
	
    /** */
    public function setTks(String $slug, array $newDt) {

        $pathTo = $this->params->get('dtaCtc') . $slug . '.json';
        if(is_file($pathTo)) {

            $data = json_decode(file_get_contents($pathTo), true);
            if($data) {
                $data['tokMlm'] = $newDt['tokMlm'];
                $data['mlmRef'] = $newDt['mlmRef'];
                $data['mlmKdk'] = $newDt['mlmKdk'];
                $data['refKdk'] = $newDt['refKdk'];
                file_put_contents($pathTo, json_encode($data));
            }
        }
    }
}
<?php

namespace App\Service\AnetShop;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataSimpleMlm {

    private $params;

	public function __construct(ParameterBagInterface $container)
	{
		$this->params = $container;
	}
	
    /** */
    public function getCode(String $slug) : array {
        
        $pathTo = $this->params->get('anetMlm');
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
            $alls = $this->getDataLoks($slug, false);
            if($alls) {
                $data = array_merge($data, $alls);
            }
            return ['deco' => base64_encode(json_encode($data))];
        }
        return [];
    }
	
    /** */
    public function getDataLoks(String $slug, bool $compress = true) : array {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo .'/'. $slug . '.json';
        if(is_file($pathTo)) {

            $res = '';
            $data = json_decode(file_get_contents($pathTo), true);
            if($data) {
                if(!$compress) { return $data; }
                $res = json_encode($data);
                return ['deco' => base64_encode($res)];
            }
        }
        return [];
    }
	
    /** */
    public function getDataLoksUserTest() : array {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo .'/test_mlm.json';
        if(is_file($pathTo)) {
            $res = '';
            $data = json_decode(file_get_contents($pathTo), true);
            if($data) {
                return ['deco' => base64_encode($res)];
            }
        }
        return [];
    }
	
    /** */
    public function setTksMlm(String $slug, array $newDt) {

        $data = [];
        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        
        $pathTo = $pathTo .'/'. $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
            $data['tokMlm'] = $newDt['tokMlm'];
            $data['mlmRef'] = $newDt['mlmRef'];
            $data['mlmKdk'] = $newDt['mlmKdk'];
            $data['refKdk'] = $newDt['refKdk'];
            file_put_contents($pathTo, json_encode($data));
        }
    }
    
    /** */
    public function setThePass(String $slug, array $newDt): void {

        $data = [];
        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        $pathTo = $pathTo .'/'. $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
        }
        $data['pass'] = $newDt['pass'];
        file_put_contents($pathTo, json_encode($data));
    }

    /** */
    public function setTksMsg(String $slug, array $newDt) {

        $data = [];
        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        $pathTo = $pathTo .'/'. $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
        }
        $data['tokMess'] = $newDt['tokMess'];
        file_put_contents($pathTo, json_encode($data));
    }

    /** */
    public function setTksWeb(String $slug, array $newDt) {

        $data = [];
        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        $pathTo = $pathTo .'/'. $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
        }
        $data['tokWeb'] = $newDt['tokWeb'];
        file_put_contents($pathTo, json_encode($data));
    }

    /** */
    public function getDataContact(String $slug): array
    {
        $pathTo = $this->params->get('dtaCtc') . $slug . '.json';
        if(is_file($pathTo)) {
            $data = file_get_contents($pathTo);
            if($data) {
                return json_decode($data, true);
            }
        }
        return [];
    }
    
    /** */
    public function setDataContact(String $slug, array $newData) {

        $pathTo = $this->params->get('dtaCtc') . $slug . '.json';
        if(is_file($pathTo)) {
            file_put_contents($pathTo, json_encode($newData));
        }
    }

}
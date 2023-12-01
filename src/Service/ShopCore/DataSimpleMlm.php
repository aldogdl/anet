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
            $tks  = $this->getTksMlm($slug, false);
            if($tks) {
                $newRes = array_merge($data, $tks);
            }
        }
        return ['deco' => base64_encode(json_encode($newRes))];
    }
	
    /** */
    public function getTksMlm(String $slug, bool $compress = true) : array {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo . $slug . '.json';
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
    public function setTksMlm(String $slug, array $newDt) {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }

        $pathTo = $pathTo . $slug . '.json';
        file_put_contents($pathTo, json_encode($newDt));
    }

    /** */
    public function setThePass(String $slug, array $newDt) {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        $pathTo = $pathTo . $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
        }
        if($data) {
            $data['pass'] = $newDt['pass'];
            file_put_contents($pathTo, json_encode($data));
        }
    }

    /** */
    public function setTksMsg(String $slug, array $newDt) {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        $pathTo = $pathTo . $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
        }
        if($data) {
            $data['tokMess'] = $newDt['tokMess'];
            file_put_contents($pathTo, json_encode($data));
        }
    }

    /** */
    public function setTksWeb(String $slug, array $newDt) {

        $pathTo = $this->params->get('dtaCtcLog');
        if(!is_dir($pathTo)) {
            mkdir($pathTo);
        }
        $pathTo = $pathTo . $slug . '.json';
        if(is_file($pathTo)) {
            $data = json_decode(file_get_contents($pathTo), true);
        }
        if($data) {
            $data['tokWeb'] = $newDt['tokWeb'];
            file_put_contents($pathTo, json_encode($data));
        }
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
    public function setDataContact(String $slug, String $newData) {

        $pathTo = $this->params->get('dtaCtc') . $slug . '.json';
        if(is_file($pathTo)) {
            file_put_contents($pathTo, json_encode(json_decode($newData, true)));
        }
    }

}
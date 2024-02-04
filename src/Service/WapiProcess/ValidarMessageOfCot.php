<?php

namespace App\Service\WapiProcess;

use Symfony\Component\Finder\Finder;
use App\Entity\WaMsgMdl;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\WapiProcess\WrapHttp;

class ValidarMessageOfCot {

    public int $code = 100;
    public bool $isValid = false;
    public bool $isEmptyDetalles = false;
    
    public array $paths = [];
    public array $cotProgress = [];
    private Filesystem $filesystem;
    private WrapHttp $wapiHttp;
    private ExtractMessage $message;

    /**
     * Palabras claves para validar los destalles
     */
    private array $conj = [
        'así', 'asi', 'bien', 'bueno', 'como', 'con', 'cómo', 'contra', 'cuando', 'donde', 'de', 'el', 'en',
        'espero', 'está', 'esta', 'fuera', 'igual', 'foto', 'la', 'las', 'lo', 'los', 'mas', 'mientras',
        'mismo', 'ni', 'no', 'ora', 'otra', 'otro', 'pero', 'porque', 'que', 'solo', 'sino',
        'sea', 'tanto', 'también', 'tambien', 'un', 'una', 'unas', 'uno', 'unos', 'vien', 'ya'
    ];
    /** */
    private $permitidas = ['jpeg', 'jpg', 'webp', 'png'];
    /** 
     * Analizamos los mensajes para detectar errores, y como se condiciona cada
     * mensaje para determinar su tipo, evitamos hacerlo doble con la variable $code
    */
    public function __construct(
        ExtractMessage $obj, WrapHttp $wapi, array $paths, array $cotProgress
    ){

        $this->paths       = $paths;
        $this->cotProgress = $cotProgress;
        $this->wapiHttp    = $wapi;
        $this->message     = $obj;
    }

    /** */
    public function validate()
    {
        $this->filesystem  = new Filesystem();
        $msg = $this->message->get();

        if(count($this->cotProgress) > 0) {

            if(is_array($msg->message)) {
                if(!array_key_exists('idItem', $msg->message)) {
                    // Si existe el campo body en el mensaje, sacamos el msg de body
                    // ya que lo vamos a colocar otra ves dentro de body, para no duplicar
                    // el campo body.
                    if(array_key_exists('body', $msg->message)) {
                        $msg->message = $msg->message['body'];
                    }
                    $msg->message = [
                        'idItem' => $this->cotProgress['idItem'],
                        'body' => $msg->message
                    ];
                }
            }else{
                $msg->message = [
                    'idItem' => $this->cotProgress['idItem'],
                    'body' => $msg->message
                ];
            }
        }else{
            if(is_array($msg->message)) {
                if(!array_key_exists('idItem', $msg->message)) {
                    return false;
                }
            }
        }

        // A esta altura es obligatorio tener el idItem que se esta o quiere cotizar
        // Ya sea por una cotizacion en progreso o por un boton interactivo
        $trackFile = new TrackFileCot($msg, $this->paths, null, true);

        if($trackFile->isAtendido) {
            $trackFile->fSys->setPathBase($this->paths['waTemplates']);
            $template = $trackFile->fSys->getContent('eatn.json');
            $conm = new ConmutadorWa($msg->from, $this->paths['tkwaconm']);
            $conm->setBody($template['type'], $template);
            $this->wapiHttp->send($conm);
            $this->isValid  = false;
            return;
        }

        if($this->message->isDoc) {
            $template = $this->buildMsgSimple(
                "*LO SENTIMOS MUCHO*.\n\n📝Por el momento solo Imágenes y/o Texto acepta el Sistema automatizado."
            );
            $this->sentMsg($template, $msg->from);
            $this->isValid  = false;
            return;
        }

        $this->isValid  = true;
        if($this->message->isInteractive) {
            $this->validateInteractive($msg, $trackFile);
            return;
        }
        
        if(!array_key_exists('current', $this->cotProgress)) {
            $this->isValid  = false;
            return false;
        }

        $this->code = 101;
        if($this->cotProgress['current'] == 'sfto' && !$this->message->isImage) {
            $template = $this->buildMsgSimple(
                "*DISCULPA pero...*.\n\n📸 El sistema está esperando *Fotografias* de la Autoparte."
            );
            $this->sentMsg($template, $msg->from);
            $this->isValid  = false;
            return;
        }

        if($this->cotProgress['current'] == 'sfto' && $this->message->isImage) {
            $this->validateImage($msg);
            return;
        }

        if($this->cotProgress['current'] == 'sdta' && $this->message->isImage) {
            $this->validateImage($msg);
            return;
        }

        $this->code = 102;
        if($this->cotProgress['current'] == 'sdta' && $this->message->isText) {
            $this->validateText($msg);
            return;
        }

        if($this->cotProgress['current'] == 'scto' && $this->message->isText) {
            $this->validateText($msg);
            return;
        }
    }

    /** */
    private function validateInteractive(WaMsgMdl $msg, TrackFileCot $trackFile): void
    {
        // Creamos el archivo de cotizacion en progreso si es necesario
        if(!$this->paths['hasCotPro'] && count($this->cotProgress) == 0) {
            if($msg->subEvento == 'sfto') {
                $trackFile->build();
                if(count($trackFile->baitProgress) == 0) {
                    $template = $this->buildMsgSimple(
                        "*LO SENTIMOS MUCHO*.\n\n📝La cotización que deseas atender ya no esta disponible, pronto te enviaremos más.😃👍"
                    );
                    $this->sentMsg($template, $msg->from);
                    $this->isValid  = false;
                    return;
                }
                $trackFile->saveFileTrackProcess($trackFile->baitProgress);
                $this->cotProgress = $trackFile->baitProgress;
                $this->isValid = true;
                return;
            }
        }

        // El mensaje recibido es que... No agregará fotos
        if($msg->subEvento == 'nfto') {
            // Enviamos el mensaje de confirmacion de sin fotos
            $template = $this->getFile($msg->subEvento.'.json');
            $deco = new DecodeTemplate($this->cotProgress);
            $template = $deco->decode($template);
            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
            return;
        }
        
        // El mensaje recibido es que... Que se arrepintio y agregara fotos
        if($msg->subEvento == 'sifto') {
            // Enviamos el mensaje de buena elenccion agrega fotos
            $template = $this->getFile($msg->subEvento.'.json');
            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
            return;
        }
    }

    /** */
    private function validateImage(WaMsgMdl $msg): void
    {
        $this->isValid = true;
        if(!in_array($msg->status, $this->permitidas)) {
            $template = $this->getFile('eftoExt.json');
            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
            return;
        }
        
        if($this->cotProgress['current'] == 'sfto') {
            return;
        }
        // Si es deep viene de la condicion dende ya se envio el msg de detalles pero sigue
        // enviando fotos, por lo tanto, es necesario calcular si hay que enviarle otro msg
        // para recordarle en que paso va (detalles)
        $save = false;
        $cant = 1;
        $last = -1;
        $finder = new Finder();
        
		$finder->files()->in($this->paths['cotProgres'])->name($msg->from .'*.imgs');
		if($finder->hasResults()) {
			foreach ($finder as $file) {
				$files = $file->getRelativePathname();
                $partes = explode('_', $files);
                try {
                    $cantFile = (integer) $partes[1];
                    $cant = ($cant > $cantFile) ? $cant : $cantFile;
                    $lastFile = (integer) $partes[2];
                    $last = ($cant > $lastFile) ? $cant : $lastFile;
                } catch (\Throwable $th) {
                    $cant = 0;
                    $last = -1;
                }
                unlink($file->getRealPath());
			}

            if($last > -1) {

                // Si la ultima ves que se recibió una img han pasado mas de 5 segundos
                // quiere decir que nos esta enviando las fotos 1 a 1.
                $diff = time() - $last;
                $avisar = ($diff > 5) ? true : false;
                
                $limitForScreen = 4;
                $isEvent = ($cant % 2 == 0) ? true : false;
                // Si la cantidad de fotos supera los 3 revisamos para ver si son mas de 6
                if($avisar) {
                    if($cant > $limitForScreen && $isEvent) {
                        $avisar = false;
                    }
                    if($cant > 1 && $cant < $limitForScreen && $isEvent) {
                        $avisar = false;
                    }
                }

                if($avisar) {
                    $template = $this->buildMsgSimple(
                        "*Hemos recibido correctamente tus fotografías*.\n\n📝Si ház finalizado de enviar fotos.\nPor favor indicanos los *DETALLES de la pieza*"
                    );
                    $this->sentMsg($template, $msg->from);
                    // Despues de enviar el aviso de las fotos volvemos a inicial el conteo
                    $this->removeFileImgs($msg->from);
                }else{
                    $save = true;
                }
            }
		}else{
            $save = true;
        }

        if($save) {
            $cant = $cant + 1;
            $filename = $msg->from.'_'.$cant.'_'.time().'_.imgs';
            file_put_contents($this->paths['cotProgres'].'/'.$filename, '');
        }
    }

    /** */
    public function removeFileImgs(String $waId)
    {
        $finder = new Finder();
        $finder->files()->in($this->paths['cotProgres'])->name($waId.'*.imgs');
		if($finder->hasResults()) {
			foreach ($finder as $file) {
				unlink($file->getRealPath());
			}
        }
    }

    /** */
    private function validateText(WaMsgMdl $msg): void
    {
        if($msg->type != 'text') {
            // TODO enviar error al cliente
            return;
        }

        $campo = $this->cotProgress['current'];
        $valor = '';
        if(array_key_exists('body', $msg->message)) {
            $valor = $msg->message['body'];
        }else{
            $valor = $msg->message;
        }

        $isValid = $this->isValid($campo, $valor);
        if(!$isValid) {

            if($campo == 'sdta') {
                $template = $this->getFile('edta.json');
            }

            if($campo == 'scto') {

                if($this->isEmptyDetalles) {
                    $this->cotProgress['current'] = 'sdta';
                    $this->cotProgress['next'] = 'scto';
                    file_put_contents($this->paths['cotProgres'].'/'.$msg->from.'.json', json_encode($this->cotProgress));
                    $template = $this->buildMsgSimple(
                        "*POR FAVOR...*.\n\n📝Indica algunos detalles de la pieza antes de continuar 🙂"
                    );
                    $this->sentMsg($template, $msg->from);
                    $this->isValid  = false;
                    return;
                }
                $template = $this->getFile('ecto.json');
            }

            $this->sentMsg($template, $msg->from);
            $this->isValid = false;
        }
    }
    
    /** */
    private function isValid(String $campo, String $data): bool
    {   
        if($campo == 'sdta') {
            if(strlen($data) < 3) {
                return false;
            }
            $data = mb_strtolower($data);
            $partes = explode(' ', $data);
            $palabras = count($partes);
            if(strlen($data) > 9 && $palabras == 1) {
                return false;
            }

            if(strlen($data) < 9 && $palabras == 1) {

                $vocales = ['a', 'e', 'i', 'o', 'u'];
                $rota = count($vocales);
                $hasVocales = false;
                $hasConso = false;
                for ($i=0; $i < $rota; $i++) { 
                    if(mb_strpos($data, $vocales[$i]) !== false) {
                        $hasVocales = true;
                        break;
                    }
                }
                if($hasVocales) {
                    $conso = ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'w', 'x', 'y', 'z'];
                    $rota = count($conso);
                    for ($i=0; $i < $rota; $i++) { 
                        if(mb_strpos($data, $vocales[$i]) !== false) {
                            $hasConso = true;
                            break;
                        }
                    }
                }
                return ($hasVocales && $hasConso) ? true : false;
            }
            $rota = count($this->conj);
            $data = strtolower($data);
            $data = trim($data);
            for ($i=0; $i < $rota; $i++) { 
                if(mb_strpos($data, $this->conj[$i]) !== false) {
                    return true;
                }
            }
            return false;
        }

        if($campo == 'scto') {

            $this->isEmptyDetalles = false;
            if(!array_key_exists('detalles', $this->cotProgress['track'])) {
                $this->isEmptyDetalles = true;
            }else{
                if(mb_strlen($this->cotProgress['track']['detalles']) < 3) {
                    $this->isEmptyDetalles = true;
                }
            }
            if($this->isEmptyDetalles) {
                return false;
            }

            if(strlen($data) < 3) {
                return false;
            }

            return $this->isValidNumero($data);
        }

        return true;
    }

    /**
     * Solo es necesario revisar el costo para saber si estan enviando un numero
     * o letras para indicar este valor.
     */
    public function isValidNumero(String $data) : bool
    {
        $result = $data;
        $str = mb_strtolower($result);
        if(mb_strpos($str, 'mil') !== false) {
            return false;
        }

        $str = str_replace('$', '', $str);
        $str = str_replace(',', '', $str);

        if(mb_strpos($str, '.') !== false) {

            $partes = explode('.', $str);
            $entera = $this->isDigit($partes[0]);
            if($entera != '-1') {
                $decimal = $this->isDigit($partes[1]);
                if($decimal != '-1') {
                    return true;
                }
            }
        }

        $entera = $this->isDigit($str);
        if($entera != '-1') {
            return true;
        }
        return false; 
    }

    /**
     * Checamos si el valor dado es un numero.
     */
    private function isDigit(String $value) : String
    {
        $value = preg_replace('/[^0-9]/', '', $value);
        if(strlen($value) > 2) {
            if(is_int($value) || ctype_digit($value)) {
                return $value;
            }
        }
        return '-1';
    }

    /** */
    public function buildMsgSimple(String $text): array
    {
        return [
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text
            ]
        ];
    }

    /** */
    public function sentMsg(array $template, String $to, bool $withContext = false)
    {
        if(count($template) > 0) {

            if($withContext) {
                if(array_key_exists('wamid_cot', $this->cotProgress)) {
                    $template['context'] = $this->cotProgress['wamid_cot'];
                }
            }
            $conm = new ConmutadorWa($to, $this->paths['tkwaconm']);
            $conm->setBody($template['type'], $template);
            $result = $this->wapiHttp->send($conm);
            if($result['statuscode'] != 200) {
                // TODO Hacer archivo de registros de errores
                return;
            }
        }
    }

    /** */
    private function getFile(String $filename): array
    {
        $path = $this->paths['waTemplates'].'/'.$filename;
        if($this->filesystem->exists($path)) {
            try {
                $tpl = file_get_contents($path);
                if(strlen($tpl) > 0) {
                    return json_decode($tpl, true);
                }
            } catch (\Throwable $th) {}
        }
        // TODO tener un msg template de error desconocido
        return [];
    }

}
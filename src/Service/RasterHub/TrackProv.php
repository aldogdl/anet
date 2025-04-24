<?php

namespace App\Service\RasterHub;

use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\WaSender;
use App\Service\RasterHub\TemplatesTrack;
use App\Service\Pushes;

/**
 * El sistema de rastreo por medio de notificacions para los
 * proveedores.
 */
class TrackProv {

  private array $data;
  private array $contacts;
  private ?Pushes $push;
  private WaSender $waS;

  /** */
  public function __construct(?Pushes $push, WaSender $waS, array $data, array $contacs)
  {
    $this->data = $data;
    $this->contacts = $contacs;
    if($push != null) {
      $this->push = $push;
    }
    $this->waS = $waS;
  }

  /** 
   * Construimos el mensaje y lo enviamos a los contactos
   * @see PostController/sentNotification
  */
  public function builderTrack(String $folderToBackup, String $folderFails) : array 
  {
    $result = ['abort' => true, 'msg' => ''];
    $idSendFile = $this->data['type'] .'::'. $this->data['id'] .'::'.round(microtime(true) * 1000);
    $filename = $folderToBackup . $idSendFile. '.json';

    // Enviamos el mensaje via PUSH a los contactos
    if($this->data['type'] == 'publica') {
      if(array_key_exists('srcIdDbSr', $this->data)) {
        if($this->data['srcIdDbSr'] != 0) {
          // Enviar un mensaje de cotizacion solo al solicitante
          // y al cotizador para aprovechar el push y sincronizar
        }
      }
      return ['abort' => false, 'msg' => 'ok'];
    }

    $result = $this->push->sendToTopic($this->data, 'buscar');
    return ['abort' => false, 'msg' => 'ok'];
    
    if(array_key_exists('slug', $this->contacts)) {
      // Si contiene slug, significa que se le enviará el msg a 1 persona
      $this->data['srcSlug'] = $this->contacts['slug'];
    }

    // Guardamos el mensaje en el folder fb_sended, esta info es usada en el metodo:
    // sentResponseByAction para recuperar los ids del item y enviar la respuesta segun
    // la accion del usuario por medio de los botones del mensaje.
    file_put_contents($filename, json_encode($this->data));

    $this->data['tokens'] = $this->contacts['tokens'];
    $this->data['waIds'] = $this->contacts['waIds'];
    $this->contacts = [];
    
    $this->data['cant'] = count($this->data['tokens']);
    if($this->data['cant'] == 0) {
      $result = ['abort' => true, 'msg' => 'X Sin contactos'];
    }else{

      // Enviamos el mensaje via PUSH a los contactos
      $result = $this->push->sendMultiple($this->data);

      if(array_key_exists('fails', $result)) {
        $filename = $folderFails .
        $this->data['type'] .'_'. round(microtime(true) * 1000) . '.json';
        $this->data['fails'] = $result['fails'];
        // Guardamos el mensaje en el folder fb_fails
        file_put_contents($filename, json_encode($this->data));
        unset($result['fails']);
      }

      // En caso de que la imagen portada se halla indexado correctamente
      // en los servidores de Whatsapp, enviamos el mensaje via Whatsapp a los contactos
      if(array_key_exists('idwap', $this->data)) {
        // Si contiene el id de la imagen que se envio a whatsapp
        // lo enviamos por ese medio
        $this->sendToWhatsapp($idSendFile);
      }else{
        // Si no se logró enviar la imagen a whatsapp que hacer?
      }
    }

    return $result;
  }

  /** 
   * Metodo para enviarle al usuario el mensaje de link cuando quiere cotizar
   * una pieza, esta accion fue por presionar un boton entre [ Directamente | Formulario ]
   * [NOTA] El mensaje inicial fue enviado en la clase: PostController::sentNotification
  */
  public function sentResponseByAction(WaMsgDto $msg) : void 
  {
    $folderToBackup = $this->waS->fSys->getFolderTo('fbSended');
    $filename = $msg->idDbSr;
    if(!mb_strpos($filename, '::')) {
      // TODO Mensaje al Cotizador acerca de:
      // El obj $msg no contiene el nomnre del archivo donde estan los datos
      // de la pieza a cotizar, la cual debe estar en: /public_html/fb_sended
      return;
    }

    $file = json_decode(file_get_contents($folderToBackup.'/'.$filename.'.json'), true);
    if($file == null || count($file) == 0) {
      return;
    }

    if(!array_key_exists('ownWaId', $file)) {
      // TODO No existe el campo del waId del Emisor
      return;
    }

    $this->waS->initConmutador();
    if($this->waS->conm == null) {
      return;
    }

    $waIdTo = $this->waS->conm->waIdToPhone($file['ownWaId']);

    $tmp = new TemplatesTrack();
    $link = $tmp->buildTemplateLinkAction($msg, $waIdTo, $file);
    if(count($link) > 0) {
      $this->waS->setWaIdToConmutador($msg->from);
      $this->waS->sendPreTemplate($link);
    }
    return;
  }

  /** 
   * Enviamos el mensaje a los contactos por medio de Whatsapp
   * [NOTA] Este metodo fue llamado en el metodo: builderTrack
  */
  private function sendToWhatsapp(String $idFile): void
  {
    $rota = count($this->data['waIds']);
    if($rota == 0) {
      return;
    }
    
    $this->waS->initConmutador();
    if($this->waS->conm == null) {
      return;
    }
    
    $tmp = new TemplatesTrack();
    $tmplate = $tmp->forTrackOnlyBtnCotizar(
      $this->data['idwap'], $this->data['body'], $idFile, 'form'
    );
    for ($i=0; $i < $rota; $i++) {
      $this->waS->setWaIdToConmutador($this->data['waIds'][$i]);
      $this->waS->sendPreTemplate($tmplate);
    }
  }

}

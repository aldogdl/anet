<?php

namespace App\Service\WapiRequest;

use Symfony\Component\Filesystem\Filesystem;

class ProcessCMD {
    
    private Filesystem $filesystem;
    private String $pathToCmd;
    private String $waId;

    public function __construct(String $path)
    {
        $this->filesystem = new Filesystem();
        $this->pathToCmd = $path;
    }

    /** */
    private function getMotivos(String $clave): String {

        $motivos = [
            'conv_free' => 'Mientras esta abierta una sesión de conversación con un '.
            'Ejecutivo de Autoparnet, no es posible intercambiar comandos entre sistemas.'
        ];

        return $motivos[$clave];
    }

    /**
     * Revisamos si hay un archivo cmd del usuario
     * $prefix puede ser _ok para revisar si existe un archivo procesado
     */
    public function hasFileCmd(String $waId, String $prefix = ''): bool
    {
        $this->waId = $waId;
        return $this->filesystem->exists($this->pathToCmd.'/'.$waId.$prefix.'.json');
    }

    /**
     * Recuperamos el archivo del comando procesado del usuario
     */
    public function getFileProcesadoCmd(): array
    {
        $filename = $this->pathToCmd.'/'.$this->waId.'_ok.json';
        $has = $this->filesystem->exists($filename);
        if($has) {
            return json_decode( file_get_contents($filename), true);
        }
        return [];
    }

    
    /**
     * Los Status enviados por whatsapp contienen el tiempo de expiración
     * de la ventana de sesion, los esperamos y guardamos en el archivo.
     */
    public function setProcessOk(array $msg): array
    {
        $path = $this->pathToCmd.'/'.$this->waId;
        unlink($path.'.json');
        file_put_contents($path.'_ok.json', json_encode($msg));
        return [];
    }

    /**
     * En caso de que no sea posible la execución de un comando guardamos en el archivo
     * de éste, el motivo de su negación
     */
    public function denegarMotivo(String $clavemotivo): void
    {
        $motivo = $this->getMotivos($clavemotivo);
        $path = $this->pathToCmd.'/'.$this->waId;
        unlink($path.'.json');
        file_put_contents($path.'_ok.json', json_encode([
            'motivo' => $motivo
        ]));
    }

}
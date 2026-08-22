<?php

namespace App\Service\Any;

use App\Repository\NextSellerRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExpedienteManager
{
    private ParameterBagInterface $params;
    private Fsys $fsys;
    private NextSellerRepository $nextSellerRepo;
    private Filesystem $filesystem;

    public function __construct(
        ParameterBagInterface $params,
        Fsys $fsys,
        NextSellerRepository $nextSellerRepo
    ) {
        $this->params = $params;
        $this->fsys = $fsys;
        $this->nextSellerRepo = $nextSellerRepo;
        $this->filesystem = new Filesystem();
    }

    /**
     * Actualiza el expediente de una empresa de forma atómica y con merge inteligente por rol.
     *
     * @param string $slug Slug de la empresa
     * @param array $incomingData Datos enviados por el cliente
     * @param string|null $senderWaId waId del usuario que realiza la acción
     * @return array Expediente consolidado final
     * @throws \Exception
     */
    public function updateExpediente(string $slug, array $incomingData, ?string $senderWaId = null): array
    {
        $slug = trim($slug);
        if (empty($slug)) {
            throw new \InvalidArgumentException('Slug no proporcionado.');
        }

        $dirPath = Path::canonicalize($this->params->get(AnyPath::$DTACTC));
        if (!$this->filesystem->exists($dirPath)) {
            $this->filesystem->mkdir($dirPath, 0755);
        }

        $filePath = Path::canonicalize($dirPath . '/' . $slug . '.json');

        // Abrir archivo con bloqueo exclusivo atómico (flock)
        $fp = fopen($filePath, 'c+');
        if (!$fp) {
            throw new \RuntimeException("No se pudo abrir el archivo de expediente: $filePath");
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException("No se pudo obtener el bloqueo de archivo para: $slug.json");
        }

        try {
            // 1. Leer contenido actual en disco
            rewind($fp);
            $fileContent = stream_get_contents($fp);
            $currentExp = [];
            if (!empty($fileContent)) {
                $decoded = json_decode($fileContent, true);
                if (is_array($decoded)) {
                    $currentExp = $decoded;
                }
            }

            // 2. Gestionar caso especial de registro inicial
            if (isset($incomingData['registro'])) {
                $reg = $this->fsys->get(AnyPath::$REGAUTH, $slug . '.json');
                $this->fsys->del(AnyPath::$REGAUTH, $slug . '.json');
                unset($incomingData['registro']);
                if ($reg && isset($reg['asesor'])) {
                    $incomingData['asesor'] = $reg['asesor'];
                }
            }

            // 3. Evaluar y normalizar Plan y NIF (facturación protegida)
            $this->ensurePlanAndNif($currentExp, $incomingData);

            // 4. Determinar senderWaId
            if (empty($senderWaId)) {
                $senderWaId = $incomingData['senderWaId'] ?? $incomingData['waId'] ?? null;
            }
            if ($senderWaId !== null) {
                $senderWaId = (string)$senderWaId;
            }

            // 5. Determinar rol del emisor
            $isMain = $this->isSenderMain($currentExp, $senderWaId, $incomingData);

            // 6. Aplicar Merge Inteligente según Rol
            if ($isMain) {
                $currentExp = $this->applyMergeForMain($slug, $currentExp, $incomingData);
            } else {
                $currentExp = $this->applyMergeForColab($currentExp, $incomingData, $senderWaId);
            }

            // 7. Actualizar control de versión y timestamp
            $currentVersion = isset($currentExp['version']) && is_numeric($currentExp['version'])
                ? (int)$currentExp['version']
                : 0;
            $currentExp['version'] = $currentVersion + 1;
            $currentExp['updatedAt'] = (int) round(microtime(true) * 1000);

            // 8. Escribir atómicamente a disco
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($currentExp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);

            return $currentExp;

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Asegura y protege los campos de facturación (plan y nif).
     * Nadie en la app puede cambiarlos arbitrariamente; se rigen por reglas de servidor.
     */
    public function ensurePlanAndNif(array &$currentExp, array $incomingData): void
    {
        $now = new \DateTime('now');
        $oneMonthLater = (clone $now)->modify('+1 month');
        $defaultNif = $oneMonthLater->getTimestamp() * 1000;

        // Si no existen en el expediente actual ni en el incoming
        if (!isset($currentExp['plan']) || empty($currentExp['plan'])) {
            $currentExp['plan'] = 'pro';
            $currentExp['nif'] = $defaultNif;
            if (!isset($currentExp['desde'])) {
                $currentExp['desde'] = $now->getTimestamp() * 1000;
            }
        } elseif ($currentExp['plan'] === 'renova' || (isset($incomingData['plan']) && $incomingData['plan'] === 'renova')) {
            // Si está marcado para renovar
            $currentExp['plan'] = 'pro';
            $currentExp['nif'] = $defaultNif;
        }

        // Asegurar que nif exista
        if (!isset($currentExp['nif']) || (int)$currentExp['nif'] <= 0) {
            $currentExp['nif'] = $defaultNif;
        }
    }

    /**
     * Verifica si el emisor tiene rol ROLE_MAIN o si es una inicialización de expediente.
     */
    private function isSenderMain(array $currentExp, ?string $senderWaId, array $incomingData): bool
    {
        // Si el expediente actual no tiene colabs (ej. nuevo registro), se permite como inicialización MAIN
        if (empty($currentExp['colabs']) || !is_array($currentExp['colabs'])) {
            return true;
        }

        if (empty($senderWaId)) {
            // Fallback: si no se especifica senderWaId pero vienen datos globales, buscar en incomingData si hay colab main
            return false;
        }

        foreach ($currentExp['colabs'] as $colab) {
            $waId = isset($colab['waId']) ? (string)$colab['waId'] : '';
            if ($waId === $senderWaId) {
                $roles = isset($colab['roles']) && is_array($colab['roles']) ? $colab['roles'] : [];
                return in_array('ROLE_MAIN', $roles, true);
            }
        }

        return false;
    }

    /**
     * Aplica el merge para usuario con rol ROLE_MAIN (datos de empresa y administración de colabs).
     */
    private function applyMergeForMain(string $slug, array $currentExp, array $incomingData): array
    {
        // Campos globales de la empresa permitidos para MAIN
        $allowedCompanyFields = [
            'slug', 'empresa', 'logo', 'categoria', 'ynksmx', 'mlmId',
            'address', 'colonia', 'localidad', 'links', 'prestige',
            'anyChatId', 'anyInviteLink', 'asesor', 'desde'
        ];

        foreach ($allowedCompanyFields as $field) {
            if (array_key_exists($field, $incomingData)) {
                $currentExp[$field] = $incomingData[$field];
            }
        }

        // Asegurar que el slug esté presente
        $currentExp['slug'] = $slug;

        // Gestión y depuración de la lista de colaboradores
        if (isset($incomingData['colabs']) && is_array($incomingData['colabs'])) {
            $cleanedColabs = $this->nextSellerRepo->evalAndCleanColabs($slug, $incomingData['colabs']);
            $currentExp['colabs'] = $cleanedColabs;
        } elseif (!isset($currentExp['colabs'])) {
            $currentExp['colabs'] = [];
        }

        return $currentExp;
    }

    /**
     * Aplica el merge para un colaborador ordinario (ROLE_AVO / no-MAIN).
     * Solo permite mutar sus propios datos personales (nombre, fullName, foto, pass, taId).
     * Ignora cambios en stt, datos de empresa u otros colaboradores.
     */
    private function applyMergeForColab(array $currentExp, array $incomingData, ?string $senderWaId): array
    {
        if (empty($senderWaId) || empty($currentExp['colabs']) || !is_array($currentExp['colabs'])) {
            return $currentExp;
        }

        // Determinar los datos a actualizar del colaborador
        $colabPayload = null;

        // Si viene un array 'colabs' en incoming, buscar al senderWaId allí
        if (isset($incomingData['colabs']) && is_array($incomingData['colabs'])) {
            foreach ($incomingData['colabs'] as $c) {
                if (isset($c['waId']) && (string)$c['waId'] === $senderWaId) {
                    $colabPayload = $c;
                    break;
                }
            }
        }

        // Si no viene dentro de 'colabs', verificar si el incomingData mismo es el payload del perfil
        if ($colabPayload === null && isset($incomingData['waId']) && (string)$incomingData['waId'] === $senderWaId) {
            $colabPayload = $incomingData;
        }

        if ($colabPayload === null) {
            return $currentExp;
        }

        // Fusionar únicamente los campos permitidos del colaborador dentro de colabs
        $allowedColabFields = ['nombre', 'fullName', 'foto', 'pass', 'taId'];

        foreach ($currentExp['colabs'] as $idx => $existingColab) {
            $waId = isset($existingColab['waId']) ? (string)$existingColab['waId'] : '';
            if ($waId === $senderWaId) {
                foreach ($allowedColabFields as $field) {
                    if (array_key_exists($field, $colabPayload)) {
                        $currentExp['colabs'][$idx][$field] = $colabPayload[$field];
                    }
                }
                break;
            }
        }

        return $currentExp;
    }

}

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
        $lockPath = Path::canonicalize($dirPath . '/' . $slug . '.lock');

        // Abrir archivo de bloqueo dedicado y estable
        $lockFp = @fopen($lockPath, 'c+');
        if ($lockFp === false) {
            throw new \RuntimeException("No se pudo abrir el archivo de bloqueo: $lockPath");
        }

        if (!@flock($lockFp, LOCK_EX)) {
            @fclose($lockFp);
            throw new \RuntimeException("No se pudo obtener el bloqueo de archivo para: $slug.lock");
        }

        $tmpFile = null;

        try {
            // 1. Leer contenido actual en disco bajo bloqueo exclusivo
            $currentExp = [];
            if (file_exists($filePath)) {
                $fileContent = @file_get_contents($filePath);
                if ($fileContent !== false && trim($fileContent) !== '') {
                    $decoded = json_decode($fileContent, true);
                    if (is_array($decoded)) {
                        $currentExp = $decoded;
                    }
                }
            }

            $pendingRegAuthDelete = false;

            // 2. Gestionar caso de registro inicial y reintentos idempotentes
            if (!empty($incomingData['registro'])) {
                $incomingWaId = $senderWaId ?? $incomingData['waId'] ?? ($incomingData['colabs'][0]['waId'] ?? null);

                // Caso 2.A: El expediente ya existe y está consolidado en disco
                if (!empty($currentExp['colabs']) && is_array($currentExp['colabs'])) {
                    // Validar si es un reintento idempotente del fundador principal
                    $isOriginalFounder = false;
                    $normalizedIncoming = $incomingWaId ? $this->normalizeWaId((string)$incomingWaId) : '';
                    foreach ($currentExp['colabs'] as $existingColab) {
                        $cRoles = $existingColab['roles'] ?? [];
                        if (in_array('ROLE_MAIN', $cRoles, true)) {
                            $existingWa = $existingColab['waId'] ?? '';
                            if ($existingWa === $incomingWaId || ($normalizedIncoming !== '' && $this->normalizeWaId((string)$existingWa) === $normalizedIncoming)) {
                                $isOriginalFounder = true;
                                break;
                            }
                        }
                    }

                    if ($isOriginalFounder) {
                        // Reintento legítimo tras respuesta HTTP perdida: limpiar cualquier residuo de reg_auth y retornar expediente intacto
                        $this->fsys->del(AnyPath::$REGAUTH, $slug . '.json');
                        return $currentExp;
                    }

                    // Si no es el fundador original, no se permite usurpar una empresa ya registrada
                    throw new \RuntimeException('La empresa ya se encuentra registrada por otro usuario.');
                }

                // Caso 2.B: Registro inicial legítimo (expediente aún no existe)
                $reg = $this->fsys->get(AnyPath::$REGAUTH, $slug . '.json');
                if (!$reg || !isset($reg['msg']) || $reg['msg'] !== 'ok') {
                    throw new \RuntimeException('Registro no autorizado por un asesor.');
                }

                if (isset($reg['asesor'])) {
                    $incomingData['asesor'] = $reg['asesor'];
                }
                unset($incomingData['registro']);
                $pendingRegAuthDelete = true;
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

            // 8. Serializar y escribir atómicamente mediante archivo temporal
            $encoded = json_encode($currentExp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException("Error al serializar el expediente JSON para: $slug");
            }

            $tmpFile = tempnam($dirPath, 'exp_tmp_');
            if ($tmpFile === false) {
                throw new \RuntimeException("No se pudo crear archivo temporal para reemplazo atómico en: $dirPath");
            }

            $bytesWritten = @file_put_contents($tmpFile, $encoded);
            if ($bytesWritten === false || $bytesWritten !== strlen($encoded)) {
                throw new \RuntimeException("Escritura incompleta o fallida en archivo temporal: $tmpFile");
            }

            // Reemplazo atómico del archivo destino
            if (!@rename($tmpFile, $filePath)) {
                @unlink($filePath);
                if (!@rename($tmpFile, $filePath)) {
                    throw new \RuntimeException("Fallo al renombrar atómicamente el archivo temporal hacia $filePath");
                }
            }
            $tmpFile = null; // Reemplazo exitoso

            // 9. Consumo definitivo y seguro de la autorización SOLO tras confirmar persistencia atómica
            if ($pendingRegAuthDelete) {
                $this->fsys->del(AnyPath::$REGAUTH, $slug . '.json');
            }

            return $currentExp;

        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            if (is_resource($lockFp)) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
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

        $normalizedSender = $this->normalizeWaId($senderWaId);
        foreach ($currentExp['colabs'] as $colab) {
            $waId = isset($colab['waId']) ? (string)$colab['waId'] : '';
            if ($waId === $senderWaId || $this->normalizeWaId($waId) === $normalizedSender) {
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
            'slug', 'empresa', 'logo', 'categoria', 'accountType', 'ynksmx', 'mlmId',
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

        // Gestión y depuración de la lista de colaboradores con preservación de contraseñas
        if (isset($incomingData['colabs']) && is_array($incomingData['colabs'])) {
            // 1. Indexar colaboradores existentes en disco por waId normalizado
            $existingColabsByWa = [];
            if (!empty($currentExp['colabs']) && is_array($currentExp['colabs'])) {
                foreach ($currentExp['colabs'] as $ec) {
                    if (is_array($ec) && isset($ec['waId'])) {
                        $normWa = $this->normalizeWaId((string)$ec['waId']);
                        if ($normWa !== '') {
                            $existingColabsByWa[$normWa] = $ec;
                        }
                    }
                }
            }

            // 2. Reconciliar lista entrante: si pass es vacío/null/ausente, conservar el pass existente del servidor
            $reconciledIncomingColabs = [];
            foreach ($incomingData['colabs'] as $incColab) {
                if (!is_array($incColab)) {
                    continue;
                }
                $incWa = isset($incColab['waId']) ? (string)$incColab['waId'] : '';
                $normIncWa = $this->normalizeWaId($incWa);

                $incomingPass = isset($incColab['pass']) ? trim((string)$incColab['pass']) : '';

                if ($normIncWa !== '' && isset($existingColabsByWa[$normIncWa])) {
                    $existingColab = $existingColabsByWa[$normIncWa];
                    $existingPass = isset($existingColab['pass']) ? trim((string)$existingColab['pass']) : '';

                    // Conservar pass si el entrante no trae un valor no vacío explícito
                    if ($incomingPass === '' && $existingPass !== '') {
                        $incColab['pass'] = $existingColab['pass'];
                    } elseif ($incomingPass !== '') {
                        $incColab['pass'] = $incomingPass;
                    } else {
                        unset($incColab['pass']);
                    }
                } else {
                    // Nuevo colaborador: registrar solo si trae pass no vacío
                    if ($incomingPass !== '') {
                        $incColab['pass'] = $incomingPass;
                    } else {
                        unset($incColab['pass']);
                    }
                }

                $reconciledIncomingColabs[] = $incColab;
            }

            $cleanedColabs = $this->nextSellerRepo->evalAndCleanColabs($slug, $reconciledIncomingColabs);
            $currentExp['colabs'] = $cleanedColabs;
        } elseif (!isset($currentExp['colabs'])) {
            $currentExp['colabs'] = [];
        }

        // Si el MAIN envía datos personales SIN array colabs (ej. taId, pass, foto),
        // aplicar merge de su propio perfil como lo haría un colab ordinario
        $personalFields = ['taId', 'pass', 'foto', 'nombre', 'fullName'];
        $hasPersonalData = false;
        foreach ($personalFields as $pf) {
            if (array_key_exists($pf, $incomingData)) {
                $hasPersonalData = true;
                break;
            }
        }
        if ($hasPersonalData && !isset($incomingData['colabs'])) {
            $senderWaId = $incomingData['senderWaId'] ?? $incomingData['waId'] ?? null;
            if ($senderWaId !== null) {
                $currentExp = $this->applyMergeForColab($currentExp, $incomingData, (string)$senderWaId);
            }
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

        $normalizedSender = $this->normalizeWaId($senderWaId);

        // Si viene un array 'colabs' en incoming, buscar al senderWaId allí
        if (isset($incomingData['colabs']) && is_array($incomingData['colabs'])) {
            foreach ($incomingData['colabs'] as $c) {
                if (isset($c['waId'])) {
                    $cWaId = (string)$c['waId'];
                    if ($cWaId === $senderWaId || $this->normalizeWaId($cWaId) === $normalizedSender) {
                        $colabPayload = $c;
                        break;
                    }
                }
            }
        }

        // Si no viene dentro de 'colabs', verificar si el incomingData mismo es el payload del perfil
        if ($colabPayload === null && isset($incomingData['waId'])) {
            $incomingWaId = (string)$incomingData['waId'];
            if ($incomingWaId === $senderWaId || $this->normalizeWaId($incomingWaId) === $normalizedSender) {
                $colabPayload = $incomingData;
            }
        }

        if ($colabPayload === null) {
            return $currentExp;
        }

        // Fusionar únicamente los campos permitidos del colaborador dentro de colabs
        $allowedColabFields = ['nombre', 'fullName', 'foto', 'pass', 'taId'];

        foreach ($currentExp['colabs'] as $idx => $existingColab) {
            $waId = isset($existingColab['waId']) ? (string)$existingColab['waId'] : '';
            if ($waId === $senderWaId || $this->normalizeWaId($waId) === $normalizedSender) {
                foreach ($allowedColabFields as $field) {
                    if (array_key_exists($field, $colabPayload)) {
                        if ($field === 'pass') {
                            $newPass = trim((string)($colabPayload['pass'] ?? ''));
                            if ($newPass !== '') {
                                $currentExp['colabs'][$idx]['pass'] = $newPass;
                            }
                            // Si viene vacío, null o ausente, NO se sobreescribe el pass existente
                        } else {
                            $currentExp['colabs'][$idx][$field] = $colabPayload[$field];
                        }
                    }
                }
                break;
            }
        }

        return $currentExp;
    }

    /**
     * Normaliza un waId mexicano para comparación robusta.
     * Replica la lógica de PhoneHelper.normalizeWaId() del cliente Dart.
     */
    private function normalizeWaId(string $raw): string
    {
        $clean = preg_replace('/\D/', '', $raw);
        $len = strlen($clean);

        // 13 dígitos con prefijo 521 (formato estándar México WhatsApp)
        if ($len === 13 && str_starts_with($clean, '521')) {
            return $clean;
        }
        // 10 dígitos nacionales → asumir México
        if ($len === 10) {
            return '521' . $clean;
        }
        // 12 dígitos formato E.164 México (52 + 10 dígitos)
        if ($len === 12 && str_starts_with($clean, '52')) {
            return '521' . substr($clean, 2);
        }
        // 11 dígitos con prefijo 1 (1 + 10 dígitos)
        if ($len === 11 && str_starts_with($clean, '1')) {
            return '521' . substr($clean, 1);
        }
        // Prefijo repetido 521521...
        if (str_starts_with($clean, '521521')) {
            return $this->normalizeWaId(substr($clean, 3));
        }

        return $clean;
    }

    /**
     * Actualiza administrativamente los datos comerciales de una empresa bajo lista blanca estricta.
     *
     * Campos permitidos: empresa, localidad, address, colonia, categoria, prestige.
     * Preserva íntegramente: slug, colabs, accountType, plan, nif, desde, asesor, links, logo.
     *
     * @param string $slug Slug de la empresa
     * @param array $data Datos comerciales validados
     * @return array Expediente actualizado
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function updateCommercialInfoByAdmin(string $slug, array $data): array
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
        $lockPath = Path::canonicalize($dirPath . '/' . $slug . '.lock');

        $lockFp = @fopen($lockPath, 'c+');
        if ($lockFp === false) {
            throw new \RuntimeException("No se pudo abrir el archivo de bloqueo: $lockPath");
        }

        if (!@flock($lockFp, LOCK_EX)) {
            @fclose($lockFp);
            throw new \RuntimeException("No se pudo obtener el bloqueo de archivo para: $slug.lock");
        }

        $tmpFile = null;

        try {
            if (!file_exists($filePath)) {
                throw new \RuntimeException("El expediente de la empresa '$slug' no existe.");
            }

            $rawContent = @file_get_contents($filePath);
            if ($rawContent === false || trim($rawContent) === '') {
                throw new \RuntimeException("El expediente de la empresa '$slug' está vacío o no se puede leer.");
            }

            $currentExp = json_decode($rawContent, true);
            if (!is_array($currentExp)) {
                throw new \RuntimeException("El contenido del expediente '$slug' contiene JSON inválido.");
            }

            // Lista blanca estricta de campos comerciales modificables por administración
            if (array_key_exists('empresa', $data)) {
                $empresa = trim((string)$data['empresa']);
                if ($empresa === '') {
                    throw new \InvalidArgumentException("El nombre de la empresa no puede estar vacío.");
                }
                $currentExp['empresa'] = $empresa;
            }

            if (array_key_exists('localidad', $data)) {
                $currentExp['localidad'] = trim((string)$data['localidad']);
            }

            if (array_key_exists('address', $data)) {
                $currentExp['address'] = trim((string)$data['address']);
            }

            if (array_key_exists('colonia', $data)) {
                $currentExp['colonia'] = trim((string)$data['colonia']);
            }

            if (array_key_exists('categoria', $data)) {
                $currentExp['categoria'] = trim((string)$data['categoria']);
            }

            if (array_key_exists('prestige', $data)) {
                $rawPrestige = $data['prestige'];
                if (is_numeric($rawPrestige)) {
                    $currentExp['prestige'] = (int)$rawPrestige;
                } else {
                    $currentExp['prestige'] = $rawPrestige;
                }
            }

            // Incrementar versión y actualizar timestamp de mutación
            $currentVersion = isset($currentExp['version']) && is_numeric($currentExp['version'])
                ? (int)$currentExp['version']
                : 0;
            $currentExp['version'] = $currentVersion + 1;
            $currentExp['updatedAt'] = (int) round(microtime(true) * 1000);

            // Serialización y reemplazo atómico seguro mediante archivo temporal
            $encoded = json_encode($currentExp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException("Error al serializar el expediente JSON para: $slug");
            }

            $tmpFile = tempnam($dirPath, 'exp_tmp_');
            if ($tmpFile === false) {
                throw new \RuntimeException("No se pudo crear archivo temporal para reemplazo atómico en: $dirPath");
            }

            $bytesWritten = @file_put_contents($tmpFile, $encoded);
            if ($bytesWritten === false || $bytesWritten !== strlen($encoded)) {
                throw new \RuntimeException("Escritura incompleta o fallida en archivo temporal: $tmpFile");
            }

            if (!@rename($tmpFile, $filePath)) {
                @unlink($filePath);
                if (!@rename($tmpFile, $filePath)) {
                    throw new \RuntimeException("Fallo al renombrar atómicamente el archivo temporal hacia $filePath");
                }
            }
            $tmpFile = null;

            return $currentExp;

        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            if (is_resource($lockFp)) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }
}

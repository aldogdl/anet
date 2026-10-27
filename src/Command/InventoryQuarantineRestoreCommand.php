<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Comando administrativo CLI para la inspección y recuperación segura e idempotente
 * de archivos en cuarentena tras caídas bruscas o interrupciones pre/post-commit.
 */
#[AsCommand(
    name: 'inventory:quarantine-restore',
    description: 'Inspecciona y restaura de forma segura e idempotente archivos en cuarentena tras interrupciones.'
)]
class InventoryQuarantineRestoreCommand extends Command
{
    private Connection $conn;
    private string $quarantineBaseDir;

    public function __construct(Connection $conn, string $invExp)
    {
        parent::__construct();
        $this->conn = $conn;
        $this->quarantineBaseDir = rtrim($invExp, '/\\') . '/quarantine';
    }

    protected function configure(): void
    {
        $this
            ->addOption('session', 's', InputOption::VALUE_OPTIONAL, 'Identificador de la sesión de cuarentena a restaurar.')
            ->addOption('slug', null, InputOption::VALUE_OPTIONAL, 'Slug de la empresa para buscar la sesión de cuarentena más reciente.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Previsualiza la restauración sin modificar archivos físicos.')
            ->addOption('force-orphan', null, InputOption::VALUE_NONE, 'Fuerza la restauración incluso si las publicaciones ya fueron borradas de MySQL.')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'Lista todas las sesiones de cuarentena registradas.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Recuperación Administrativa de Cuarentena de Inventario');

        if (!is_dir($this->quarantineBaseDir)) {
            $io->warning("El directorio de cuarentena no existe: {$this->quarantineBaseDir}");
            return Command::SUCCESS;
        }

        // Listar sesiones disponibles
        $sessions = [];
        $dirs = glob($this->quarantineBaseDir . '/*', GLOB_ONLYDIR);
        if ($dirs) {
            foreach ($dirs as $dir) {
                $manifestPath = $dir . '/manifest.json';
                if (file_exists($manifestPath)) {
                    $manifest = json_decode((string)file_get_contents($manifestPath), true) ?? [];
                    $sessions[basename($dir)] = [
                        'path' => $dir,
                        'slug' => $manifest['slug'] ?? 'desconocido',
                        'timestamp' => $manifest['timestamp'] ?? 'desconocido',
                        'status' => $manifest['status'] ?? 'quarantined',
                        'committed' => $manifest['databaseCommitted'] ?? false,
                        'filesCount' => $manifest['filesCount'] ?? count($manifest['files'] ?? []),
                    ];
                }
            }
        }

        if ($input->getOption('list')) {
            if (empty($sessions)) {
                $io->info('No hay sesiones de cuarentena registradas.');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($sessions as $token => $s) {
                $rows[] = [
                    $token,
                    $s['slug'],
                    $s['timestamp'],
                    $s['status'],
                    $s['committed'] ? 'SÍ' : 'NO',
                    $s['filesCount'],
                ];
            }
            $io->table(['Sesión', 'Slug', 'Fecha', 'Estado', 'BD Commit', 'Archivos'], $rows);
            return Command::SUCCESS;
        }

        // Determinar sesión objetivo
        $targetSession = $input->getOption('session');
        $slug = $input->getOption('slug');

        if (!$targetSession && $slug) {
            // Buscar la más reciente para este slug
            foreach (array_reverse($sessions) as $token => $s) {
                if ($s['slug'] === $slug) {
                    $targetSession = $token;
                    break;
                }
            }
        }

        if (!$targetSession) {
            $io->error('Debe especificar --session o --slug para ejecutar la restauración. Use --list para ver las disponibles.');
            return Command::FAILURE;
        }

        if (!isset($sessions[$targetSession])) {
            $io->error("No se encontró la sesión de cuarentena especificada: {$targetSession}");
            return Command::FAILURE;
        }

        $sessionData = $sessions[$targetSession];
        $sessionDir = $sessionData['path'];
        $manifestPath = $sessionDir . '/manifest.json';
        $manifest = json_decode((string)file_get_contents($manifestPath), true) ?? [];
        $companySlug = $manifest['slug'] ?? '';
        $files = $manifest['files'] ?? $manifest['quarantinedFiles'] ?? [];

        $io->section("Sesión seleccionada: {$targetSession} (Empresa: {$companySlug})");
        $io->text("Total de archivos registrados en manifiesto: " . count($files));

        // Validar estado en MySQL
        $dbCount = (int)$this->conn->fetchOne(
            "SELECT COUNT(*) FROM item_pub WHERE slug = ?",
            [$companySlug]
        );

        $isDryRun = (bool)$input->getOption('dry-run');
        $forceOrphan = (bool)$input->getOption('force-orphan');

        if ($dbCount > 0) {
            $io->success("ESTADO SEGURO DETECTADO (Pre-Commit): MySQL contiene {$dbCount} publicaciones para '{$companySlug}'. La transacción previa se abortó o no se completó. Es 100% seguro restituir los archivos físicos.");
        } else {
            $io->warning("ESTADO POST-COMMIT DETECTADO: MySQL no contiene publicaciones para '{$companySlug}'. La transacción ya eliminó los registros en base de datos. Restaurar las fotos creará archivos huérfanos.");
            if (!$forceOrphan) {
                $io->error("Restauración detenida para evitar archivos huérfanos. Si planea reimportar el inventario, use la opción --force-orphan.");
                return Command::FAILURE;
            }
            $io->caution("Continuando restauración con --force-orphan activa.");
        }

        if ($isDryRun) {
            $io->note('[MODO DRY-RUN] No se realizarán movimientos físicos de archivos.');
        }

        $restoredCount = 0;
        $alreadyExistedCount = 0;
        $missingInQuarantineCount = 0;

        foreach ($files as $fileEntry) {
            $origPath = $fileEntry['original'] ?? null;
            $quarantinePath = $fileEntry['quarantine'] ?? null;

            if (!$origPath || !$quarantinePath) {
                continue;
            }

            if (!file_exists($quarantinePath)) {
                // Verificar si ya está en la ruta original (idempotencia)
                if (file_exists($origPath)) {
                    $alreadyExistedCount++;
                    continue;
                }
                $missingInQuarantineCount++;
                continue;
            }

            // Comprobar idempotencia: si ya existe en destino
            if (file_exists($origPath)) {
                if (filesize($origPath) === filesize($quarantinePath)) {
                    $alreadyExistedCount++;
                    if (!$isDryRun) {
                        @unlink($quarantinePath);
                    }
                    continue;
                }
            }

            if (!$isDryRun) {
                $targetDir = dirname($origPath);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                if (@rename($quarantinePath, $origPath)) {
                    $restoredCount++;
                }
            } else {
                $restoredCount++;
            }
        }

        $io->table(
            ['Métrica de Recuperación', 'Cantidad'],
            [
                ['Archivos restituidos exitosamente', $restoredCount],
                ['Archivos ya existentes en destino (idempotentes)', $alreadyExistedCount],
                ['Archivos no encontrados en cuarentena', $missingInQuarantineCount],
            ]
        );

        if (!$isDryRun && $missingInQuarantineCount === 0) {
            $manifest['status'] = 'restored';
            $manifest['restoredAt'] = date('c');
            @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_SLASHES));
            $io->success("Restauración finalizada exitosamente para la sesión {$targetSession}.");
        }

        return Command::SUCCESS;
    }
}

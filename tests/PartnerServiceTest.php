<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Service/Any/PartnerService.php';

use App\Service\Any\PartnerService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

$tempDir = sys_get_temp_dir() . '/partner_test_' . uniqid();
mkdir($tempDir);
$validJsonPath = $tempDir . '/partners.json';
$corruptJsonPath = $tempDir . '/corrupt.json';
$missingJsonPath = $tempDir . '/non_existing.json';

file_put_contents($validJsonPath, json_encode([
    'partners' => [
        'socio1',
        'Socio-Especial',
        '  slug_con_espacios  '
    ]
]));

file_put_contents($corruptJsonPath, '{ invalid json');

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName, &$passed, &$failed): void {
    if ($condition) {
        echo "[PASS] $testName\n";
        $passed++;
    } else {
        echo "[FAIL] $testName\n";
        $failed++;
    }
}

$params = new ParameterBag(['dtaCtc' => $tempDir]);
$service = new PartnerService($params, $validJsonPath);
$defaultPathService = new PartnerService($params); // Prueba resolución automática mediante dtaCtc/partners.json

// 1. Slug presente
assertTest($service->isPartner('socio1') === true, '1. slug presente retorna true', $passed, $failed);
assertTest($defaultPathService->isPartner('socio1') === true, '1a. slug presente en default path (dtaCtc/partners.json) retorna true', $passed, $failed);
assertTest($service->isPartner('SOCIO1') === true, '1b. slug mayúsculas retorna true', $passed, $failed);
assertTest($service->isPartner(' socio1 ') === true, '1c. slug con espacios retorna true', $passed, $failed);
assertTest($service->isPartner('socio-especial') === true, '1d. slug socio-especial normalizado retorna true', $passed, $failed);
assertTest($service->isPartner('slug_con_espacios') === true, '1e. partner guardado con espacios en JSON normalizado retorna true', $passed, $failed);

// 2. Slug ausente
assertTest($service->isPartner('no_socio') === false, '2. slug ausente retorna false', $passed, $failed);
assertTest($service->isPartner('') === false, '2b. slug vacío retorna false', $passed, $failed);
assertTest($service->isPartner(null) === false, '2c. slug null retorna false', $passed, $failed);

// 3. Archivo ausente o corrupto
$corruptService = new PartnerService($params, $corruptJsonPath);
assertTest($corruptService->isPartner('socio1') === false, '3a. archivo corrupto retorna false seguro', $passed, $failed);

$missingService = new PartnerService($params, $missingJsonPath);
assertTest($missingService->isPartner('socio1') === false, '3b. archivo ausente retorna false seguro', $passed, $failed);

// Limpieza
@unlink($validJsonPath);
@unlink($corruptJsonPath);
@rmdir($tempDir);

echo "\nResultados AutoParnet Tests: $passed pasados, $failed fallidos.\n";
if ($failed > 0) {
    exit(1);
}

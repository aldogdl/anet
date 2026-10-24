<?php

namespace App\Service;

/**
 * Helper centralizado para aplicar el "Super Blindaje de stt = 3"
 * y reglas canónicas de actualización en importaciones masivas o automáticas
 * (InventoryImportService y InventoryTemporalImportService).
 *
 * REGLAS FUNDAMENTALES:
 * 1. Si un ítem en SR tiene stt == 3 (auditado y refinado por operario humano con ficha técnica verificada):
 *    - NINGUNA importación automática puede degradar ni cambiar su estado (se conserva stt = 3).
 *    - NINGUNA importación automática puede sobrescribir su ficha técnica (pieza, marcas, modelos, años, lado, posición, detalles, variantes).
 *    - NINGUNA importación automática puede sobrescribir ni vaciar sus fotografías existentes (thumb, img_big, extras.pictures, extras.pathImg).
 *    - NINGUNA importación automática puede alterar sus identificadores canónicos (id, iku, id_src, slug).
 *    - ÚNICAMENTE se permite actualizar variables comerciales y de inventario: price, costo, is_active (respetando inactividad 501+), updated_at y from_dev.
 *    - EXCEPCIÓN DE BAJA COMERCIAL: Si la fuente externa marca explícitamente la publicación como inactiva/pausada (stt >= 501 o is_active false),
 *      se permite marcar stt = 501 e is_active = false, sin tocar su ficha técnica ni fotos.
 * 2. Si un ítem NO tiene stt == 3:
 *    - Ninguna importación automática tiene permiso para promoverlo automáticamente a stt = 3 (la promoción a 3 es privilegio exclusivo de FormInv).
 *    - Si ambos registros (existente e incoming) están inactivos (stt >= 501), se ignora la actualización.
 */
class InventoryShieldHelper
{
    /**
     * Campos de Ficha Técnica protegidos estrictamente cuando stt == 3.
     */
    private const FICHA_TECNICA_FIELDS = [
        'pieza',
        'mrk_id',
        'mdl_id',
        'anio_inicio',
        'anio_fin',
        'lado',
        'poss',
        'detalles',
        'variantes',
    ];

    /**
     * Aplica el Super Blindaje de stt = 3 y devuelve el array de datos seguro para ejecutar el UPDATE en MySQL.
     *
     * @param array $existing Registro actual en la tabla item_pub (con id, stt, ficha técnica, fotos, etc.)
     * @param array $incoming Datos que la importación pretende actualizar
     * @return array Datos filtrados y blindados listos para UPDATE en item_pub
     */
    public static function applyShield(array $existing, array $incoming): array
    {
        $existingStt = (int) ($existing['stt'] ?? 0);
        $incomingStt = (int) ($incoming['stt'] ?? 0);

        // Clón de datos entrantes como base
        $safeData = $incoming;

        // 1. Identificadores canónicos: siempre inmutables en UPDATE
        $safeData['id_src'] = $existing['id_src'] ?? $incoming['id_src'];
        $safeData['slug'] = $existing['slug'] ?? $incoming['slug'];
        if (!empty($existing['iku'])) {
            $safeData['iku'] = $existing['iku'];
        }

        // 2. CASO SUPER BLINDAJE: El ítem en SR ya fue auditado/refinado por humano (stt == 3)
        if ($existingStt === 3) {

            // A. Evaluación de Estado:
            // Si la fuente externa lo pausa o elimina (incoming >= 501 o isActive false explícito con stt >= 500)
            if ($incomingStt >= 501 || (isset($incoming['is_active']) && !$incoming['is_active'] && $incomingStt >= 500)) {
                $safeData['stt'] = 501;
                $safeData['is_active'] = 0;
            } else {
                // Preservar obligatoriamente stt = 3 frente a valores crudos (0, 1, 300, 302, etc.)
                $safeData['stt'] = 3;
                if (isset($incoming['is_active'])) {
                    $safeData['is_active'] = $incoming['is_active'] ? 1 : 0;
                } else {
                    $safeData['is_active'] = $existing['is_active'] ? 1 : 0;
                }
            }

            // B. Congelar Ficha Técnica verificada:
            foreach (self::FICHA_TECNICA_FIELDS as $field) {
                if (array_key_exists($field, $existing)) {
                    $safeData[$field] = $existing[$field];
                }
            }

            // C. Proteger Fotografías y Miniaturas:
            // Si el registro existente tiene fotos, NUNCA reemplazarlas por vacíos o enlaces de inferior calidad
            $existingThumb = trim((string) ($existing['thumb'] ?? ''));
            if ($existingThumb !== '') {
                $safeData['thumb'] = $existingThumb;
            }

            $existingImgBig = trim((string) ($existing['img_big'] ?? ''));
            if ($existingImgBig !== '') {
                $safeData['img_big'] = $existingImgBig;
            }

            // D. Fusión de Extras con preservación de imágenes de SR:
            $existingExtras = self::decodeExtras($existing['extras'] ?? null);
            $incomingExtras = self::decodeExtras($incoming['extras'] ?? null);

            // Preservar pictures y pathImg existentes
            if (!empty($existingExtras['pictures'])) {
                $incomingExtras['pictures'] = $existingExtras['pictures'];
            }
            if (!empty($existingExtras['pathImg'])) {
                $incomingExtras['pathImg'] = $existingExtras['pathImg'];
            }
            if (isset($existingExtras['idSr']) && (int) $existingExtras['idSr'] > 0) {
                $incomingExtras['idSr'] = (int) $existingExtras['idSr'];
            } elseif (isset($existing['id']) && (int) $existing['id'] > 0) {
                $incomingExtras['idSr'] = (int) $existing['id'];
            }

            // Preservar CBI existente si el incoming no trae uno válido
            if (!empty($existingExtras['cbi']) && empty($incomingExtras['cbi'])) {
                $incomingExtras['cbi'] = $existingExtras['cbi'];
            }

            $safeData['extras'] = json_encode($incomingExtras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // E. Variables comerciales actualizables:
            // price, costo, link (siempre que conserve ft-idSr), updated_at
            if (isset($incoming['price']) && (float) $incoming['price'] > 0) {
                $safeData['price'] = (float) $incoming['price'];
            } else {
                $safeData['price'] = (float) ($existing['price'] ?? 0.0);
            }

            if (isset($incoming['costo'])) {
                $safeData['costo'] = (float) $incoming['costo'];
            }

            return $safeData;
        }

        // 3. CASO ÍTEM NO AUDITADO (existingStt != 3):
        // Si el incoming viene con stt == 3 por error o manipulación de archivo CSV, NO promover automáticamente.
        // Solo FormInv puede asignar stt = 3. En importación automática se normaliza a 302 si venía activo.
        if ($incomingStt === 3) {
            $safeData['stt'] = ($existingStt > 0 && $existingStt < 501) ? $existingStt : 302;
        }

        // Si el estado final es >= 501, forzar is_active = 0
        if ((int) ($safeData['stt'] ?? 0) >= 501) {
            $safeData['is_active'] = 0;
        } elseif (isset($safeData['is_active'])) {
            $safeData['is_active'] = $safeData['is_active'] ? 1 : 0;
        }

        // Preservar ID de SR en extras si ya existía
        $existingExtras = self::decodeExtras($existing['extras'] ?? null);
        $incomingExtras = self::decodeExtras($incoming['extras'] ?? null);
        if (isset($existingExtras['idSr']) && (int) $existingExtras['idSr'] > 0) {
            $incomingExtras['idSr'] = (int) $existingExtras['idSr'];
        } elseif (isset($existing['id']) && (int) $existing['id'] > 0) {
            $incomingExtras['idSr'] = (int) $existing['id'];
        }
        $safeData['extras'] = json_encode($incomingExtras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $safeData;
    }

    /**
     * Decodifica de forma tolerante el campo extras (array, string JSON o null).
     */
    public static function decodeExtras(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}

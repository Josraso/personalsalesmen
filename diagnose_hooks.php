<?php
/**
 * Diagnóstico DEFINITIVO del problema de hooks
 */

require_once '../../config/config.inc.php';
require_once '../../init.php';

echo "<h1>Diagnóstico DEFINITIVO - ¿Por qué no se ejecutan los hooks?</h1>";

// 1. Verificar ID del módulo
$moduleIdFromDb = (int)Db::getInstance()->getValue(
    "SELECT id_module FROM " . _DB_PREFIX_ . "module WHERE name = 'personalsalesmen'"
);

$module = Module::getInstanceByName('personalsalesmen');
$moduleIdFromInstance = $module ? (int)$module->id : 0;

echo "<h2>1. ID del Módulo</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Fuente</th><th>ID</th><th>Estado</th></tr>";
echo "<tr><td>Base de datos (ps_module)</td><td><strong>{$moduleIdFromDb}</strong></td><td>" . ($moduleIdFromDb > 0 ? '✓' : '✗') . "</td></tr>";
echo "<tr><td>Instancia del módulo (\$module->id)</td><td><strong>{$moduleIdFromInstance}</strong></td><td>" . ($moduleIdFromInstance > 0 ? '✓' : '✗') . "</td></tr>";

if ($moduleIdFromDb !== $moduleIdFromInstance) {
    echo "<tr><td colspan='3' style='background:red;color:white'><strong>❌ PROBLEMA: IDs NO COINCIDEN</strong></td></tr>";
} else {
    echo "<tr><td colspan='3' style='background:green;color:white'><strong>✓ IDs COINCIDEN</strong></td></tr>";
}
echo "</table>";

// 2. Verificar estado del módulo
echo "<h2>2. Estado del Módulo</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Campo</th><th>Valor</th></tr>";

$moduleData = Db::getInstance()->getRow(
    "SELECT * FROM " . _DB_PREFIX_ . "module WHERE name = 'personalsalesmen'"
);

if ($moduleData) {
    foreach ($moduleData as $key => $value) {
        $highlight = '';
        if ($key === 'active' && $value == 0) {
            $highlight = 'background:red;color:white';
        }
        echo "<tr style='{$highlight}'><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
    }
}
echo "</table>";

// 3. Verificar hooks registrados CON id_module
echo "<h2>3. Hooks Registrados en Base de Datos</h2>";

$hooks = Db::getInstance()->executeS("
    SELECT hm.id_hook, h.name, hm.position, hm.id_module
    FROM " . _DB_PREFIX_ . "hook_module hm
    JOIN " . _DB_PREFIX_ . "hook h ON hm.id_hook = h.id_hook
    WHERE hm.id_module = {$moduleIdFromDb}
    ORDER BY h.name
");

echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>Hook Name</th><th>Hook ID</th><th>Position</th><th>Module ID</th><th>Estado</th></tr>";

$targetHooks = [
    'actionCustomerGridQueryBuilderModifier',
    'actionOrderGridQueryBuilderModifier',
    'actionAddressGridQueryBuilderModifier'
];

foreach ($hooks as $hook) {
    $isTarget = in_array($hook['name'], $targetHooks);
    $bg = $isTarget ? 'background:yellow' : '';

    $status = '✓ Registrado';
    if ($hook['id_module'] != $moduleIdFromInstance) {
        $status = '❌ ID módulo incorrecto';
        $bg = 'background:red;color:white';
    } elseif ($hook['position'] == 0) {
        $status = '⚠️ Sin posición';
        $bg = 'background:orange';
    }

    echo "<tr style='{$bg}'>";
    echo "<td><strong>{$hook['name']}</strong></td>";
    echo "<td>{$hook['id_hook']}</td>";
    echo "<td>{$hook['position']}</td>";
    echo "<td>{$hook['id_module']}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Verificar si hooks están en caché de PrestaShop
echo "<h2>4. Verificar Caché de Hooks</h2>";

$hookCache = Hook::getHookModuleExecList();
echo "<p>Total hooks en caché: " . count($hookCache) . "</p>";

$ourHooksInCache = [];
foreach ($targetHooks as $hookName) {
    if (isset($hookCache[$hookName])) {
        $ourHooksInCache[$hookName] = $hookCache[$hookName];
    }
}

if (empty($ourHooksInCache)) {
    echo "<p style='background:red;color:white;padding:10px'><strong>❌ PROBLEMA ENCONTRADO: Nuestros hooks NO están en la caché de PrestaShop</strong></p>";
    echo "<p>Esto significa que aunque están en BD, PrestaShop no los reconoce.</p>";
} else {
    echo "<p style='background:green;color:white;padding:10px'><strong>✓ Hooks encontrados en caché</strong></p>";
    echo "<pre>" . print_r($ourHooksInCache, true) . "</pre>";
}

// 5. Verificar si hay overrides bloqueando
echo "<h2>5. Verificar Overrides</h2>";

$overrideFile = _PS_OVERRIDE_DIR_ . 'classes/Hook.php';
if (file_exists($overrideFile)) {
    echo "<p style='background:orange;padding:10px'>⚠️ Existe override de Hook.php</p>";
    echo "<p>Archivo: <code>{$overrideFile}</code></p>";
} else {
    echo "<p style='background:green;color:white;padding:10px'>✓ No hay override de Hook.php</p>";
}

// 6. DIAGNÓSTICO FINAL
echo "<hr>";
echo "<h2>🔍 DIAGNÓSTICO FINAL</h2>";

if ($moduleIdFromDb !== $moduleIdFromInstance) {
    echo "<p style='background:red;color:white;padding:15px;font-size:18px'>";
    echo "❌ <strong>PROBLEMA:</strong> El ID del módulo en BD ({$moduleIdFromDb}) no coincide con el ID de la instancia ({$moduleIdFromInstance}).<br>";
    echo "Esto significa que hay registros corruptos en la base de datos.";
    echo "</p>";
} elseif ($moduleData['active'] == 0) {
    echo "<p style='background:red;color:white;padding:15px;font-size:18px'>";
    echo "❌ <strong>PROBLEMA:</strong> El módulo NO está activo en la base de datos (active = 0).";
    echo "</p>";
} elseif (empty($ourHooksInCache)) {
    echo "<p style='background:red;color:white;padding:15px;font-size:18px'>";
    echo "❌ <strong>PROBLEMA:</strong> Los hooks están registrados en BD pero NO están en la caché de PrestaShop.<br>";
    echo "PrestaShop NO ejecuta hooks que no estén en su caché interna.<br><br>";
    echo "<strong>SOLUCIÓN:</strong> Necesitas regenerar la caché de hooks de PrestaShop.";
    echo "</p>";
} else {
    echo "<p style='background:green;color:white;padding:15px;font-size:18px'>";
    echo "✓ Todo parece correcto. Si los hooks aún no se ejecutan, puede ser un problema de contexto o permisos.";
    echo "</p>";
}

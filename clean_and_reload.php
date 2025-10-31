<?php
/**
 * Script para limpiar TODA la caché y forzar recarga del módulo
 */

require_once '../../config/config.inc.php';
require_once '../../init.php';

echo "<h1>Limpieza Completa de Caché y Recarga del Módulo</h1>";

echo "<h2>PASO 1: Limpiar caché de PrestaShop</h2>";

// Limpiar var/cache
Tools::clearCache();
echo "<p style='color:green'>✓ var/cache limpiada</p>";

// Limpiar class_index.php
$classIndexFile = _PS_CACHE_DIR_ . 'class_index.php';
if (file_exists($classIndexFile)) {
    unlink($classIndexFile);
    echo "<p style='color:green'>✓ class_index.php eliminado</p>";
}

// Limpiar caché de módulos
$moduleCache = _PS_MODULE_DIR_ . 'personalsalesmen/var/cache';
if (is_dir($moduleCache)) {
    $files = glob($moduleCache . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "<p style='color:green'>✓ Caché del módulo limpiada</p>";
}

// Limpiar caché de Doctrine
$doctrineCache = _PS_ROOT_DIR_ . '/var/cache/prod/doctrine';
if (is_dir($doctrineCache)) {
    $files = glob($doctrineCache . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "<p style='color:green'>✓ Caché de Doctrine limpiada</p>";
}

echo "<h2>PASO 2: Forzar upgrade del módulo</h2>";

$module = Module::getInstanceByName('personalsalesmen');
if (!$module) {
    die("<p style='color:red'>ERROR: No se pudo cargar el módulo</p>");
}

// Actualizar versión en base de datos
Db::getInstance()->execute("
    UPDATE " . _DB_PREFIX_ . "module
    SET version = '5.0.1'
    WHERE name = 'personalsalesmen'
");

echo "<p style='color:green'>✓ Versión actualizada en base de datos a 5.0.1</p>";

// Reiniciar módulo (desactivar y activar)
if (Module::isEnabled('personalsalesmen')) {
    $module->disable();
    echo "<p style='color:blue'>Módulo desactivado</p>";

    $module->enable();
    echo "<p style='color:blue'>Módulo activado</p>";
}

echo "<h2>PASO 3: Verificar logs</h2>";

$logFiles = [
    'module_load.log' => 'Log de carga del módulo',
    'hook_test.log' => 'Log de ejecución de hooks'
];

foreach ($logFiles as $file => $description) {
    $filePath = _PS_MODULE_DIR_ . 'personalsalesmen/' . $file;

    echo "<h3>{$description}</h3>";

    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $lines = explode("\n", trim($content));
        $lastLines = array_slice($lines, -10); // Últimas 10 líneas

        echo "<pre style='background:#f0f0f0;padding:10px;border:1px solid #ccc'>";
        echo htmlspecialchars(implode("\n", $lastLines));
        echo "</pre>";

        echo "<p>Archivo: <code>{$filePath}</code></p>";
        echo "<p>Tamaño: " . filesize($filePath) . " bytes | Líneas: " . count($lines) . "</p>";
    } else {
        echo "<p style='color:red'>❌ Archivo NO existe: {$filePath}</p>";
    }
}

echo "<h2>PASO 4: Verificar estado actual</h2>";

echo "<p><strong>Módulo activo:</strong> " . (Module::isEnabled('personalsalesmen') ? '✓ SÍ' : '✗ NO') . "</p>";
echo "<p><strong>Versión del módulo:</strong> " . $module->version . "</p>";

$hookCount = Db::getInstance()->getValue("
    SELECT COUNT(*)
    FROM " . _DB_PREFIX_ . "hook_module hm
    JOIN " . _DB_PREFIX_ . "module m ON hm.id_module = m.id_module
    WHERE m.name = 'personalsalesmen'
");

echo "<p><strong>Hooks registrados:</strong> {$hookCount}</p>";

echo "<hr>";
echo "<h2>INSTRUCCIONES:</h2>";
echo "<ol>";
echo "<li>La caché ha sido limpiada completamente</li>";
echo "<li>El módulo ha sido reiniciado (desactivado y activado)</li>";
echo "<li>Ve al backoffice y entra a <strong>Clientes</strong> con un empleado con restricciones</li>";
echo "<li>Vuelve a esta página para ver si aparecen logs nuevos</li>";
echo "</ol>";

echo "<p><a href='clean_and_reload.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none;font-weight:bold'>🔄 REFRESCAR ESTA PÁGINA</a></p>";

<?php
/**
 * Script para limpiar y reparar hooks en la base de datos
 * Ejecutar desde: http://tudominio.com/modules/personalsalesmen/fix_hooks_db.php
 */

require_once '../../config/config.inc.php';
require_once '../../init.php';

echo "<h1>Personal Salesmen - Diagnóstico y Reparación de Hooks</h1>";

// Obtener ID del módulo
$moduleId = (int)Db::getInstance()->getValue(
    "SELECT id_module FROM " . _DB_PREFIX_ . "module WHERE name = 'personalsalesmen'"
);

if (!$moduleId) {
    die("<p style='color:red'>ERROR: Módulo 'personalsalesmen' no encontrado en base de datos.</p>");
}

echo "<h2>1. Estado Actual de Hooks</h2>";
echo "<p>Module ID: <strong>{$moduleId}</strong></p>";

$hooks = [
    'actionAdminControllerSetMedia',
    'actionCustomerGridQueryBuilderModifier',
    'actionOrderGridQueryBuilderModifier',
    'actionAddressGridQueryBuilderModifier',
    'actionValidateOrder',
];

echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>Hook Name</th><th>Hook ID</th><th>Registrado</th><th>Posición</th><th>Estado</th></tr>";

foreach ($hooks as $hookName) {
    $hookId = Hook::getIdByName($hookName);

    if (!$hookId) {
        echo "<tr><td>{$hookName}</td><td colspan='4' style='color:red'>❌ Hook no existe en sistema</td></tr>";
        continue;
    }

    $sql = "SELECT position FROM " . _DB_PREFIX_ . "hook_module
            WHERE id_module = {$moduleId} AND id_hook = {$hookId}";
    $position = Db::getInstance()->getValue($sql);

    if ($position === false) {
        echo "<tr><td>{$hookName}</td><td>{$hookId}</td><td style='color:red'>NO</td><td>-</td><td style='color:red'>❌ NO REGISTRADO</td></tr>";
    } elseif ($position == 0 || $position === null) {
        echo "<tr><td>{$hookName}</td><td>{$hookId}</td><td style='color:orange'>SÍ</td><td style='color:red'>{$position}</td><td style='color:orange'>⚠️ CORRUPTO (sin posición)</td></tr>";
    } else {
        echo "<tr><td>{$hookName}</td><td>{$hookId}</td><td style='color:green'>SÍ</td><td style='color:green'>{$position}</td><td style='color:green'>✓ OK</td></tr>";
    }
}
echo "</table>";

echo "<h2>2. Acción de Reparación</h2>";

if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
    echo "<h3>🔧 EJECUTANDO REPARACIÓN...</h3>";

    // PASO 1: Eliminar todos los registros del módulo
    echo "<p>PASO 1: Eliminando registros existentes...</p>";
    $deleted = Db::getInstance()->delete('hook_module', 'id_module = ' . $moduleId);
    echo "<p style='color:blue'>Eliminados: {$deleted} registros</p>";

    // PASO 2: Re-registrar hooks
    echo "<p>PASO 2: Re-registrando hooks...</p>";
    $module = Module::getInstanceByName('personalsalesmen');

    if (!$module) {
        die("<p style='color:red'>ERROR: No se pudo cargar instancia del módulo</p>");
    }

    $success = 0;
    $failed = 0;

    foreach ($hooks as $hookName) {
        if ($module->registerHook($hookName)) {
            echo "<p style='color:green'>✓ {$hookName} registrado correctamente</p>";
            $success++;
        } else {
            echo "<p style='color:red'>✗ {$hookName} FALLÓ</p>";
            $failed++;
        }
    }

    // PASO 3: Limpiar caché
    echo "<p>PASO 3: Limpiando caché...</p>";
    Tools::clearCache();
    echo "<p style='color:green'>✓ Caché limpiada</p>";

    echo "<h3>RESULTADO:</h3>";
    echo "<p style='color:green; font-size:18px'>✓ Hooks registrados correctamente: {$success}</p>";
    if ($failed > 0) {
        echo "<p style='color:red; font-size:18px'>✗ Hooks con error: {$failed}</p>";
    }

    echo "<p><a href='fix_hooks_db.php'>Ver estado actualizado</a></p>";

} else {
    echo "<p><strong>¿Quieres reparar los hooks ahora?</strong></p>";
    echo "<p><a href='fix_hooks_db.php?fix=yes' style='background:red;color:white;padding:10px 20px;text-decoration:none;font-weight:bold'>🔧 REPARAR HOOKS AHORA</a></p>";
    echo "<p style='color:red'><strong>ADVERTENCIA:</strong> Esto eliminará todos los registros de hooks del módulo y los re-registrará.</p>";
}

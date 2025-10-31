<?php
/**
 * REGENERAR CACHÉ DE HOOKS - SOLUCIÓN DEFINITIVA
 */

require_once '../../config/config.inc.php';
require_once '../../init.php';

echo "<h1>Regenerar Caché de Hooks de PrestaShop</h1>";

echo "<h2>Estado ANTES de regenerar</h2>";

// Verificar estado actual
$hookCache = Hook::getHookModuleExecList();
$targetHooks = [
    'actionCustomerGridQueryBuilderModifier',
    'actionOrderGridQueryBuilderModifier',
    'actionAddressGridQueryBuilderModifier'
];

$found = 0;
foreach ($targetHooks as $hookName) {
    if (isset($hookCache[$hookName])) {
        $found++;
        echo "<p style='color:green'>✓ {$hookName} en caché</p>";
    } else {
        echo "<p style='color:red'>✗ {$hookName} NO en caché</p>";
    }
}

echo "<p><strong>Hooks en caché:</strong> {$found} de " . count($targetHooks) . "</p>";

echo "<hr>";
echo "<h2>REGENERANDO CACHÉ...</h2>";

// PASO 1: Borrar todos los archivos de caché
echo "<p>PASO 1: Borrando archivos de caché...</p>";

$cacheDirs = [
    _PS_ROOT_DIR_ . '/var/cache/prod',
    _PS_ROOT_DIR_ . '/var/cache/dev',
    _PS_CACHE_DIR_,
];

$deletedFiles = 0;
foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $deletedFiles++;
            }
        }
    }
}
echo "<p style='color:blue'>Archivos borrados: {$deletedFiles}</p>";

// PASO 2: Borrar class_index.php
$classIndex = _PS_CACHE_DIR_ . 'class_index.php';
if (file_exists($classIndex)) {
    unlink($classIndex);
    echo "<p style='color:blue'>✓ class_index.php eliminado</p>";
}

// PASO 3: Regenerar hooks usando el método nativo de PrestaShop
echo "<p>PASO 2: Regenerando hooks...</p>";

try {
    // Método 1: Hook::cacheHooks() - regenera toda la caché de hooks
    if (method_exists('Hook', 'cacheHooks')) {
        Hook::cacheHooks();
        echo "<p style='color:green'>✓ Hook::cacheHooks() ejecutado</p>";
    }

    // Método 2: Forzar recarga limpiando singleton
    if (method_exists('Hook', 'resetStaticCache')) {
        Hook::resetStaticCache();
        echo "<p style='color:green'>✓ Hook::resetStaticCache() ejecutado</p>";
    }

    // Método 3: Tools::clearCache - limpia toda la caché
    Tools::clearCache();
    echo "<p style='color:green'>✓ Tools::clearCache() ejecutado</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// PASO 4: Forzar recarga del módulo
echo "<p>PASO 3: Recargando módulo...</p>";

$module = Module::getInstanceByName('personalsalesmen');
if ($module) {
    // Forzar que PrestaShop vuelva a leer los hooks del módulo
    $moduleId = (int)$module->id;

    // Re-registrar hooks (esto los añade a la caché)
    $hooks = [
        'actionAdminControllerSetMedia',
        'actionCustomerGridQueryBuilderModifier',
        'actionOrderGridQueryBuilderModifier',
        'actionAddressGridQueryBuilderModifier',
        'actionValidateOrder',
    ];

    foreach ($hooks as $hookName) {
        // Desregistrar y registrar de nuevo para forzar entrada en caché
        $module->unregisterHook($hookName);
        $result = $module->registerHook($hookName);

        if ($result) {
            echo "<p style='color:green'>✓ {$hookName} re-registrado</p>";
        } else {
            echo "<p style='color:orange'>⚠ {$hookName} ya estaba registrado</p>";
        }
    }
}

// PASO 5: Limpiar caché de nuevo después de registrar
Tools::clearCache();
echo "<p style='color:green'>✓ Caché final limpiada</p>";

echo "<hr>";
echo "<h2>Estado DESPUÉS de regenerar</h2>";

// Forzar recarga de la caché
Hook::resetStaticCache();
$hookCacheAfter = Hook::getHookModuleExecList();

$foundAfter = 0;
foreach ($targetHooks as $hookName) {
    if (isset($hookCacheAfter[$hookName])) {
        // Verificar si nuestro módulo está en la lista
        $hasOurModule = false;
        foreach ($hookCacheAfter[$hookName] as $moduleData) {
            if (isset($moduleData['id_module']) && $moduleData['id_module'] == $module->id) {
                $hasOurModule = true;
                break;
            }
        }

        if ($hasOurModule) {
            $foundAfter++;
            echo "<p style='color:green;font-weight:bold'>✓✓✓ {$hookName} en caché CON nuestro módulo</p>";
        } else {
            echo "<p style='color:orange'>⚠ {$hookName} en caché pero SIN nuestro módulo</p>";
        }
    } else {
        echo "<p style='color:red'>✗ {$hookName} NO en caché</p>";
    }
}

echo "<hr>";
echo "<h2>🎯 RESULTADO FINAL</h2>";

if ($foundAfter === count($targetHooks)) {
    echo "<p style='background:green;color:white;padding:20px;font-size:20px;text-align:center'>";
    echo "✓✓✓ ¡ÉXITO! Los {$foundAfter} hooks están ahora en la caché de PrestaShop<br>";
    echo "Los filtros deberían funcionar correctamente ahora.";
    echo "</p>";

    echo "<h3>Siguiente paso:</h3>";
    echo "<ol>";
    echo "<li>Ve al backoffice de PrestaShop</li>";
    echo "<li>Entra con un empleado que tenga restricciones</li>";
    echo "<li>Ve a <strong>Clientes</strong> o <strong>Pedidos</strong></li>";
    echo "<li>Deberías ver SOLO los clientes/pedidos asignados</li>";
    echo "</ol>";

} else {
    echo "<p style='background:red;color:white;padding:20px;font-size:20px;text-align:center'>";
    echo "❌ Aún faltan {" . (count($targetHooks) - $foundAfter) . "} hooks por cargar en caché";
    echo "</p>";

    echo "<h3>Prueba esto:</h3>";
    echo "<ol>";
    echo "<li>Ve a backoffice > Módulos > Module Manager</li>";
    echo "<li>Busca 'Personal Salesmen'</li>";
    echo "<li>Haz clic en 'Desinstalar'</li>";
    echo "<li>Haz clic en 'Instalar'</li>";
    echo "<li>Vuelve a este script para verificar</li>";
    echo "</ol>";
}

echo "<p style='margin-top:30px'><a href='diagnose_hooks.php' style='background:blue;color:white;padding:10px 20px;text-decoration:none'>🔍 Ver Diagnóstico Completo</a></p>";

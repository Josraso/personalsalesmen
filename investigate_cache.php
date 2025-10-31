<?php
/**
 * Investigar POR QUÉ los hooks no entran en caché
 */

require_once '../../config/config.inc.php';
require_once '../../init.php';

echo "<h1>Investigación: ¿Por qué los hooks NO entran en caché?</h1>";

$moduleId = 207; // ID del módulo

echo "<h2>1. SQL que usa PrestaShop para cargar hooks</h2>";

// Este es el SQL que PrestaShop usa internamente en Hook::getHookModuleExecList()
$sql = "
SELECT
    h.id_hook,
    h.name as h_name,
    hm.position,
    hm.id_module,
    m.name as m_name,
    m.active
FROM `" . _DB_PREFIX_ . "hook_module` hm
STRAIGHT_JOIN `" . _DB_PREFIX_ . "hook` h ON (h.id_hook = hm.id_hook)
STRAIGHT_JOIN `" . _DB_PREFIX_ . "module` m ON (m.id_module = hm.id_module)
WHERE m.active = 1
AND h.name IN (
    'actionCustomerGridQueryBuilderModifier',
    'actionOrderGridQueryBuilderModifier',
    'actionAddressGridQueryBuilderModifier'
)
ORDER BY hm.position
";

echo "<p>SQL Query:</p>";
echo "<pre style='background:#f0f0f0;padding:10px;border:1px solid #ccc'>" . htmlspecialchars($sql) . "</pre>";

$result = Db::getInstance()->executeS($sql);

echo "<h3>Resultado del SQL:</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>Hook ID</th><th>Hook Name</th><th>Position</th><th>Module ID</th><th>Module Name</th><th>Active</th></tr>";

if ($result) {
    foreach ($result as $row) {
        $highlight = ($row['id_module'] == $moduleId) ? 'background:yellow' : '';
        echo "<tr style='{$highlight}'>";
        echo "<td>{$row['id_hook']}</td>";
        echo "<td><strong>{$row['h_name']}</strong></td>";
        echo "<td>{$row['position']}</td>";
        echo "<td>{$row['id_module']}</td>";
        echo "<td>{$row['m_name']}</td>";
        echo "<td>" . ($row['active'] ? '✓' : '✗') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' style='color:red'>❌ NO HAY RESULTADOS</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>2. Verificar tabla ps_module</h2>";

$moduleData = Db::getInstance()->getRow("
    SELECT * FROM `" . _DB_PREFIX_ . "module`
    WHERE id_module = {$moduleId}
");

echo "<table border='1' cellpadding='5'>";
foreach ($moduleData as $key => $value) {
    $highlight = '';
    if ($key === 'active' && $value == 0) {
        $highlight = 'background:red;color:white';
    }
    echo "<tr style='{$highlight}'><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>3. Verificar tabla ps_hook_module directamente</h2>";

$hookModuleData = Db::getInstance()->executeS("
    SELECT hm.*, h.name as hook_name
    FROM `" . _DB_PREFIX_ . "hook_module` hm
    JOIN `" . _DB_PREFIX_ . "hook` h ON h.id_hook = hm.id_hook
    WHERE hm.id_module = {$moduleId}
    AND h.name IN (
        'actionCustomerGridQueryBuilderModifier',
        'actionOrderGridQueryBuilderModifier',
        'actionAddressGridQueryBuilderModifier'
    )
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID Hook Module</th><th>Hook Name</th><th>ID Hook</th><th>ID Module</th><th>Position</th></tr>";
foreach ($hookModuleData as $row) {
    echo "<tr>";
    echo "<td>{$row['id_hook_module']}</td>";
    echo "<td><strong>{$row['hook_name']}</strong></td>";
    echo "<td>{$row['id_hook']}</td>";
    echo "<td>{$row['id_module']}</td>";
    echo "<td>{$row['position']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>4. Probar Hook::exec() manualmente</h2>";

// Intentar ejecutar el hook manualmente
echo "<p>Intentando ejecutar hook manualmente...</p>";

$module = Module::getInstanceByName('personalsalesmen');

if ($module) {
    // Simular parámetros del hook
    $fakeParams = [
        'search_query_builder' => new stdClass()
    ];

    echo "<p>Llamando a hookActionCustomerGridQueryBuilderModifier()...</p>";

    try {
        ob_start();
        $module->hookActionCustomerGridQueryBuilderModifier($fakeParams);
        $output = ob_get_clean();

        echo "<p style='color:green'>✓ Método ejecutado correctamente</p>";

        if (!empty($output)) {
            echo "<p>Output: <pre>{$output}</pre></p>";
        }

        // Verificar si se creó el log
        $logFile = _PS_MODULE_DIR_ . 'personalsalesmen/hook_test.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $lastLines = array_slice(explode("\n", trim($logContent)), -5);

            echo "<p style='color:green'>✓ hook_test.log actualizado:</p>";
            echo "<pre style='background:#e0ffe0;padding:10px'>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
        } else {
            echo "<p style='color:red'>✗ hook_test.log NO se creó</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Error al ejecutar: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<h2>5. Verificar Hook::getHookModuleExecList() completo</h2>";

Hook::resetStaticCache();
$allHooks = Hook::getHookModuleExecList();

echo "<p>Total de hooks en caché: " . count($allHooks) . "</p>";

// Buscar hooks que contengan "Grid" o "QueryBuilder"
$gridHooks = [];
foreach ($allHooks as $hookName => $modules) {
    if (stripos($hookName, 'Grid') !== false || stripos($hookName, 'QueryBuilder') !== false) {
        $gridHooks[$hookName] = count($modules);
    }
}

echo "<h3>Hooks de Grid/QueryBuilder en caché (" . count($gridHooks) . "):</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Hook Name</th><th>Módulos registrados</th></tr>";

$targetHooks = [
    'actionCustomerGridQueryBuilderModifier',
    'actionOrderGridQueryBuilderModifier',
    'actionAddressGridQueryBuilderModifier'
];

foreach ($gridHooks as $hookName => $count) {
    $isTarget = in_array($hookName, $targetHooks);
    $bg = $isTarget ? 'background:yellow' : '';

    echo "<tr style='{$bg}'>";
    echo "<td><strong>{$hookName}</strong></td>";
    echo "<td>{$count}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>🔍 DIAGNÓSTICO</h2>";

if (count($result) === 3) {
    echo "<p style='background:green;color:white;padding:10px'>";
    echo "✓ El SQL de PrestaShop SÍ devuelve nuestros 3 hooks correctamente.<br>";
    echo "Esto significa que los datos en BD están correctos.";
    echo "</p>";

    echo "<p style='background:orange;padding:10px'>";
    echo "⚠️ PERO los hooks NO aparecen en Hook::getHookModuleExecList().<br>";
    echo "Esto sugiere que PrestaShop está usando alguna CACHÉ PERSISTENTE que no se limpia con Tools::clearCache().<br><br>";
    echo "<strong>Posibles soluciones:</strong><br>";
    echo "1. Borrar manualmente: var/cache/prod/* y var/cache/dev/*<br>";
    echo "2. Reiniciar PHP-FPM o Apache (para limpiar OPcache)<br>";
    echo "3. Si usas Redis/Memcached, limpiar esa caché también";
    echo "</p>";

} else {
    echo "<p style='background:red;color:white;padding:10px'>";
    echo "❌ El SQL de PrestaShop NO devuelve nuestros hooks.<br>";
    echo "Revisa los datos en las tablas ps_module y ps_hook_module.";
    echo "</p>";
}

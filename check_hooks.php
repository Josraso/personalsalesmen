<?php
/**
 * Verificar qué hooks grid modifier existen en este PrestaShop
 */

require_once '../../config/config.inc.php';
require_once '../../init.php';

echo "<h1>Hooks Grid Modifier en este PrestaShop</h1>";

// Buscar todos los hooks que contengan "Grid" en el nombre
$sql = "SELECT id_hook, name, title, description
        FROM " . _DB_PREFIX_ . "hook
        WHERE name LIKE '%Grid%' OR name LIKE '%QueryBuilder%'
        ORDER BY name";

$hooks = Db::getInstance()->executeS($sql);

echo "<h2>Hooks encontrados (" . count($hooks) . "):</h2>";

if (empty($hooks)) {
    echo "<p style='color:red'>❌ NO SE ENCONTRARON HOOKS DE GRID/QUERYBUILDER</p>";
    echo "<p>Esto significa que PrestaShop 8/9 NO usa estos hooks, usa otro sistema.</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Título</th><th>Descripción</th></tr>";

    foreach ($hooks as $hook) {
        $highlight = (strpos($hook['name'], 'Customer') !== false ||
                     strpos($hook['name'], 'Order') !== false ||
                     strpos($hook['name'], 'Address') !== false) ?
                     'background:yellow' : '';

        echo "<tr style='{$highlight}'>";
        echo "<td>{$hook['id_hook']}</td>";
        echo "<td><strong>{$hook['name']}</strong></td>";
        echo "<td>{$hook['title']}</td>";
        echo "<td>{$hook['description']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>Verificar si módulo está activo</h2>";

$module = Module::getInstanceByName('personalsalesmen');
if ($module && Module::isEnabled('personalsalesmen')) {
    echo "<p style='color:green'>✓ Módulo 'personalsalesmen' está ACTIVO</p>";
    echo "<p>Version: " . $module->version . "</p>";
} else {
    echo "<p style='color:red'>✗ Módulo 'personalsalesmen' NO está activo</p>";
}

echo "<hr>";
echo "<h2>¿Cómo se filtran grids en PrestaShop 8/9?</h2>";
echo "<p>Si no hay hooks Grid/QueryBuilder, PrestaShop 8/9 puede usar:</p>";
echo "<ul>";
echo "<li><strong>Grid Definition</strong> - Sobrescribir clases de definición de grid</li>";
echo "<li><strong>Grid Query Hooks</strong> - Hooks diferentes a los de 1.7</li>";
echo "<li><strong>Doctrine Filters</strong> - Filtros a nivel de repositorio</li>";
echo "<li><strong>Event Subscribers</strong> - Sistema de eventos Symfony</li>";
echo "</ul>";

echo "<p style='background:yellow;padding:10px'><strong>IMPORTANTE:</strong> Si no aparecen hooks QueryBuilder arriba, necesitamos cambiar completamente el enfoque y usar el sistema correcto de PrestaShop 8/9.</p>";

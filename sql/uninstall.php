<?php
/**
 * SQL Uninstallation Script
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

// Eliminar tabla de asignaciones
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'personalsalesmen_assignment`';

// Ejecutar queries
foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
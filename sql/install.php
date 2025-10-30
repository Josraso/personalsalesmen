<?php
/**
 * SQL Installation Script
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

// Tabla principal de asignaciones
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'personalsalesmen_assignment` (
    `id_assignment` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_employee` INT(11) UNSIGNED NOT NULL,
    `id_customer` INT(11) UNSIGNED NULL DEFAULT NULL,
    `id_group` INT(11) UNSIGNED NULL DEFAULT NULL,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_assignment`),
    UNIQUE KEY `unique_assignment` (`id_employee`, `id_customer`, `id_group`),
    KEY `idx_employee_active` (`id_employee`, `active`),
    KEY `idx_customer_active` (`id_customer`, `active`),
    KEY `idx_group_active` (`id_group`, `active`),
    KEY `idx_date_add` (`date_add`),
    CONSTRAINT `check_customer_or_group` CHECK (
        (`id_customer` IS NOT NULL AND `id_group` IS NULL) OR
        (`id_customer` IS NULL AND `id_group` IS NOT NULL)
    )
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

// Ejecutar queries
foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
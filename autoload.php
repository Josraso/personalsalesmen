<?php
/**
 * Simple Autoloader for Personal Salesmen Module
 * No Composer required - works out of the box
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// PSR-4 Autoloader for PrestaShop\Module\PersonalSalesmen namespace
spl_autoload_register(function ($class) {
    // Prefix del namespace
    $prefix = 'PrestaShop\\Module\\PersonalSalesmen\\';

    // Base directory para el namespace prefix
    $base_dir = __DIR__ . '/src/';

    // Verificar si la clase usa el namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, pasar a la siguiente función autoload registrada
        return;
    }

    // Obtener el nombre relativo de la clase
    $relative_class = substr($class, $len);

    // Reemplazar el namespace prefix con el base directory
    // Reemplazar separadores de namespace con separadores de directorio
    // Agregar .php al final
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si el archivo existe, cargarlo
    if (file_exists($file)) {
        require_once $file;
    }
});

// Cargar clases adicionales si son necesarias
// (PrestaShop ya tiene sus propios autoloaders para clases core)

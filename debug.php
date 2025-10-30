<?php
/**
 * DEBUG SCRIPT - Personal Salesmen Module
 *
 * INSTRUCCIONES:
 * 1. Sube este archivo a: /modules/personalsalesmen/debug.php
 * 2. Desde el BACKOFFICE, accede a:
 *    https://tu-tienda.com/modules/personalsalesmen/debug.php?token=debug123
 * 3. Copia TODA la salida y envíamela
 */

// Bootstrap PrestaShop
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/autoload.php';

use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository;

header('Content-Type: text/plain; charset=utf-8');

echo "=== PERSONAL SALESMEN MODULE - DEBUG INFO ===\n\n";

// 1. Info del empleado actual
$context = Context::getContext();
$employee = $context->employee;

// Verificar si hay un empleado logueado
if (!$employee || !Validate::isLoadedObject($employee)) {
    echo "ERROR CRÍTICO: No hay empleado logueado o la sesión no es válida.\n";
    echo "\nINSTRUCCIONES:\n";
    echo "1. Asegúrate de estar logueado en el backoffice de PrestaShop\n";
    echo "2. Abre esta URL en una nueva pestaña del MISMO navegador donde estás logueado\n";
    echo "3. O mejor, copia el contenido de este archivo y ejecútalo desde el módulo\n\n";
    echo "INFO DE CONTEXTO:\n";
    echo "- Context employee exists: " . (isset($context->employee) ? 'YES' : 'NO') . "\n";
    echo "- Cookie exists: " . (isset($context->cookie) ? 'YES' : 'NO') . "\n";
    if (isset($context->cookie)) {
        echo "- Cookie id_employee: " . (int)$context->cookie->id_employee . "\n";
        echo "- Cookie email: " . ($context->cookie->email ?? 'N/A') . "\n";
    }
    die("\n=== FIN DEBUG (CON ERRORES) ===\n");
}

echo "1. EMPLEADO ACTUAL:\n";
echo "   - ID: " . (int)$employee->id . "\n";
echo "   - Nombre: " . $employee->firstname . " " . $employee->lastname . "\n";
echo "   - Email: " . $employee->email . "\n";
echo "   - Profile ID: " . (int)$employee->id_profile . "\n";
echo "   - Profile Name: ";
try {
    $profile = new Profile($employee->id_profile);
    echo (isset($profile->name[1]) ? $profile->name[1] : 'N/A') . "\n\n";
} catch (Exception $e) {
    echo "ERROR al cargar perfil\n\n";
}

// 2. Configuración del módulo
echo "2. CONFIGURACIÓN DEL MÓDULO:\n";
echo "   - PSM_RESTRICTION_ENABLED: " . (Configuration::get('PSM_RESTRICTION_ENABLED') ? 'YES (ON)' : 'NO (OFF)') . "\n";
echo "   - PSM_EMAIL_NOTIFICATIONS: " . (Configuration::get('PSM_EMAIL_NOTIFICATIONS') ? 'YES' : 'NO') . "\n\n";

// 3. AccessControlService
$accessControl = new AccessControlService($context);

echo "3. ACCESS CONTROL:\n";
echo "   - Can See Everything: " . ($accessControl->canSeeEverything() ? 'YES (no restrictions)' : 'NO (should be filtered)') . "\n";
echo "   - Has Restrictions: " . ($accessControl->hasRestrictions() ? 'YES' : 'NO') . "\n";
echo "   - Can Manage Assignments: " . ($accessControl->canManageAssignments() ? 'YES' : 'NO') . "\n\n";

// 4. Asignaciones para este empleado
$repository = new AssignmentRepository();
$assignments = $repository->findByEmployee((int)$employee->id);

echo "4. ASIGNACIONES PARA ESTE EMPLEADO:\n";
echo "   - Total: " . count($assignments) . "\n";

if (!empty($assignments)) {
    foreach ($assignments as $idx => $assignment) {
        echo "   [" . ($idx + 1) . "] ";
        if ($assignment['id_customer']) {
            $customer = new Customer($assignment['id_customer']);
            echo "Customer: " . $customer->firstname . " " . $customer->lastname . " (ID: " . $assignment['id_customer'] . ")";
        } elseif ($assignment['id_group']) {
            $group = new Group($assignment['id_group']);
            echo "Group: " . $group->name[1] . " (ID: " . $assignment['id_group'] . ")";
        }
        echo " - Active: " . ($assignment['active'] ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "   ¡NO HAY ASIGNACIONES PARA ESTE EMPLEADO!\n";
}
echo "\n";

// 5. IDs de clientes permitidos
$allowedIds = $accessControl->getAllowedCustomerIds();

echo "5. CLIENTES PERMITIDOS:\n";
echo "   - Total IDs: " . count($allowedIds) . "\n";
if (!empty($allowedIds)) {
    echo "   - IDs: " . implode(", ", array_slice($allowedIds, 0, 20));
    if (count($allowedIds) > 20) {
        echo " ... (+" . (count($allowedIds) - 20) . " more)";
    }
    echo "\n";
} else {
    echo "   - Array vacío = SIN RESTRICCIONES (ve todos) o SIN ASIGNACIONES\n";
}
echo "\n";

// 6. Verificar hooks registrados
echo "6. HOOKS REGISTRADOS:\n";
$module = Module::getInstanceByName('personalsalesmen');
if ($module) {
    // Obtener hooks del módulo desde la base de datos
    $sql = new DbQuery();
    $sql->select('h.name, hm.id_hook');
    $sql->from('hook_module', 'hm');
    $sql->innerJoin('hook', 'h', 'h.id_hook = hm.id_hook');
    $sql->where('hm.id_module = ' . (int)$module->id);
    $sql->orderBy('h.name ASC');

    $hooks = Db::getInstance()->executeS($sql);
    echo "   - Total hooks: " . count($hooks) . "\n";
    if ($hooks) {
        foreach ($hooks as $hook) {
            echo "   - " . $hook['name'] . "\n";
        }
    }
} else {
    echo "   ERROR: Module not found!\n";
}
echo "\n";

// 7. Test SQL Filter
echo "7. SQL FILTER TEST:\n";
$sqlFilter = $accessControl->getSQLFilter('c', 'id_customer');
echo "   - Filter: " . $sqlFilter . "\n\n";

echo "=== FIN DEBUG ===\n";

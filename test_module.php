<?php
/**
 * TEST DIRECTO - No depende de hooks
 */

// Bootstrap PrestaShop
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/autoload.php';

use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository;

header('Content-Type: text/html; charset=utf-8');

echo "<h1>TEST PERSONAL SALESMEN MODULE</h1>";

// 1. Verificar contexto
$context = Context::getContext();
$employee = $context->employee;

echo "<h2>1. EMPLEADO ACTUAL</h2>";
if (!$employee || !Validate::isLoadedObject($employee)) {
    echo "<p style='color:red;'><strong>ERROR: No hay empleado logueado</strong></p>";
    echo "<p>Asegúrate de estar logueado en el backoffice y abre este enlace desde la misma sesión.</p>";
    die();
}

echo "<ul>";
echo "<li><strong>ID:</strong> " . (int)$employee->id . "</li>";
echo "<li><strong>Nombre:</strong> " . htmlspecialchars($employee->firstname . ' ' . $employee->lastname) . "</li>";
echo "<li><strong>Email:</strong> " . htmlspecialchars($employee->email) . "</li>";
echo "<li><strong>Profile ID:</strong> " . (int)$employee->id_profile . "</li>";
echo "</ul>";

// 2. Verificar configuración
echo "<h2>2. CONFIGURACIÓN</h2>";
$restrictionEnabled = Configuration::get('PSM_RESTRICTION_ENABLED');
echo "<ul>";
echo "<li><strong>PSM_RESTRICTION_ENABLED:</strong> <span style='color:" . ($restrictionEnabled ? 'green' : 'red') . ";'>" . ($restrictionEnabled ? 'YES (ON)' : 'NO (OFF)') . "</span></li>";
echo "</ul>";

// 3. AccessControlService
echo "<h2>3. ACCESS CONTROL SERVICE</h2>";
$accessControl = new AccessControlService($context);

echo "<ul>";
$canSeeEverything = $accessControl->canSeeEverything();
echo "<li><strong>canSeeEverything:</strong> <span style='color:" . ($canSeeEverything ? 'red' : 'green') . ";'>" . ($canSeeEverything ? 'YES (NO restrictions)' : 'NO (SHOULD filter)') . "</span></li>";
echo "<li><strong>hasRestrictions:</strong> " . ($accessControl->hasRestrictions() ? 'YES' : 'NO') . "</li>";
echo "</ul>";

// 4. Asignaciones
echo "<h2>4. ASIGNACIONES</h2>";
$repository = new AssignmentRepository();
$assignments = $repository->findByEmployee((int)$employee->id);

echo "<p><strong>Total asignaciones:</strong> " . count($assignments) . "</p>";

if (!empty($assignments)) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Tipo</th><th>ID</th><th>Nombre</th><th>Activo</th></tr>";
    foreach ($assignments as $assignment) {
        echo "<tr>";
        if ($assignment['id_customer']) {
            $customer = new Customer($assignment['id_customer']);
            echo "<td>Cliente</td>";
            echo "<td>" . $assignment['id_customer'] . "</td>";
            echo "<td>" . htmlspecialchars($customer->firstname . ' ' . $customer->lastname) . "</td>";
        } elseif ($assignment['id_group']) {
            $group = new Group($assignment['id_group']);
            echo "<td>Grupo</td>";
            echo "<td>" . $assignment['id_group'] . "</td>";
            echo "<td>" . htmlspecialchars($group->name[1] ?? 'N/A') . "</td>";
        }
        echo "<td>" . ($assignment['active'] ? '<span style="color:green;">SÍ</span>' : '<span style="color:red;">NO</span>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'><strong>NO HAY ASIGNACIONES para este empleado</strong></p>";
}

// 5. IDs permitidos
echo "<h2>5. CLIENTES PERMITIDOS</h2>";
$allowedIds = $accessControl->getAllowedCustomerIds();

echo "<p><strong>Total IDs permitidos:</strong> " . count($allowedIds) . "</p>";

if (!empty($allowedIds)) {
    echo "<p><strong>IDs:</strong> " . implode(', ', array_slice($allowedIds, 0, 50));
    if (count($allowedIds) > 50) {
        echo " ... (+" . (count($allowedIds) - 50) . " más)";
    }
    echo "</p>";
} else {
    echo "<p style='color:red;'><strong>Array VACÍO = Sin asignaciones O puede ver todo</strong></p>";
}

// 6. Verificar hooks registrados
echo "<h2>6. HOOKS REGISTRADOS EN BD</h2>";
$module = Module::getInstanceByName('personalsalesmen');
if ($module) {
    $sql = new DbQuery();
    $sql->select('h.name');
    $sql->from('hook_module', 'hm');
    $sql->innerJoin('hook', 'h', 'h.id_hook = hm.id_hook');
    $sql->where('hm.id_module = ' . (int)$module->id);
    $sql->orderBy('h.name ASC');

    $hooks = Db::getInstance()->executeS($sql);

    echo "<p><strong>Total hooks:</strong> " . count($hooks) . "</p>";

    if ($hooks) {
        echo "<ul>";
        foreach ($hooks as $hook) {
            $isImportant = in_array($hook['name'], ['actionCustomerGridQueryBuilderModifier', 'actionOrderGridQueryBuilderModifier', 'actionAddressGridQueryBuilderModifier']);
            echo "<li" . ($isImportant ? " style='color:green;font-weight:bold;'" : "") . ">" . htmlspecialchars($hook['name']) . "</li>";
        }
        echo "</ul>";

        // Verificar hooks críticos
        $requiredHooks = ['actionCustomerGridQueryBuilderModifier', 'actionOrderGridQueryBuilderModifier', 'actionAddressGridQueryBuilderModifier'];
        $registeredHookNames = array_column($hooks, 'name');

        $missingHooks = array_diff($requiredHooks, $registeredHookNames);

        if (!empty($missingHooks)) {
            echo "<p style='color:red;'><strong>FALTAN ESTOS HOOKS CRÍTICOS:</strong></p>";
            echo "<ul>";
            foreach ($missingHooks as $hook) {
                echo "<li style='color:red;'>" . $hook . "</li>";
            }
            echo "</ul>";
            echo "<p><strong>SOLUCIÓN:</strong> Desinstala y reinstala el módulo, o usa el botón 'Update Hooks'</p>";
        } else {
            echo "<p style='color:green;'><strong>✓ Todos los hooks críticos están registrados</strong></p>";
        }
    }
} else {
    echo "<p style='color:red;'><strong>ERROR: No se pudo cargar el módulo</strong></p>";
}

// 7. DIAGNÓSTICO FINAL
echo "<h2>7. DIAGNÓSTICO</h2>";

if ($employee->id_profile == 1) {
    echo "<p style='background:#e3f2fd;padding:15px;border-left:4px solid #2196F3;'>";
    echo "<strong>ℹ️ Eres SuperAdmin (Profile ID = 1)</strong><br>";
    echo "Los SuperAdmin SIEMPRE ven TODO. Esto es normal y correcto.<br>";
    echo "<strong>SOLUCIÓN:</strong> Loguéate con un empleado que tenga perfil 'Vendedor' u otro perfil diferente a SuperAdmin.";
    echo "</p>";
} elseif (!$restrictionEnabled) {
    echo "<p style='background:#fff3e0;padding:15px;border-left:4px solid #ff9800;'>";
    echo "<strong>⚠️ Restricciones DESACTIVADAS</strong><br>";
    echo "PSM_RESTRICTION_ENABLED está en OFF.<br>";
    echo "<strong>SOLUCIÓN:</strong> Ve a Módulos > Personal Salesmen > Configurar y activa 'Enable Access Restriction'.";
    echo "</p>";
} elseif (empty($assignments)) {
    echo "<p style='background:#ffebee;padding:15px;border-left:4px solid #f44336;'>";
    echo "<strong>✗ NO TIENES ASIGNACIONES</strong><br>";
    echo "Este empleado no tiene clientes ni grupos asignados.<br>";
    echo "<strong>SOLUCIÓN:</strong> Como SuperAdmin, ve a Clientes > Personal Salesmen y crea asignaciones para este empleado.";
    echo "</p>";
} elseif (empty($allowedIds)) {
    echo "<p style='background:#ffebee;padding:15px;border-left:4px solid #f44336;'>";
    echo "<strong>✗ ASIGNACIONES SIN CLIENTES</strong><br>";
    echo "Tienes asignaciones pero no se obtienen IDs de clientes.<br>";
    echo "<strong>POSIBLE CAUSA:</strong> Los clientes o grupos asignados no existen o están inactivos.";
    echo "</p>";
} else {
    echo "<p style='background:#e8f5e9;padding:15px;border-left:4px solid #4caf50;'>";
    echo "<strong>✓ CONFIGURACIÓN CORRECTA</strong><br>";
    echo "Tienes " . count($allowedIds) . " cliente(s) asignado(s) y las restricciones están activas.<br>";
    echo "<strong>Si sigues viendo TODOS los clientes:</strong><br>";
    echo "1. Los hooks NO se están ejecutando (verifica sección 6)<br>";
    echo "2. Limpia caché: <code>rm -rf var/cache/*</code><br>";
    echo "3. Si falta algún hook crítico, reinstala el módulo";
    echo "</p>";
}

echo "<hr>";
echo "<p><strong>Generado:</strong> " . date('Y-m-d H:i:s') . "</p>";

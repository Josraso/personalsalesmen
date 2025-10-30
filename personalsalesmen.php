<?php
/**
 * Personal Salesmen Module for PrestaShop 8 & 9
 *
 * @author    Your Name
 * @copyright 2025
 * @license   MIT
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/autoload.php';

use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\Module\PersonalSalesmen\Service\AssignmentService;
use PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository;

class PersonalSalesmen extends Module
{
    private $assignmentService;

    public function __construct()
    {
        $this->name = 'personalsalesmen';
        $this->tab = 'administration';
        $this->version = '5.0.0';
        $this->author = 'Community';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Personal Salesmen', [], 'Modules.Personalsalesmen.Admin');
        $this->description = $this->trans('Assign customers and groups to specific employees. Employees only see their assigned data.', [], 'Modules.Personalsalesmen.Admin');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];

        // Inicializar solo AssignmentService (AccessControlService se crea cuando se necesita)
        $repository = new AssignmentRepository();
        $this->assignmentService = new AssignmentService($repository);
    }

    /**
     * Obtener AccessControlService con contexto actual
     * IMPORTANTE: No cachear en propiedad, crear siempre nuevo para tener contexto actualizado
     */
    private function getAccessControl(): AccessControlService
    {
        return new AccessControlService($this->context);
    }

    /**
     * Instalación del módulo
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        // Crear tablas
        require_once __DIR__ . '/sql/install.php';

        // Configuración por defecto
        Configuration::updateValue('PSM_RESTRICTION_ENABLED', 1);
        Configuration::updateValue('PSM_EMAIL_NOTIFICATIONS', 1);

        // Registrar hooks
        return $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('actionCustomerGridQueryBuilderModifier')
            && $this->registerHook('actionOrderGridQueryBuilderModifier')
            && $this->registerHook('actionAddressGridQueryBuilderModifier')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionObjectCustomerAddAfter')
            && $this->registerHook('actionObjectCustomerUpdateAfter')
            && $this->registerHook('actionCustomerThreadsGridQueryBuilderModifier')
            && $this->installTab();
    }

    /**
     * Desinstalación del módulo
     */
    public function uninstall(): bool
    {
        // Eliminar tablas
        require_once __DIR__ . '/sql/uninstall.php';

        // Eliminar configuración
        Configuration::deleteByName('PSM_RESTRICTION_ENABLED');
        Configuration::deleteByName('PSM_EMAIL_NOTIFICATIONS');

        // Desinstalar tab
        $this->uninstallTab();

        return parent::uninstall();
    }

    /**
     * Instalar tab en el menú
     */
    private function installTab(): bool
    {
        $tab = new Tab();
        $tab->class_name = 'AdminPersonalSalesmen';
        $tab->module = $this->name;
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentCustomer');
        $tab->icon = 'people';
        $tab->active = 1;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int)$lang['id_lang']] = $this->trans('Personal Salesmen', [], 'Modules.Personalsalesmen.Admin', $lang['locale']);
        }

        return $tab->add();
    }

    /**
     * Desinstalar tab
     */
    private function uninstallTab(): bool
    {
        $idTab = (int)Tab::getIdFromClassName('AdminPersonalSalesmen');
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
        }
        return true;
    }

    /**
     * Página de configuración
     */
    public function getContent(): string
    {
        $output = '';

        // Actualizar hooks si se solicita
        if (Tools::isSubmit('updateHooks')) {
            $this->updateModuleHooks();
            $output .= $this->displayConfirmation($this->trans('Hooks updated successfully! Module is now ready to use.', [], 'Modules.Personalsalesmen.Admin'));
        }

        // Procesar formulario
        if (Tools::isSubmit('submitPersonalSalesmenConfig')) {
            Configuration::updateValue('PSM_RESTRICTION_ENABLED', (int)Tools::getValue('PSM_RESTRICTION_ENABLED'));
            Configuration::updateValue('PSM_EMAIL_NOTIFICATIONS', (int)Tools::getValue('PSM_EMAIL_NOTIFICATIONS'));

            $output .= $this->displayConfirmation($this->trans('Settings updated successfully.', [], 'Modules.Personalsalesmen.Admin'));
        }

        // Banner informativo
        $output .= '<div class="alert alert-info">
            <h4><i class="icon-info"></i> ' . $this->l('How to manage assignments') . '</h4>
            <p>' . $this->l('To assign customers to employees, go to:') . ' <strong>' . $this->l('Customers > Personal Salesmen') . '</strong></p>
            <p>' . $this->l('This page is only for general module configuration.') . '</p>
        </div>';

        // Botón de actualización de hooks
        $output .= '<div class="alert alert-warning">
            <h4><i class="icon-warning-sign"></i> ' . $this->l('After updating the module') . '</h4>
            <p>' . $this->l('If you just updated the module code, click this button to register new hooks:') . '</p>
            <form method="post" action="' . $_SERVER['REQUEST_URI'] . '">
                <button type="submit" name="updateHooks" class="btn btn-primary">
                    <i class="icon-refresh"></i> ' . $this->l('Update Hooks') . '
                </button>
            </form>
        </div>';

        return $output . $this->renderConfigForm();
    }

    /**
     * Actualizar hooks del módulo sin desinstalar
     */
    private function updateModuleHooks(): bool
    {
        // Registrar todos los hooks
        $hooks = [
            'actionAdminControllerSetMedia',
            'actionCustomerGridQueryBuilderModifier',
            'actionOrderGridQueryBuilderModifier',
            'actionAddressGridQueryBuilderModifier',
            'actionValidateOrder',
            'actionObjectCustomerAddAfter',
            'actionCustomerThreadsGridQueryBuilderModifier',
            'actionObjectCustomerUpdateAfter', // Para validar grupos
        ];

        $success = true;
        foreach ($hooks as $hookName) {
            if (!$this->isRegisteredInHook($hookName)) {
                $success = $success && $this->registerHook($hookName);
            }
        }

        return $success;
    }

    /**
     * Formulario de configuración
     */
    private function renderConfigForm(): string
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitPersonalSalesmenConfig';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value = [
            'PSM_RESTRICTION_ENABLED' => Configuration::get('PSM_RESTRICTION_ENABLED'),
            'PSM_EMAIL_NOTIFICATIONS' => Configuration::get('PSM_EMAIL_NOTIFICATIONS'),
        ];

        return $helper->generateForm([$this->getConfigFormStructure()]);
    }

    /**
     * Estructura del formulario de configuración
     */
    private function getConfigFormStructure(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Personalsalesmen.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable Access Restriction', [], 'Modules.Personalsalesmen.Admin'),
                        'name' => 'PSM_RESTRICTION_ENABLED',
                        'desc' => $this->trans('When enabled, employees can only see their assigned customers and orders.', [], 'Modules.Personalsalesmen.Admin'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Email Notifications', [], 'Modules.Personalsalesmen.Admin'),
                        'name' => 'PSM_EMAIL_NOTIFICATIONS',
                        'desc' => $this->trans('Send email to employees when their assigned customers place orders.', [], 'Modules.Personalsalesmen.Admin'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Enabled', [], 'Admin.Global')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('Disabled', [], 'Admin.Global')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];
    }

    /**
     * Hook: Bloquear acceso directo a recursos no permitidos
     * Intercepta accesos desde campanita, URLs directas, etc.
     * También inyecta JavaScript para filtrar grupos de clientes
     */
    public function hookActionAdminControllerSetMedia(): void
    {
        $accessControl = $this->getAccessControl();
        $controller = $this->context->controller->controller_name ?? '';

        // SuperAdmin puede acceder a todo - no aplicar restricciones
        if (!$accessControl->canSeeEverything()) {
            // Inyectar JavaScript para filtrar grupos en formulario de clientes
            if ($controller === 'AdminCustomers') {
                $this->injectGroupFilterScript($accessControl);
            }

            // Inyectar JavaScript para filtrar clientes en formulario de pedidos
            if ($controller === 'AdminOrders') {
                $this->injectCustomerFilterScript($accessControl);
            }

            // Controladores protegidos y sus parámetros de ID
            $protectedControllers = [
                'AdminCustomers' => 'id_customer',
                'AdminOrders' => 'id_order',
                'AdminAddresses' => 'id_address',
                'AdminCustomerThreads' => 'id_customer_thread',
            ];

            if (isset($protectedControllers[$controller])) {
                $idParam = $protectedControllers[$controller];
                $resourceId = (int)Tools::getValue($idParam);

                // También verificar vieworder, viewcustomer, etc.
                $isViewing = Tools::getValue('view' . strtolower(str_replace('Admin', '', $controller)))
                             || Tools::getValue('update' . strtolower(str_replace('Admin', '', $controller)));

                if ($resourceId > 0 || $isViewing) {
                    if ($resourceId === 0) {
                        $resourceId = (int)Tools::getValue('id_' . strtolower(str_replace('Admin', '', $controller)));
                    }

                    $customerId = $this->getCustomerIdFromResource($controller, $resourceId);

                    if ($customerId && !$accessControl->canAccessCustomer($customerId)) {
                        $this->context->controller->errors[] = $this->trans(
                            'Access denied. You do not have permission to view this resource.',
                            [],
                            'Modules.Personalsalesmen.Admin'
                        );

                        // Redirigir al listado correspondiente
                        $redirectController = $controller;
                        if ($controller === 'AdminCustomerThreads') {
                            $redirectController = 'AdminCustomerService';
                        }

                        Tools::redirectAdmin($this->context->link->getAdminLink($redirectController));
                        exit;
                    }
                }
            }
        }
    }

    /**
     * Inyectar JavaScript para filtrar clientes en formulario de pedidos
     */
    private function injectCustomerFilterScript(AccessControlService $accessControl): void
    {
        $allowedCustomerIds = $accessControl->getAllowedCustomerIds();

        // Si no tiene restricciones, no filtrar
        if (empty($allowedCustomerIds)) {
            return;
        }

        $allowedCustomersJson = json_encode(array_map('intval', $allowedCustomerIds));

        $script = "
        <script type='text/javascript'>
        (function() {
            var allowedCustomerIds = {$allowedCustomersJson};

            console.log('PersonalSalesmen: Filtering order customer selector. Allowed customers:', allowedCustomerIds.length);

            // Interceptar y filtrar resultados de autocomplete de clientes
            function filterCustomerSearch() {
                // PrestaShop 8/9 usa un input específico para buscar clientes al crear pedidos
                var customerSearchInput = document.querySelector('input[name=\"customer\"]') ||
                                         document.querySelector('#customer_search') ||
                                         document.querySelector('[data-action=\"search-customer\"]') ||
                                         document.querySelector('.js-customer-search');

                if (customerSearchInput) {
                    console.log('PersonalSalesmen: Found customer search input');

                    // Interceptar eventos de búsqueda
                    var originalFetch = window.fetch;
                    window.fetch = function() {
                        return originalFetch.apply(this, arguments).then(function(response) {
                            if (response.url && response.url.includes('customer')) {
                                return response.clone().json().then(function(data) {
                                    // Filtrar resultados por IDs permitidos
                                    if (Array.isArray(data)) {
                                        data = data.filter(function(customer) {
                                            return allowedCustomerIds.includes(parseInt(customer.id_customer || customer.id));
                                        });
                                    } else if (data.customers) {
                                        data.customers = data.customers.filter(function(customer) {
                                            return allowedCustomerIds.includes(parseInt(customer.id_customer || customer.id));
                                        });
                                    }

                                    return new Response(JSON.stringify(data), {
                                        status: response.status,
                                        statusText: response.statusText,
                                        headers: response.headers
                                    });
                                });
                            }
                            return response;
                        });
                    };
                } else {
                    // Reintentar si aún no se ha cargado
                    setTimeout(filterCustomerSearch, 500);
                }
            }

            // Ejecutar cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', filterCustomerSearch);
            } else {
                filterCustomerSearch();
            }

            // También ejecutar después de un segundo
            setTimeout(filterCustomerSearch, 1000);
        })();
        </script>
        ";

        echo $script;
    }

    /**
     * Inyectar JavaScript para filtrar grupos de clientes
     */
    private function injectGroupFilterScript(AccessControlService $accessControl): void
    {
        $allowedGroupIds = $accessControl->getAllowedGroupIds();
        $hasOnlyGroupAssignments = $accessControl->hasOnlyGroupAssignments();

        // Si no tiene asignaciones de grupo, no filtrar (puede usar cualquier grupo o ninguno)
        if (empty($allowedGroupIds)) {
            return;
        }

        // Crear array JavaScript con IDs permitidos
        $allowedGroupsJson = json_encode(array_map('intval', $allowedGroupIds));

        $script = "
        <script type='text/javascript'>
        (function() {
            function filterGroups() {
                var allowedGroupIds = {$allowedGroupsJson};
                var hasOnlyGroupAssignments = " . ($hasOnlyGroupAssignments ? 'true' : 'false') . ";

                console.log('PersonalSalesmen: Filtering groups. Allowed:', allowedGroupIds);

                // Intentar múltiples selectores para PrestaShop 8/9
                var selectors = [
                    'input[name=\"groupBox[]\"]',
                    'input[type=\"checkbox\"][id^=\"form_group_ids_\"]',
                    '.js-choice-options input[type=\"checkbox\"]',
                    '#customer_group input[type=\"checkbox\"]'
                ];

                var foundCheckboxes = false;

                selectors.forEach(function(selector) {
                    var checkboxes = document.querySelectorAll(selector);
                    if (checkboxes.length > 0) {
                        foundCheckboxes = true;
                        console.log('PersonalSalesmen: Found ' + checkboxes.length + ' group checkboxes with selector:', selector);

                        checkboxes.forEach(function(checkbox) {
                            // Extraer ID del grupo del value o del id
                            var groupId = parseInt(checkbox.value) || parseInt(checkbox.id.replace(/\\D/g, ''));

                            if (groupId && !allowedGroupIds.includes(groupId)) {
                                // Ocultar y deshabilitar grupos no permitidos
                                var row = checkbox.closest('tr') || checkbox.closest('.form-group') || checkbox.closest('.choice-option');
                                var label = checkbox.closest('label');

                                if (row) {
                                    row.style.display = 'none';
                                }
                                if (label) {
                                    label.style.display = 'none';
                                }

                                checkbox.disabled = true;
                                checkbox.checked = false;

                                console.log('PersonalSalesmen: Disabled group', groupId);
                            } else if (groupId && hasOnlyGroupAssignments) {
                                // Pre-seleccionar grupos permitidos
                                checkbox.checked = true;
                                console.log('PersonalSalesmen: Pre-selected group', groupId);
                            }
                        });
                    }
                });

                if (!foundCheckboxes) {
                    console.log('PersonalSalesmen: No group checkboxes found. Retrying in 500ms...');
                    setTimeout(filterGroups, 500);
                }
            }

            // Ejecutar al cargar y con retraso por si el DOM se construye dinámicamente
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', filterGroups);
            } else {
                filterGroups();
            }

            // Ejecutar también después de un segundo para capturar elementos cargados dinámicamente
            setTimeout(filterGroups, 1000);
        })();
        </script>
        ";

        echo $script;
    }

    /**
     * Hook: Filtrar grid de clientes
     */
    public function hookActionCustomerGridQueryBuilderModifier(array $params): void
    {
        error_log('PSM DEBUG: hookActionCustomerGridQueryBuilderModifier EJECUTADO');
        $this->applyAccessRestriction($params['search_query_builder'], 'c', 'id_customer');
    }

    /**
     * Hook: Filtrar grid de pedidos
     */
    public function hookActionOrderGridQueryBuilderModifier(array $params): void
    {
        error_log('PSM DEBUG: hookActionOrderGridQueryBuilderModifier EJECUTADO');

        $accessControl = $this->getAccessControl();

        $canSeeEverything = $accessControl->canSeeEverything();
        error_log('PSM DEBUG: canSeeEverything = ' . ($canSeeEverything ? 'TRUE' : 'FALSE'));

        if ($canSeeEverything) {
            error_log('PSM DEBUG: Employee can see everything, NO FILTER APPLIED');
            return;
        }

        $allowedIds = $accessControl->getAllowedCustomerIds();
        error_log('PSM DEBUG: allowedIds count = ' . count($allowedIds) . ' IDs: ' . implode(',', $allowedIds));

        if (empty($allowedIds)) {
            error_log('PSM DEBUG: NO allowed IDs, setting 1=0');
            $params['search_query_builder']->andWhere('1 = 0');
            return;
        }

        $filter = 'o.id_customer IN (' . implode(',', array_map('intval', $allowedIds)) . ')';
        error_log('PSM DEBUG: Applying filter: ' . $filter);

        // Usar o.id_customer directamente sin JOIN (evita conflicto de alias)
        $params['search_query_builder']->andWhere($filter);
    }

    /**
     * Hook: Filtrar grid de direcciones
     */
    public function hookActionAddressGridQueryBuilderModifier(array $params): void
    {
        error_log('PSM DEBUG: hookActionAddressGridQueryBuilderModifier EJECUTADO');
        $this->applyAccessRestriction($params['search_query_builder'], 'a', 'id_customer');
    }

    /**
     * Hook: Notificar cuando se valida un pedido
     */
    public function hookActionValidateOrder(array $params): void
    {
        if (!Configuration::get('PSM_EMAIL_NOTIFICATIONS')) {
            return;
        }

        $customer = $params['customer'];
        $order = $params['order'];

        $employees = $this->assignmentService->getAssignedEmployees((int)$customer->id);

        foreach ($employees as $employee) {
            $this->sendOrderNotification($employee, $customer, $order);
        }
    }

    /**
     * Hook: Auto-asignar cliente creado por empleado restringido
     */
    public function hookActionObjectCustomerAddAfter(array $params): void
    {
        $accessControl = $this->getAccessControl();

        // Solo auto-asignar si es un empleado con restricciones (no SuperAdmin)
        if ($accessControl->canSeeEverything()) {
            return;
        }

        $customer = $params['object'];
        if (!Validate::isLoadedObject($customer)) {
            return;
        }

        $employeeId = (int)$this->context->employee->id;

        // Crear asignación automática
        $result = $this->assignmentService->createAssignment($employeeId, (int)$customer->id, null);

        // Log para debug
        if (!$result['success']) {
            error_log('PersonalSalesmen: Failed to auto-assign customer ' . $customer->id . ' to employee ' . $employeeId . ': ' . $result['error']);
        }
    }

    /**
     * Hook: Validar grupos al actualizar cliente
     */
    public function hookActionObjectCustomerUpdateAfter(array $params): void
    {
        $accessControl = $this->getAccessControl();

        // Solo validar si es un empleado con restricciones
        if ($accessControl->canSeeEverything()) {
            return;
        }

        // Obtener grupos permitidos
        $allowedGroupIds = $accessControl->getAllowedGroupIds();

        // Si no tiene restricciones de grupo, permitir todo
        if (empty($allowedGroupIds)) {
            return;
        }

        $customer = $params['object'];
        if (!Validate::isLoadedObject($customer)) {
            return;
        }

        // Verificar que los grupos asignados estén permitidos
        $customerGroups = $customer->getGroups();

        foreach ($customerGroups as $groupId) {
            if (!in_array($groupId, $allowedGroupIds)) {
                // Remover grupo no autorizado
                $customer->removeGroup($groupId);
                error_log('PersonalSalesmen: Employee ' . $this->context->employee->id . ' tried to assign unauthorized group ' . $groupId);
            }
        }
    }

    /**
     * Hook: Filtrar grid de Customer Threads (Servicio al Cliente)
     */
    public function hookActionCustomerThreadsGridQueryBuilderModifier(array $params): void
    {
        $accessControl = $this->getAccessControl();

        if ($accessControl->canSeeEverything()) {
            return;
        }

        $allowedIds = $accessControl->getAllowedCustomerIds();

        if (empty($allowedIds)) {
            $params['search_query_builder']->andWhere('1 = 0');
            return;
        }

        // Filtrar por id_customer en customer_thread
        $params['search_query_builder']
            ->andWhere('ct.id_customer IN (' . implode(',', array_map('intval', $allowedIds)) . ')');
    }

    /**
     * Aplicar restricción de acceso a query builder
     */
    private function applyAccessRestriction($queryBuilder, string $alias, string $field): void
    {
        error_log("PSM DEBUG: applyAccessRestriction called for {$alias}.{$field}");

        $accessControl = $this->getAccessControl();

        $empId = $this->context->employee->id ?? 0;
        $profId = $this->context->employee->id_profile ?? 0;
        error_log("PSM DEBUG: Employee ID={$empId}, Profile ID={$profId}");

        $canSeeEverything = $accessControl->canSeeEverything();
        error_log("PSM DEBUG: canSeeEverything = " . ($canSeeEverything ? 'TRUE' : 'FALSE'));

        if ($canSeeEverything) {
            error_log("PSM DEBUG: NO FILTER - Employee can see everything");
            return;
        }

        $allowedIds = $accessControl->getAllowedCustomerIds();
        error_log("PSM DEBUG: Allowed IDs count = " . count($allowedIds) . ", IDs: " . implode(',', array_slice($allowedIds, 0, 20)));

        if (empty($allowedIds)) {
            error_log("PSM DEBUG: EMPTY allowed IDs - Setting 1=0");
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $filter = $alias . '.' . $field . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')';
        error_log("PSM DEBUG: Applying filter: {$filter}");
        $queryBuilder->andWhere($filter);
    }

    /**
     * Obtener customer_id desde un recurso
     */
    private function getCustomerIdFromResource(string $controller, int $resourceId): ?int
    {
        switch ($controller) {
            case 'AdminCustomers':
                return $resourceId;

            case 'AdminOrders':
                $order = new Order($resourceId);
                return $order->id_customer ?: null;

            case 'AdminAddresses':
                $address = new Address($resourceId);
                return $address->id_customer ?: null;

            case 'AdminCustomerThreads':
                // Obtener id_customer desde customer_thread
                $sql = new DbQuery();
                $sql->select('id_customer');
                $sql->from('customer_thread');
                $sql->where('id_customer_thread = ' . (int)$resourceId);
                $customerId = Db::getInstance()->getValue($sql);
                return $customerId ? (int)$customerId : null;

            default:
                return null;
        }
    }

    /**
     * Enviar notificación de pedido
     */
    private function sendOrderNotification(Employee $employee, Customer $customer, Order $order): void
    {
        $templateVars = [
            '{employee_name}' => $employee->firstname . ' ' . $employee->lastname,
            '{customer_name}' => $customer->firstname . ' ' . $customer->lastname,
            '{order_reference}' => $order->reference,
            '{order_total}' => Tools::displayPrice($order->total_paid, new Currency($order->id_currency)),
            '{order_link}' => $this->context->link->getAdminLink('AdminOrders', true, [], [
                'id_order' => $order->id,
                'vieworder' => 1
            ]),
        ];

        Mail::send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'new_order_assigned',
            $this->trans('New order from your assigned customer', [], 'Modules.Personalsalesmen.Admin'),
            $templateVars,
            $employee->email,
            $employee->firstname . ' ' . $employee->lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . $this->name . '/views/templates/emails/'
        );
    }
}
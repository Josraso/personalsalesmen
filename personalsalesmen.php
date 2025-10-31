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

        // Registrar hooks Y transplantarlos (asignar posición)
        $hooks = [
            'actionAdminControllerSetMedia',
            'actionCustomerGridQueryBuilderModifier',
            'actionOrderGridQueryBuilderModifier',
            'actionAddressGridQueryBuilderModifier',
            'actionValidateOrder',
        ];

        foreach ($hooks as $hookName) {
            if (!$this->registerHook($hookName)) {
                return false;
            }

            // CRÍTICO: Transplant para asegurar que tiene posición
            $this->transplantHookWithPosition($hookName);
        }

        return $this->installTab();
    }

    /**
     * Asegurar que el hook tiene posición asignada
     */
    private function transplantHookWithPosition(string $hookName): bool
    {
        $hookId = Hook::getIdByName($hookName);
        if (!$hookId) {
            return false;
        }

        // Actualizar posición en hook_module (asegurar que existe)
        $sql = "UPDATE `" . _DB_PREFIX_ . "hook_module`
                SET position = 1
                WHERE id_module = " . (int)$this->id . "
                AND id_hook = " . (int)$hookId;

        Db::getInstance()->execute($sql);

        return true;
    }

    /**
     * Método público para reparar hooks (llamar desde configuración)
     */
    public function fixHookPositions(): bool
    {
        $hooks = [
            'actionAdminControllerSetMedia',
            'actionCustomerGridQueryBuilderModifier',
            'actionOrderGridQueryBuilderModifier',
            'actionAddressGridQueryBuilderModifier',
            'actionValidateOrder',
        ];

        foreach ($hooks as $hookName) {
            $hookId = Hook::getIdByName($hookName);
            if (!$hookId) {
                continue;
            }

            // Asegurar que el hook está registrado
            if (!$this->isRegisteredInHook($hookName)) {
                $this->registerHook($hookName);
            }

            // Forzar posición
            Db::getInstance()->execute("
                UPDATE `" . _DB_PREFIX_ . "hook_module`
                SET position = 1
                WHERE id_module = " . (int)$this->id . "
                AND id_hook = " . (int)$hookId . "
            ");
        }

        // Limpiar caché
        Tools::clearCache();

        return true;
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

        // Procesar formulario de configuración
        if (Tools::isSubmit('submitPersonalSalesmenConfig')) {
            Configuration::updateValue('PSM_RESTRICTION_ENABLED', (int)Tools::getValue('PSM_RESTRICTION_ENABLED'));
            Configuration::updateValue('PSM_EMAIL_NOTIFICATIONS', (int)Tools::getValue('PSM_EMAIL_NOTIFICATIONS'));

            $output .= $this->displayConfirmation($this->trans('Settings updated successfully.', [], 'Modules.Personalsalesmen.Admin'));
        }

        // Procesar reparación de hooks
        if (Tools::isSubmit('submitFixHooks')) {
            if ($this->fixHookPositions()) {
                $output .= $this->displayConfirmation($this->l('Hook positions updated successfully. Cache cleared.'));
            } else {
                $output .= $this->displayError($this->l('Error updating hook positions.'));
            }
        }

        // Banner informativo
        $output .= '<div class="alert alert-info">
            <h4><i class="icon-info"></i> ' . $this->l('How to manage assignments') . '</h4>
            <p>' . $this->l('To assign customers to employees, go to:') . ' <strong>' . $this->l('Customers > Personal Salesmen') . '</strong></p>
            <p>' . $this->l('This page is only for general module configuration.') . '</p>
        </div>';

        return $output . $this->renderFixHooksButton() . $this->renderConfigForm();
    }

    /**
     * Botón para reparar hooks
     */
    private function renderFixHooksButton(): string
    {
        $currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $token = Tools::getAdminTokenLite('AdminModules');

        return '<div class="panel">
            <div class="panel-heading">
                <i class="icon-wrench"></i> ' . $this->l('Hook Diagnostics & Repair') . '
            </div>
            <div class="panel-body">
                <p>' . $this->l('If restrictions are not working, hooks may not have positions assigned.') . '</p>
                <p>' . $this->l('Click the button below to force hook position registration.') . '</p>
                <form method="post" action="' . $currentIndex . '&token=' . $token . '">
                    <button type="submit" name="submitFixHooks" class="btn btn-warning">
                        <i class="icon-refresh"></i> ' . $this->l('Fix Hook Positions') . '
                    </button>
                </form>
            </div>
        </div>';
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
     */
    public function hookActionAdminControllerSetMedia(): void
    {
        $accessControl = $this->getAccessControl();
        $controller = $this->context->controller->controller_name ?? '';
        $protectedControllers = ['AdminOrders', 'AdminCustomers', 'AdminAddresses'];

        if (!in_array($controller, $protectedControllers) || $accessControl->canSeeEverything()) {
            return;
        }

        // Obtener ID del recurso
        $idParam = 'id_' . strtolower(str_replace('Admin', '', $controller));
        $resourceId = (int)Tools::getValue($idParam);

        if ($resourceId > 0) {
            $customerId = $this->getCustomerIdFromResource($controller, $resourceId);

            if ($customerId && !$accessControl->canAccessCustomer($customerId)) {
                $this->context->controller->errors[] = $this->trans(
                    'You do not have permission to access this resource.',
                    [],
                    'Modules.Personalsalesmen.Admin'
                );
                Tools::redirectAdmin($this->context->link->getAdminLink($controller));
            }
        }
    }

    /**
     * Hook: Filtrar grid de clientes
     */
    public function hookActionCustomerGridQueryBuilderModifier(array $params): void
    {
        echo "<!-- PSM DEBUG: hookActionCustomerGridQueryBuilderModifier CALLED -->\n";
        $this->applyAccessRestriction($params['search_query_builder'], 'c', 'id_customer');
    }

    /**
     * Hook: Filtrar grid de pedidos
     */
    public function hookActionOrderGridQueryBuilderModifier(array $params): void
    {
        echo "<!-- PSM DEBUG: hookActionOrderGridQueryBuilderModifier CALLED -->\n";
        $accessControl = $this->getAccessControl();

        if ($accessControl->canSeeEverything()) {
            echo "<!-- PSM DEBUG: canSeeEverything=TRUE, NO FILTER -->\n";
            return;
        }

        $allowedIds = $accessControl->getAllowedCustomerIds();
        echo "<!-- PSM DEBUG: Allowed IDs count=" . count($allowedIds) . " -->\n";

        if (empty($allowedIds)) {
            echo "<!-- PSM DEBUG: EMPTY IDs, setting 1=0 -->\n";
            $params['search_query_builder']->andWhere('1 = 0');
            return;
        }

        $filter = 'o.id_customer IN (' . implode(',', array_map('intval', $allowedIds)) . ')';
        echo "<!-- PSM DEBUG: Applying filter: {$filter} -->\n";

        // Usar o.id_customer directamente sin JOIN (evita conflicto de alias)
        $params['search_query_builder']->andWhere($filter);
    }

    /**
     * Hook: Filtrar grid de direcciones
     */
    public function hookActionAddressGridQueryBuilderModifier(array $params): void
    {
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
     * Aplicar restricción de acceso a query builder
     */
    private function applyAccessRestriction($queryBuilder, string $alias, string $field): void
    {
        echo "<!-- PSM DEBUG: applyAccessRestriction for {$alias}.{$field} -->\n";
        $accessControl = $this->getAccessControl();

        $empId = $this->context->employee->id ?? 0;
        $profId = $this->context->employee->id_profile ?? 0;
        echo "<!-- PSM DEBUG: Employee ID={$empId}, Profile={$profId} -->\n";

        if ($accessControl->canSeeEverything()) {
            echo "<!-- PSM DEBUG: canSeeEverything=TRUE, NO FILTER -->\n";
            return;
        }

        $allowedIds = $accessControl->getAllowedCustomerIds();
        echo "<!-- PSM DEBUG: Allowed IDs count=" . count($allowedIds) . " IDs:" . implode(',', array_slice($allowedIds, 0, 10)) . " -->\n";

        if (empty($allowedIds)) {
            echo "<!-- PSM DEBUG: EMPTY IDs, setting 1=0 -->\n";
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $filter = $alias . '.' . $field . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')';
        echo "<!-- PSM DEBUG: Applying filter: {$filter} -->\n";
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
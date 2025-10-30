<?php
/**
 * Admin Controller for Personal Salesmen
 * Legacy controller for menu functionality
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'personalsalesmen/autoload.php';

use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\Module\PersonalSalesmen\Service\AssignmentService;
use PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository;

class AdminPersonalSalesmenController extends ModuleAdminController
{
    private $accessControl;
    private $assignmentService;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'personalsalesmen_assignment';
        $this->className = 'Assignment';
        $this->lang = false;
        $this->context = Context::getContext();

        parent::__construct();

        $this->meta_title = $this->l('Personal Salesmen - Manage Assignments');

        // Inicializar servicios
        $this->accessControl = new AccessControlService($this->context);
        $repository = new AssignmentRepository();
        $this->assignmentService = new AssignmentService($repository);
    }

    /**
     * Render main page
     */
    public function renderView()
    {
        // Verificar permisos
        if (!$this->accessControl->canManageAssignments()) {
            $this->errors[] = $this->l('You do not have permission to manage assignments.');
            return parent::renderView();
        }

        // Obtener asignaciones
        $assignments = $this->assignmentService->getAssignmentsGroupedByEmployee();
        $statistics = $this->assignmentService->getStatistics();

        // Asignar a Smarty
        $this->context->smarty->assign([
            'assignments' => $assignments,
            'statistics' => $statistics,
            'module_dir' => $this->module->getPathUri(),
            'controller_url' => $this->context->link->getAdminLink('AdminPersonalSalesmen', true),
        ]);

        // Usar template del módulo
        return $this->module->display(_PS_MODULE_DIR_ . 'personalsalesmen/personalsalesmen.php', 'views/templates/admin/assignments_legacy.tpl');
    }

    /**
     * Process actions
     */
    public function postProcess()
    {
        // Crear nueva asignación
        if (Tools::isSubmit('submitAddAssignment')) {
            $idEmployee = (int)Tools::getValue('id_employee');
            $idCustomer = Tools::getValue('id_customer') ? (int)Tools::getValue('id_customer') : null;
            $idGroup = Tools::getValue('id_group') ? (int)Tools::getValue('id_group') : null;

            $result = $this->assignmentService->createAssignment($idEmployee, $idCustomer, $idGroup);

            if ($result['success']) {
                $this->confirmations[] = $this->l('Assignment created successfully.');
            } else {
                $this->errors[] = $result['error'];
            }
        }

        // Eliminar asignación
        if (Tools::isSubmit('deleteAssignment')) {
            $id = (int)Tools::getValue('id_assignment');
            $result = $this->assignmentService->deleteAssignment($id);

            if ($result['success']) {
                $this->confirmations[] = $this->l('Assignment deleted successfully.');
            } else {
                $this->errors[] = $result['error'];
            }
        }

        parent::postProcess();
    }

    /**
     * Set default template
     */
    public function initContent()
    {
        $this->content = $this->renderSimpleInterface();
        parent::initContent();
    }

    /**
     * Render simple interface (fallback)
     */
    private function renderSimpleInterface()
    {
        if (!$this->accessControl->canManageAssignments()) {
            return $this->displayError($this->l('You do not have permission to manage assignments. Only SuperAdmin can access this page.'));
        }

        $assignments = $this->assignmentService->getAllAssignmentsWithDetails();
        $statistics = $this->assignmentService->getStatistics();

        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-group"></i> ' . $this->l('Personal Salesmen - Manage Assignments') . '</div>';
        $html .= '<div class="panel-body">';

        // Estadísticas
        $html .= '<div class="row">';
        $html .= '<div class="col-md-3"><div class="alert alert-info text-center">';
        $html .= '<h3>' . (isset($statistics['total_assignments']) ? $statistics['total_assignments'] : 0) . '</h3>';
        $html .= '<p>' . $this->l('Total Assignments') . '</p>';
        $html .= '</div></div>';
        $html .= '<div class="col-md-3"><div class="alert alert-success text-center">';
        $html .= '<h3>' . (isset($statistics['total_employees']) ? $statistics['total_employees'] : 0) . '</h3>';
        $html .= '<p>' . $this->l('Active Employees') . '</p>';
        $html .= '</div></div>';
        $html .= '<div class="col-md-3"><div class="alert alert-warning text-center">';
        $html .= '<h3>' . (isset($statistics['customer_assignments']) ? $statistics['customer_assignments'] : 0) . '</h3>';
        $html .= '<p>' . $this->l('Customer Assignments') . '</p>';
        $html .= '</div></div>';
        $html .= '<div class="col-md-3"><div class="alert alert-primary text-center">';
        $html .= '<h3>' . (isset($statistics['group_assignments']) ? $statistics['group_assignments'] : 0) . '</h3>';
        $html .= '<p>' . $this->l('Group Assignments') . '</p>';
        $html .= '</div></div>';
        $html .= '</div>';

        // Botón para crear
        $html .= '<div class="alert alert-info">';
        $html .= '<p><strong>' . $this->l('Note:') . '</strong> ' . $this->l('To create assignments, use the form below.') . '</p>';
        $html .= '</div>';

        // Lista de asignaciones
        if (empty($assignments)) {
            $html .= '<div class="alert alert-warning">' . $this->l('No assignments found. Create your first assignment below.') . '</div>';
        } else {
            $html .= '<h4>' . $this->l('Current Assignments') . '</h4>';
            $html .= '<table class="table table-bordered">';
            $html .= '<thead><tr>';
            $html .= '<th>' . $this->l('Employee') . '</th>';
            $html .= '<th>' . $this->l('Type') . '</th>';
            $html .= '<th>' . $this->l('Target') . '</th>';
            $html .= '<th>' . $this->l('Date') . '</th>';
            $html .= '<th>' . $this->l('Actions') . '</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($assignments as $assignment) {
                $html .= '<tr>';
                $html .= '<td>' . $assignment['employee']['name'] . '</td>';
                $html .= '<td><span class="badge badge-' . ($assignment['type'] == 'customer' ? 'success' : 'info') . '">' . ucfirst($assignment['type']) . '</span></td>';
                $html .= '<td>' . $assignment['target']['name'] . '</td>';
                $html .= '<td>' . date('Y-m-d H:i', strtotime($assignment['date_add'])) . '</td>';
                $html .= '<td><a href="' . $this->context->link->getAdminLink('AdminPersonalSalesmen') . '&deleteAssignment=1&id_assignment=' . $assignment['id'] . '" class="btn btn-danger btn-sm">' . $this->l('Delete') . '</a></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '</div></div>';

        // Información adicional
        $html .= '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-info"></i> ' . $this->l('How to Create Assignments') . '</div>';
        $html .= '<div class="panel-body">';
        $html .= '<p>' . $this->l('This interface is simplified. For a full-featured interface with forms, you can use the modern Symfony controller routes defined in config/routes.yml') . '</p>';
        $html .= '<p>' . $this->l('To create assignments programmatically or via API, use the AssignmentService class.') . '</p>';
        $html .= '</div></div>';

        return $html;
    }
}

<?php
/**
 * Admin Controller for Personal Salesmen
 * FULLY FUNCTIONAL - Create, view and delete assignments
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
     * Process form submissions
     */
    public function postProcess()
    {
        // Verificar permisos
        if (!$this->accessControl->canManageAssignments()) {
            $this->errors[] = $this->l('You do not have permission to manage assignments. Only SuperAdmin can access this page.');
            return;
        }

        // Crear nueva asignación
        if (Tools::isSubmit('submitAddAssignment')) {
            $idEmployee = (int)Tools::getValue('id_employee');
            $assignmentType = Tools::getValue('assignment_type');
            $idCustomer = null;
            $idGroup = null;

            if ($assignmentType === 'customer') {
                $idCustomer = (int)Tools::getValue('id_customer');
            } else {
                $idGroup = (int)Tools::getValue('id_group');
            }

            if ($idEmployee <= 0) {
                $this->errors[] = $this->l('Please select an employee.');
            } elseif ($assignmentType === 'customer' && $idCustomer <= 0) {
                $this->errors[] = $this->l('Please select a customer.');
            } elseif ($assignmentType === 'group' && $idGroup <= 0) {
                $this->errors[] = $this->l('Please select a customer group.');
            } else {
                $result = $this->assignmentService->createAssignment($idEmployee, $idCustomer, $idGroup);

                if ($result['success']) {
                    $this->confirmations[] = $this->l('Assignment created successfully!');
                } else {
                    $this->errors[] = $this->l('Error: ') . $result['error'];
                }
            }
        }

        // Eliminar asignación
        if (Tools::isSubmit('deleteAssignment') && Tools::getValue('id_assignment')) {
            $id = (int)Tools::getValue('id_assignment');
            $result = $this->assignmentService->deleteAssignment($id);

            if ($result['success']) {
                $this->confirmations[] = $this->l('Assignment deleted successfully!');
            } else {
                $this->errors[] = $this->l('Error: ') . $result['error'];
            }
        }

        parent::postProcess();
    }

    /**
     * Render the main content
     */
    public function initContent()
    {
        if (!$this->accessControl->canManageAssignments()) {
            $this->content = $this->displayError($this->l('You do not have permission to manage assignments. Only SuperAdmin can access this page.'));
            parent::initContent();
            return;
        }

        $this->content = $this->renderStatistics();
        $this->content .= $this->renderCreateForm();
        $this->content .= $this->renderAssignmentsList();

        parent::initContent();
    }

    /**
     * Render statistics panel
     */
    private function renderStatistics()
    {
        $statistics = $this->assignmentService->getStatistics();

        $html = '<div class="row" style="margin-bottom: 20px;">';
        $html .= '<div class="col-lg-3">';
        $html .= '<div class="panel" style="text-align: center; background: #e3f2fd;">';
        $html .= '<div class="panel-body">';
        $html .= '<h2 style="margin: 0; color: #1976d2;">' . (isset($statistics['total_assignments']) ? $statistics['total_assignments'] : 0) . '</h2>';
        $html .= '<p style="margin: 5px 0 0 0;"><strong>' . $this->l('Total Assignments') . '</strong></p>';
        $html .= '</div></div></div>';

        $html .= '<div class="col-lg-3">';
        $html .= '<div class="panel" style="text-align: center; background: #e8f5e9;">';
        $html .= '<div class="panel-body">';
        $html .= '<h2 style="margin: 0; color: #388e3c;">' . (isset($statistics['total_employees']) ? $statistics['total_employees'] : 0) . '</h2>';
        $html .= '<p style="margin: 5px 0 0 0;"><strong>' . $this->l('Active Employees') . '</strong></p>';
        $html .= '</div></div></div>';

        $html .= '<div class="col-lg-3">';
        $html .= '<div class="panel" style="text-align: center; background: #fff3e0;">';
        $html .= '<div class="panel-body">';
        $html .= '<h2 style="margin: 0; color: #f57c00;">' . (isset($statistics['customer_assignments']) ? $statistics['customer_assignments'] : 0) . '</h2>';
        $html .= '<p style="margin: 5px 0 0 0;"><strong>' . $this->l('Customer Assignments') . '</strong></p>';
        $html .= '</div></div></div>';

        $html .= '<div class="col-lg-3">';
        $html .= '<div class="panel" style="text-align: center; background: #f3e5f5;">';
        $html .= '<div class="panel-body">';
        $html .= '<h2 style="margin: 0; color: #7b1fa2;">' . (isset($statistics['group_assignments']) ? $statistics['group_assignments'] : 0) . '</h2>';
        $html .= '<p style="margin: 5px 0 0 0;"><strong>' . $this->l('Group Assignments') . '</strong></p>';
        $html .= '</div></div></div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render create assignment form
     */
    private function renderCreateForm()
    {
        // Obtener empleados con email
        $sql = new DbQuery();
        $sql->select('id_employee, firstname, lastname, email, active');
        $sql->from('employee');
        $sql->where('active = 1');
        $sql->orderBy('firstname ASC, lastname ASC');
        $employees = Db::getInstance()->executeS($sql);

        $employeeOptions = '<option value="">-- ' . $this->l('Select Employee') . ' --</option>';
        foreach ($employees as $employee) {
            $employeeOptions .= '<option value="' . (int)$employee['id_employee'] . '">'
                . htmlspecialchars($employee['firstname'] . ' ' . $employee['lastname'])
                . ' (' . htmlspecialchars($employee['email']) . ')</option>';
        }

        // Obtener clientes (con buscador ya no importa cargar más)
        $sql2 = new DbQuery();
        $sql2->select('id_customer, firstname, lastname, email, active');
        $sql2->from('customer');
        $sql2->where('active = 1 AND deleted = 0');
        $sql2->orderBy('firstname ASC, lastname ASC');
        $sql2->limit(2000); // Limite aumentado ya que tenemos buscador
        $customers = Db::getInstance()->executeS($sql2);

        $customerOptions = '<option value="">-- ' . $this->l('Select Customer') . ' --</option>';
        foreach ($customers as $customer) {
            $customerOptions .= '<option value="' . (int)$customer['id_customer'] . '">'
                . htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname'])
                . ' (' . htmlspecialchars($customer['email']) . ')</option>';
        }

        // Obtener grupos
        $groups = Group::getGroups($this->context->language->id);
        $groupOptions = '<option value="">-- ' . $this->l('Select Group') . ' --</option>';
        foreach ($groups as $group) {
            $groupOptions .= '<option value="' . (int)$group['id_group'] . '">'
                . htmlspecialchars($group['name']) . '</option>';
        }

        $html = '<div class="panel">';
        $html .= '<div class="panel-heading">';
        $html .= '<i class="icon-plus-sign"></i> ' . $this->l('Create New Assignment');
        $html .= '</div>';
        $html .= '<div class="panel-body">';

        $html .= '<form method="post" action="' . self::$currentIndex . '&token=' . $this->token . '" class="form-horizontal">';

        // Employee selector
        $html .= '<div class="form-group">';
        $html .= '<label class="control-label col-lg-3 required">' . $this->l('Employee') . '</label>';
        $html .= '<div class="col-lg-9">';
        $html .= '<select name="id_employee" id="id_employee" class="form-control" required>';
        $html .= $employeeOptions;
        $html .= '</select>';
        $html .= '<p class="help-block">' . $this->l('Select the employee who will manage this assignment.') . '</p>';
        $html .= '</div></div>';

        // Assignment type
        $html .= '<div class="form-group">';
        $html .= '<label class="control-label col-lg-3 required">' . $this->l('Assignment Type') . '</label>';
        $html .= '<div class="col-lg-9">';
        $html .= '<div class="radio">';
        $html .= '<label><input type="radio" name="assignment_type" value="customer" id="type_customer" checked onclick="toggleAssignmentType()"> ' . $this->l('Specific Customer') . '</label>';
        $html .= '</div>';
        $html .= '<div class="radio">';
        $html .= '<label><input type="radio" name="assignment_type" value="group" id="type_group" onclick="toggleAssignmentType()"> ' . $this->l('Customer Group') . '</label>';
        $html .= '</div>';
        $html .= '</div></div>';

        // Customer selector with search
        $html .= '<div class="form-group" id="customer_selector">';
        $html .= '<label class="control-label col-lg-3">' . $this->l('Customer') . '</label>';
        $html .= '<div class="col-lg-9">';
        $html .= '<input type="text" id="customer_search" class="form-control" placeholder="' . $this->l('Type to search customer name or email...') . '" autocomplete="off">';
        $html .= '<select name="id_customer" id="id_customer" class="form-control" style="margin-top: 10px;" size="8">';
        $html .= $customerOptions;
        $html .= '</select>';
        $html .= '<p class="help-block">' . $this->l('Search and select a customer from the list above.') . '</p>';
        $html .= '</div></div>';

        // Group selector
        $html .= '<div class="form-group" id="group_selector" style="display:none;">';
        $html .= '<label class="control-label col-lg-3">' . $this->l('Customer Group') . '</label>';
        $html .= '<div class="col-lg-9">';
        $html .= '<select name="id_group" id="id_group" class="form-control">';
        $html .= $groupOptions;
        $html .= '</select>';
        $html .= '<p class="help-block">' . $this->l('Select a customer group. All customers in this group will be assigned.') . '</p>';
        $html .= '</div></div>';

        // Submit button
        $html .= '<div class="panel-footer">';
        $html .= '<button type="submit" name="submitAddAssignment" class="btn btn-primary btn-lg">';
        $html .= '<i class="icon-save"></i> ' . $this->l('Create Assignment');
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</form>';
        $html .= '</div></div>';

        // JavaScript para toggle y búsqueda
        $html .= '<script>
        function toggleAssignmentType() {
            var isCustomer = document.getElementById("type_customer").checked;
            document.getElementById("customer_selector").style.display = isCustomer ? "block" : "none";
            document.getElementById("group_selector").style.display = isCustomer ? "none" : "block";

            if (isCustomer) {
                document.getElementById("id_group").value = "";
            } else {
                document.getElementById("id_customer").value = "";
            }
        }

        // Filtrado de clientes por búsqueda
        document.addEventListener("DOMContentLoaded", function() {
            var searchInput = document.getElementById("customer_search");
            var customerSelect = document.getElementById("id_customer");

            if (searchInput && customerSelect) {
                searchInput.addEventListener("keyup", function() {
                    var filter = this.value.toLowerCase();
                    var options = customerSelect.getElementsByTagName("option");

                    for (var i = 0; i < options.length; i++) {
                        var text = options[i].text.toLowerCase();
                        if (text.indexOf(filter) > -1 || filter === "") {
                            options[i].style.display = "";
                        } else {
                            options[i].style.display = "none";
                        }
                    }
                });
            }
        });
        </script>';

        return $html;
    }

    /**
     * Render assignments list
     */
    private function renderAssignmentsList()
    {
        $assignments = $this->assignmentService->getAllAssignmentsWithDetails();

        $html = '<div class="panel">';
        $html .= '<div class="panel-heading">';
        $html .= '<i class="icon-list"></i> ' . $this->l('Current Assignments');
        $html .= '</div>';

        if (empty($assignments)) {
            $html .= '<div class="panel-body">';
            $html .= '<div class="alert alert-warning">';
            $html .= '<strong>' . $this->l('No assignments yet.') . '</strong> ';
            $html .= $this->l('Use the form above to create your first assignment.');
            $html .= '</div>';
            $html .= '</div>';
        } else {
            $html .= '<table class="table">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>' . $this->l('ID') . '</th>';
            $html .= '<th>' . $this->l('Employee') . '</th>';
            $html .= '<th>' . $this->l('Type') . '</th>';
            $html .= '<th>' . $this->l('Assigned To') . '</th>';
            $html .= '<th>' . $this->l('Date Created') . '</th>';
            $html .= '<th class="text-right">' . $this->l('Actions') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ($assignments as $assignment) {
                $html .= '<tr>';
                $html .= '<td>' . (int)$assignment['id'] . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($assignment['employee']['name']) . '</strong><br>';
                $html .= '<small class="text-muted">' . htmlspecialchars($assignment['employee']['email']) . '</small></td>';

                if ($assignment['type'] === 'customer') {
                    $html .= '<td><span class="badge badge-success">' . $this->l('Customer') . '</span></td>';
                } else {
                    $html .= '<td><span class="badge badge-info">' . $this->l('Group') . '</span></td>';
                }

                $html .= '<td><strong>' . htmlspecialchars($assignment['target']['name']) . '</strong>';
                if (isset($assignment['target']['email'])) {
                    $html .= '<br><small class="text-muted">' . htmlspecialchars($assignment['target']['email']) . '</small>';
                }
                $html .= '</td>';

                $html .= '<td>' . date('Y-m-d H:i', strtotime($assignment['date_add'])) . '</td>';

                $html .= '<td class="text-right">';
                $html .= '<a href="' . self::$currentIndex . '&deleteAssignment=1&id_assignment=' . (int)$assignment['id'] . '&token=' . $this->token . '" ';
                $html .= 'class="btn btn-danger btn-sm" onclick="return confirm(\'' . $this->l('Are you sure you want to delete this assignment?') . '\');">';
                $html .= '<i class="icon-trash"></i> ' . $this->l('Delete');
                $html .= '</a>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
        }

        $html .= '</div>';

        return $html;
    }
}

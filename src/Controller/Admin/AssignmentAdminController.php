<?php
/**
 * Assignment Admin Controller
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use PrestaShop\Module\PersonalSalesmen\Service\AssignmentService;
use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;

class AssignmentAdminController extends FrameworkBundleAdminController
{
    private AssignmentService $assignmentService;
    private AccessControlService $accessControl;

    public function __construct(
        AssignmentService $assignmentService,
        AccessControlService $accessControl
    ) {
        $this->assignmentService = $assignmentService;
        $this->accessControl = $accessControl;
    }

    /**
     * Lista de asignaciones
     */
    public function indexAction(Request $request): Response
    {
        // Verificar permisos
        if (!$this->accessControl->canManageAssignments()) {
            $this->addFlash('error', $this->trans('You do not have permission to manage assignments.', 'Modules.Personalsalesmen.Admin'));
            return $this->redirectToRoute('admin_dashboard');
        }

        $assignments = $this->assignmentService->getAssignmentsGroupedByEmployee();
        $statistics = $this->assignmentService->getStatistics();

        return $this->render('@Modules/personalsalesmen/views/templates/admin/assignments/index.html.twig', [
            'assignments' => $assignments,
            'statistics' => $statistics,
            'layoutTitle' => $this->trans('Personal Salesmen - Assignments', 'Modules.Personalsalesmen.Admin'),
        ]);
    }

    /**
     * Crear nueva asignación
     */
    public function createAction(Request $request): JsonResponse
    {
        if (!$this->accessControl->canManageAssignments()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Permission denied'
            ], 403);
        }

        $idEmployee = (int)$request->request->get('id_employee');
        $idCustomer = $request->request->get('id_customer') ? (int)$request->request->get('id_customer') : null;
        $idGroup = $request->request->get('id_group') ? (int)$request->request->get('id_group') : null;

        $result = $this->assignmentService->createAssignment($idEmployee, $idCustomer, $idGroup);

        return new JsonResponse($result, $result['success'] ? 200 : 400);
    }

    /**
     * Eliminar asignación
     */
    public function deleteAction(Request $request, int $id): JsonResponse
    {
        if (!$this->accessControl->canManageAssignments()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Permission denied'
            ], 403);
        }

        $result = $this->assignmentService->deleteAssignment($id);

        return new JsonResponse($result, $result['success'] ? 200 : 400);
    }

    /**
     * Activar/Desactivar asignación
     */
    public function toggleAction(Request $request, int $id): JsonResponse
    {
        if (!$this->accessControl->canManageAssignments()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Permission denied'
            ], 403);
        }

        $active = $request->request->get('active') === '1';
        $result = $this->assignmentService->updateAssignment($id, ['active' => $active]);

        return new JsonResponse($result, $result['success'] ? 200 : 400);
    }

    /**
     * Obtener clientes disponibles (AJAX)
     */
    public function searchCustomersAction(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $customers = \Customer::searchByName($query, 20);
        $results = [];

        foreach ($customers as $customer) {
            $results[] = [
                'id' => (int)$customer['id_customer'],
                'text' => sprintf(
                    '%s %s (%s)',
                    $customer['firstname'],
                    $customer['lastname'],
                    $customer['email']
                )
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Obtener grupos disponibles (AJAX)
     */
    public function searchGroupsAction(Request $request): JsonResponse
    {
        $groups = \Group::getGroups($this->getContext()->language->id);
        $results = [];

        foreach ($groups as $group) {
            $results[] = [
                'id' => (int)$group['id_group'],
                'text' => $group['name']
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Obtener empleados disponibles (AJAX)
     */
    public function searchEmployeesAction(Request $request): JsonResponse
    {
        $employees = \Employee::getEmployees(true);
        $results = [];

        foreach ($employees as $employee) {
            $results[] = [
                'id' => (int)$employee['id_employee'],
                'text' => sprintf(
                    '%s %s (%s)',
                    $employee['firstname'],
                    $employee['lastname'],
                    $employee['email']
                )
            ];
        }

        return new JsonResponse(['results' => $results]);
    }
}
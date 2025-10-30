<?php
/**
 * Assignment Service
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Service;

use PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository;
use Employee;
use Customer;
use Group;
use Validate;

class AssignmentService
{
    private AssignmentRepository $repository;

    public function __construct(AssignmentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Crear nueva asignación
     */
    public function createAssignment(
        int $idEmployee,
        ?int $idCustomer = null,
        ?int $idGroup = null
    ): array {
        // Validaciones
        if (!$this->validateAssignmentData($idEmployee, $idCustomer, $idGroup)) {
            return [
                'success' => false,
                'error' => 'Invalid assignment data'
            ];
        }

        // Verificar si ya existe
        if ($this->repository->exists($idEmployee, $idCustomer, $idGroup)) {
            return [
                'success' => false,
                'error' => 'Assignment already exists'
            ];
        }

        // Crear asignación
        $data = [
            'id_employee' => $idEmployee,
            'id_customer' => $idCustomer,
            'id_group' => $idGroup,
            'active' => 1
        ];

        $success = $this->repository->create($data);

        return [
            'success' => $success,
            'error' => $success ? null : 'Failed to create assignment'
        ];
    }

    /**
     * Actualizar asignación
     */
    public function updateAssignment(int $id, array $data): array
    {
        $assignment = $this->repository->findById($id);
        
        if (!$assignment) {
            return [
                'success' => false,
                'error' => 'Assignment not found'
            ];
        }

        $success = $this->repository->update($id, $data);

        return [
            'success' => $success,
            'error' => $success ? null : 'Failed to update assignment'
        ];
    }

    /**
     * Eliminar asignación (soft delete)
     */
    public function deleteAssignment(int $id): array
    {
        $assignment = $this->repository->findById($id);
        
        if (!$assignment) {
            return [
                'success' => false,
                'error' => 'Assignment not found'
            ];
        }

        $success = $this->repository->delete($id);

        return [
            'success' => $success,
            'error' => $success ? null : 'Failed to delete assignment'
        ];
    }

    /**
     * Obtener todas las asignaciones con información expandida
     */
    public function getAllAssignmentsWithDetails(): array
    {
        $assignments = $this->repository->findAll();
        $result = [];

        foreach ($assignments as $assignment) {
            $employee = new Employee((int)$assignment['id_employee']);
            
            $assignmentData = [
                'id' => (int)$assignment['id_assignment'],
                'employee' => [
                    'id' => (int)$employee->id,
                    'name' => $employee->firstname . ' ' . $employee->lastname,
                    'email' => $employee->email
                ],
                'type' => $assignment['id_customer'] ? 'customer' : 'group',
                'date_add' => $assignment['date_add'],
                'active' => (bool)$assignment['active']
            ];

            if ($assignment['id_customer']) {
                $customer = new Customer((int)$assignment['id_customer']);
                $assignmentData['target'] = [
                    'id' => (int)$customer->id,
                    'name' => $customer->firstname . ' ' . $customer->lastname,
                    'email' => $customer->email
                ];
            } elseif ($assignment['id_group']) {
                $group = new Group((int)$assignment['id_group']);
                $assignmentData['target'] = [
                    'id' => (int)$group->id,
                    'name' => $group->name[\Context::getContext()->language->id] ?? 'Unknown'
                ];
            }

            $result[] = $assignmentData;
        }

        return $result;
    }

    /**
     * Obtener asignaciones agrupadas por empleado
     */
    public function getAssignmentsGroupedByEmployee(): array
    {
        $assignments = $this->repository->findAll();
        $grouped = [];

        foreach ($assignments as $assignment) {
            $empId = (int)$assignment['id_employee'];
            
            if (!isset($grouped[$empId])) {
                $employee = new Employee($empId);
                $grouped[$empId] = [
                    'employee' => [
                        'id' => $empId,
                        'name' => $employee->firstname . ' ' . $employee->lastname,
                        'email' => $employee->email
                    ],
                    'assignments' => []
                ];
            }

            $assignmentData = [
                'id' => (int)$assignment['id_assignment'],
                'type' => $assignment['id_customer'] ? 'customer' : 'group',
                'date_add' => $assignment['date_add']
            ];

            if ($assignment['id_customer']) {
                $customer = new Customer((int)$assignment['id_customer']);
                $assignmentData['target'] = [
                    'id' => (int)$customer->id,
                    'name' => $customer->firstname . ' ' . $customer->lastname,
                    'email' => $customer->email
                ];
            } elseif ($assignment['id_group']) {
                $group = new Group((int)$assignment['id_group']);
                $assignmentData['target'] = [
                    'id' => (int)$group->id,
                    'name' => $group->name[\Context::getContext()->language->id] ?? 'Unknown'
                ];
            }

            $grouped[$empId]['assignments'][] = $assignmentData;
        }

        return array_values($grouped);
    }

    /**
     * Obtener empleados asignados a un cliente
     */
    public function getAssignedEmployees(int $idCustomer): array
    {
        $employeesData = $this->repository->findEmployeesByCustomer($idCustomer);
        $employees = [];

        foreach ($employeesData as $data) {
            $employees[] = new Employee((int)$data['id_employee']);
        }

        return $employees;
    }

    /**
     * Obtener estadísticas
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Validar datos de asignación
     */
    private function validateAssignmentData(
        int $idEmployee,
        ?int $idCustomer,
        ?int $idGroup
    ): bool {
        // Debe haber cliente O grupo, no ambos, no ninguno
        if (($idCustomer === null && $idGroup === null) ||
            ($idCustomer !== null && $idGroup !== null)) {
            return false;
        }

        // Validar empleado
        if (!Validate::isLoadedObject(new Employee($idEmployee))) {
            return false;
        }

        // Validar cliente si está presente
        if ($idCustomer !== null && !Validate::isLoadedObject(new Customer($idCustomer))) {
            return false;
        }

        // Validar grupo si está presente
        if ($idGroup !== null && !Validate::isLoadedObject(new Group($idGroup))) {
            return false;
        }

        return true;
    }

    /**
     * Reasignar todos los clientes de un empleado a otro
     */
    public function reassignAll(int $fromEmployeeId, int $toEmployeeId): array
    {
        $assignments = $this->repository->findByEmployee($fromEmployeeId);
        $count = 0;
        $errors = [];

        foreach ($assignments as $assignment) {
            $result = $this->createAssignment(
                $toEmployeeId,
                $assignment['id_customer'],
                $assignment['id_group']
            );

            if ($result['success']) {
                $this->repository->delete((int)$assignment['id_assignment']);
                $count++;
            } else {
                $errors[] = $result['error'];
            }
        }

        return [
            'success' => $count > 0,
            'reassigned' => $count,
            'errors' => $errors
        ];
    }
}
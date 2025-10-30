<?php
/**
 * Access Control Service
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Service;

use Configuration;
use Context;
use PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository;

class AccessControlService
{
    private int $employeeId;
    private int $profileId;
    private bool $restrictionEnabled;
    private AssignmentRepository $repository;
    private ?array $allowedCustomerIds = null;

    public function __construct(?Context $context = null)
    {
        $context = $context ?? Context::getContext();
        
        $this->employeeId = (int)($context->employee->id ?? 0);
        $this->profileId = (int)($context->employee->id_profile ?? 0);
        $this->restrictionEnabled = (bool)Configuration::get('PSM_RESTRICTION_ENABLED');
        $this->repository = new AssignmentRepository();
    }

    /**
     * Verificar si el empleado puede ver todo
     */
    public function canSeeEverything(): bool
    {
        // SuperAdmin (profile_id = 1) o restricci�n desactivada
        $result = $this->profileId === 1 || !$this->restrictionEnabled;

        error_log("PSM DEBUG AccessControl: canSeeEverything() - Employee ID={$this->employeeId}, Profile ID={$this->profileId}, Restriction Enabled={$this->restrictionEnabled}, Result={$result}");

        return $result;
    }

    /**
     * Verificar si el empleado tiene restricciones activas
     */
    public function hasRestrictions(): bool
    {
        return !$this->canSeeEverything();
    }

    /**
     * Obtener IDs de clientes que el empleado puede ver
     * Usa cach� para evitar queries repetitivas
     */
    public function getAllowedCustomerIds(): array
    {
        if ($this->canSeeEverything()) {
            error_log("PSM DEBUG AccessControl: getAllowedCustomerIds() - Can see everything, returning empty array");
            return []; // Array vac�o significa sin restricci�n
        }

        // Usar cach� para la misma request
        if ($this->allowedCustomerIds !== null) {
            error_log("PSM DEBUG AccessControl: getAllowedCustomerIds() - Using cache, count=" . count($this->allowedCustomerIds));
            return $this->allowedCustomerIds;
        }

        $this->allowedCustomerIds = $this->repository->getCustomerIdsByEmployee($this->employeeId);

        error_log("PSM DEBUG AccessControl: getAllowedCustomerIds() - Fresh query for employee {$this->employeeId}, found " . count($this->allowedCustomerIds) . " customers");

        return $this->allowedCustomerIds;
    }

    /**
     * Verificar si el empleado puede acceder a un cliente espec�fico
     */
    public function canAccessCustomer(int $customerId): bool
    {
        if ($this->canSeeEverything()) {
            return true;
        }

        $allowedIds = $this->getAllowedCustomerIds();
        return in_array($customerId, $allowedIds, true);
    }

    /**
     * Obtener IDs de grupos asignados al empleado
     */
    public function getAllowedGroupIds(): array
    {
        if ($this->canSeeEverything()) {
            return []; // Array vacío = sin restricción
        }

        return $this->repository->getGroupIdsByEmployee($this->employeeId);
    }

    /**
     * Verificar si el empleado tiene asignaciones solo por grupo
     * (no tiene asignaciones individuales de clientes)
     */
    public function hasOnlyGroupAssignments(): bool
    {
        if ($this->canSeeEverything()) {
            return false;
        }

        $assignments = $this->repository->findByEmployee($this->employeeId);

        foreach ($assignments as $assignment) {
            if ($assignment['id_customer'] !== null) {
                return false; // Tiene al menos una asignación individual
            }
        }

        return !empty($assignments); // Solo tiene asignaciones de grupo
    }

    /**
     * Verificar si el empleado puede gestionar asignaciones
     */
    public function canManageAssignments(): bool
    {
        // Solo SuperAdmin puede gestionar asignaciones
        return $this->profileId === 1;
    }

    /**
     * Obtener filtro SQL para queries
     */
    public function getSQLFilter(string $tableAlias = 'c', string $customerField = 'id_customer'): string
    {
        if ($this->canSeeEverything()) {
            return '1=1'; // Sin restricci�n
        }

        $allowedIds = $this->getAllowedCustomerIds();

        if (empty($allowedIds)) {
            return '1=0'; // Sin acceso a ning�n cliente
        }

        $ids = implode(',', array_map('intval', $allowedIds));
        return "{$tableAlias}.{$customerField} IN ({$ids})";
    }

    /**
     * Limpiar cach�
     */
    public function clearCache(): void
    {
        $this->allowedCustomerIds = null;
    }

    /**
     * Obtener informaci�n del empleado actual
     */
    public function getCurrentEmployeeInfo(): array
    {
        return [
            'id' => $this->employeeId,
            'profile_id' => $this->profileId,
            'is_superadmin' => $this->profileId === 1,
            'has_restrictions' => $this->hasRestrictions(),
            'can_manage_assignments' => $this->canManageAssignments(),
        ];
    }
}
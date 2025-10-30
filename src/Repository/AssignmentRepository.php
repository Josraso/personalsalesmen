<?php
/**
 * Assignment Repository
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Repository;

use Db;
use DbQuery;
use PrestaShopException;

class AssignmentRepository
{
    /**
     * Obtener todas las asignaciones activas
     */
    public function findAll(): array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('personalsalesmen_assignment');
        $sql->where('active = 1');
        $sql->orderBy('id_employee ASC, date_add DESC');

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Obtener asignación por ID
     */
    public function findById(int $id): ?array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('personalsalesmen_assignment');
        $sql->where('id_assignment = ' . (int)$id);

        $result = Db::getInstance()->getRow($sql);
        return $result ?: null;
    }

    /**
     * Obtener asignaciones de un empleado
     */
    public function findByEmployee(int $idEmployee): array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('personalsalesmen_assignment');
        $sql->where('id_employee = ' . (int)$idEmployee);
        $sql->where('active = 1');
        $sql->orderBy('date_add DESC');

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Obtener empleados asignados a un cliente
     */
    public function findEmployeesByCustomer(int $idCustomer): array
    {
        $sql = new DbQuery();
        $sql->select('DISTINCT e.*');
        $sql->from('employee', 'e');
        $sql->innerJoin('personalsalesmen_assignment', 'psa', 'psa.id_employee = e.id_employee');
        $sql->where('psa.id_customer = ' . (int)$idCustomer);
        $sql->where('psa.active = 1');
        $sql->where('e.active = 1');

        // También buscar por grupo
        $sql2 = new DbQuery();
        $sql2->select('DISTINCT e.*');
        $sql2->from('employee', 'e');
        $sql2->innerJoin('personalsalesmen_assignment', 'psa', 'psa.id_employee = e.id_employee');
        $sql2->innerJoin('customer_group', 'cg', 'cg.id_group = psa.id_group');
        $sql2->where('cg.id_customer = ' . (int)$idCustomer);
        $sql2->where('psa.active = 1');
        $sql2->where('e.active = 1');

        $employees1 = Db::getInstance()->executeS($sql) ?: [];
        $employees2 = Db::getInstance()->executeS($sql2) ?: [];

        // Combinar y eliminar duplicados por id_employee
        $combined = array_merge($employees1, $employees2);
        $unique = [];
        foreach ($combined as $employee) {
            $unique[(int)$employee['id_employee']] = $employee;
        }

        return array_values($unique);
    }

    /**
     * Obtener IDs de clientes que un empleado puede ver
     */
    public function getCustomerIdsByEmployee(int $idEmployee): array
    {
        $sql = new DbQuery();
        $sql->select('DISTINCT c.id_customer');
        $sql->from('customer', 'c');
        
        // Asignaciones directas
        $sql->leftJoin(
            'personalsalesmen_assignment',
            'psa_customer',
            'psa_customer.id_customer = c.id_customer AND psa_customer.active = 1'
        );
        
        // Asignaciones por grupo
        $sql->leftJoin('customer_group', 'cg', 'cg.id_customer = c.id_customer');
        $sql->leftJoin(
            'personalsalesmen_assignment',
            'psa_group',
            'psa_group.id_group = cg.id_group AND psa_group.active = 1'
        );
        
        $sql->where(
            '(psa_customer.id_employee = ' . (int)$idEmployee . ' OR ' .
            'psa_group.id_employee = ' . (int)$idEmployee . ')'
        );

        $results = Db::getInstance()->executeS($sql);
        return $results ? array_column($results, 'id_customer') : [];
    }

    /**
     * Crear nueva asignación
     */
    public function create(array $data): bool
    {
        $data['date_add'] = date('Y-m-d H:i:s');
        $data['date_upd'] = date('Y-m-d H:i:s');
        $data['active'] = isset($data['active']) ? (int)$data['active'] : 1;

        return Db::getInstance()->insert('personalsalesmen_assignment', $data);
    }

    /**
     * Actualizar asignación
     */
    public function update(int $id, array $data): bool
    {
        $data['date_upd'] = date('Y-m-d H:i:s');

        return Db::getInstance()->update(
            'personalsalesmen_assignment',
            $data,
            'id_assignment = ' . (int)$id
        );
    }

    /**
     * Eliminar asignación (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->update($id, ['active' => 0]);
    }

    /**
     * Eliminar permanentemente
     */
    public function deletePermanently(int $id): bool
    {
        return Db::getInstance()->delete(
            'personalsalesmen_assignment',
            'id_assignment = ' . (int)$id
        );
    }

    /**
     * Verificar si existe asignación
     */
    public function exists(int $idEmployee, ?int $idCustomer, ?int $idGroup): bool
    {
        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from('personalsalesmen_assignment');
        $sql->where('id_employee = ' . (int)$idEmployee);
        $sql->where('active = 1');

        if ($idCustomer !== null) {
            $sql->where('id_customer = ' . (int)$idCustomer);
            $sql->where('id_group IS NULL');
        } elseif ($idGroup !== null) {
            $sql->where('id_group = ' . (int)$idGroup);
            $sql->where('id_customer IS NULL');
        } else {
            return false;
        }

        return (int)Db::getInstance()->getValue($sql) > 0;
    }

    /**
     * Obtener estadísticas de asignaciones
     */
    public function getStatistics(): array
    {
        $sql = new DbQuery();
        $sql->select('
            COUNT(*) as total_assignments,
            COUNT(DISTINCT id_employee) as total_employees,
            COUNT(CASE WHEN id_customer IS NOT NULL THEN 1 END) as customer_assignments,
            COUNT(CASE WHEN id_group IS NOT NULL THEN 1 END) as group_assignments
        ');
        $sql->from('personalsalesmen_assignment');
        $sql->where('active = 1');

        return Db::getInstance()->getRow($sql) ?: [];
    }
}
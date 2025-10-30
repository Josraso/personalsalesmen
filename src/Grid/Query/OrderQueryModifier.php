<?php
/**
 * Order Query Modifier
 * Modifies order grid queries to apply access restrictions
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Grid\Query;

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;

class OrderQueryModifier
{
    private AccessControlService $accessControl;
    private HookDispatcherInterface $hookDispatcher;

    public function __construct(
        AccessControlService $accessControl,
        HookDispatcherInterface $hookDispatcher
    ) {
        $this->accessControl = $accessControl;
        $this->hookDispatcher = $hookDispatcher;
    }

    /**
     * Modify order search query to apply access restrictions
     *
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function modify(QueryBuilder $queryBuilder): void
    {
        // Si el empleado puede ver todo, no aplicar restricciones
        if ($this->accessControl->canSeeEverything()) {
            return;
        }

        $allowedCustomerIds = $this->accessControl->getAllowedCustomerIds();

        // Si no tiene clientes asignados, mostrar resultado vacío
        if (empty($allowedCustomerIds)) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        // Verificar si la tabla customer ya está en el JOIN
        $from = $queryBuilder->getQueryPart('from');
        $join = $queryBuilder->getQueryPart('join');

        $hasCustomerJoin = false;

        // Buscar si ya existe join con customer
        foreach ($from as $fromPart) {
            if (isset($fromPart['alias']) && $fromPart['alias'] === 'c') {
                $hasCustomerJoin = true;
                break;
            }
        }

        if (!$hasCustomerJoin && isset($join['o'])) {
            foreach ($join['o'] as $joinPart) {
                if (isset($joinPart['joinAlias']) && $joinPart['joinAlias'] === 'c') {
                    $hasCustomerJoin = true;
                    break;
                }
            }
        }

        // Si no existe el join con customer, agregarlo
        if (!$hasCustomerJoin) {
            $queryBuilder->leftJoin(
                'o',
                _DB_PREFIX_ . 'customer',
                'c',
                'c.id_customer = o.id_customer'
            );
        }

        // Aplicar filtro de IDs permitidos
        $queryBuilder->andWhere(
            $queryBuilder->expr()->in('c.id_customer', ':psm_allowed_order_customer_ids')
        );
        $queryBuilder->setParameter(
            'psm_allowed_order_customer_ids',
            $allowedCustomerIds,
            \Doctrine\DBAL\Connection::PARAM_INT_ARRAY
        );
    }

    /**
     * Modify order count query
     *
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function modifyCount(QueryBuilder $queryBuilder): void
    {
        $this->modify($queryBuilder);
    }

    /**
     * Check if a specific order is accessible by checking its customer
     *
     * @param int $orderId
     * @return bool
     */
    public function canAccessOrder(int $orderId): bool
    {
        if ($this->accessControl->canSeeEverything()) {
            return true;
        }

        // Obtener el customer_id del pedido
        $order = new \Order($orderId);
        if (!\Validate::isLoadedObject($order)) {
            return false;
        }

        return $this->accessControl->canAccessCustomer((int)$order->id_customer);
    }

    /**
     * Apply restriction to a custom query builder
     * Useful for custom queries outside the grid system
     *
     * @param QueryBuilder $queryBuilder
     * @param string $orderTableAlias
     * @param string $customerTableAlias
     * @return void
     */
    public function applyRestriction(
        QueryBuilder $queryBuilder,
        string $orderTableAlias = 'o',
        string $customerTableAlias = 'c'
    ): void {
        if ($this->accessControl->canSeeEverything()) {
            return;
        }

        $allowedCustomerIds = $this->accessControl->getAllowedCustomerIds();

        if (empty($allowedCustomerIds)) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        // Asegurar que existe el join con customer
        $queryBuilder->leftJoin(
            $orderTableAlias,
            _DB_PREFIX_ . 'customer',
            $customerTableAlias,
            sprintf('%s.id_customer = %s.id_customer', $customerTableAlias, $orderTableAlias)
        );

        $queryBuilder->andWhere(
            $queryBuilder->expr()->in(
                sprintf('%s.id_customer', $customerTableAlias),
                ':psm_allowed_order_customers'
            )
        );
        $queryBuilder->setParameter(
            'psm_allowed_order_customers',
            $allowedCustomerIds,
            \Doctrine\DBAL\Connection::PARAM_INT_ARRAY
        );
    }

    /**
     * Get SQL WHERE clause for legacy queries
     * For backward compatibility with old PrestaShop code
     *
     * @param string $customerTableAlias
     * @return string
     */
    public function getLegacySQLFilter(string $customerTableAlias = 'c'): string
    {
        return $this->accessControl->getSQLFilter($customerTableAlias, 'id_customer');
    }

    /**
     * Hook into PrestaShop's grid query builder
     * This is called by hookActionOrderGridQueryBuilderModifier
     *
     * @param array $params
     * @return void
     */
    public function hookModifier(array $params): void
    {
        if (!isset($params['search_query_builder'])) {
            return;
        }

        /** @var QueryBuilder $searchQueryBuilder */
        $searchQueryBuilder = $params['search_query_builder'];
        $this->modify($searchQueryBuilder);

        // También modificar el count query si está disponible
        if (isset($params['count_query_builder'])) {
            /** @var QueryBuilder $countQueryBuilder */
            $countQueryBuilder = $params['count_query_builder'];
            $this->modifyCount($countQueryBuilder);
        }
    }

    /**
     * Get order statistics for current employee
     *
     * @return array
     */
    public function getOrderStatistics(): array
    {
        if ($this->accessControl->canSeeEverything()) {
            return [
                'unrestricted' => true,
                'message' => 'SuperAdmin can see all orders'
            ];
        }

        $allowedCustomerIds = $this->accessControl->getAllowedCustomerIds();

        if (empty($allowedCustomerIds)) {
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'customers_count' => 0
            ];
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(DISTINCT o.id_order) as total_orders');
        $sql->select('SUM(o.total_paid_tax_incl) as total_revenue');
        $sql->select('COUNT(DISTINCT o.id_customer) as customers_count');
        $sql->from('orders', 'o');
        $sql->where('o.id_customer IN (' . implode(',', array_map('intval', $allowedCustomerIds)) . ')');

        $result = \Db::getInstance()->getRow($sql);

        return [
            'total_orders' => (int)($result['total_orders'] ?? 0),
            'total_revenue' => (float)($result['total_revenue'] ?? 0),
            'customers_count' => (int)($result['customers_count'] ?? 0)
        ];
    }
}

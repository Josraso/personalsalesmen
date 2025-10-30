<?php
/**
 * Customer Query Modifier
 * Modifies customer grid queries to apply access restrictions
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Grid\Query;

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;

class CustomerQueryModifier
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
     * Modify customer search query to apply access restrictions
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

        // Aplicar filtro de IDs permitidos
        $queryBuilder->andWhere(
            $queryBuilder->expr()->in('c.id_customer', ':allowed_customer_ids')
        );
        $queryBuilder->setParameter('allowed_customer_ids', $allowedCustomerIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
    }

    /**
     * Modify customer count query
     *
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function modifyCount(QueryBuilder $queryBuilder): void
    {
        $this->modify($queryBuilder);
    }

    /**
     * Get customer IDs that the current employee can access
     *
     * @return array
     */
    public function getAllowedCustomerIds(): array
    {
        return $this->accessControl->getAllowedCustomerIds();
    }

    /**
     * Check if a specific customer is accessible
     *
     * @param int $customerId
     * @return bool
     */
    public function canAccessCustomer(int $customerId): bool
    {
        return $this->accessControl->canAccessCustomer($customerId);
    }

    /**
     * Apply restriction to a custom query builder
     * Useful for custom queries outside the grid system
     *
     * @param QueryBuilder $queryBuilder
     * @param string $customerTableAlias
     * @return void
     */
    public function applyRestriction(QueryBuilder $queryBuilder, string $customerTableAlias = 'c'): void
    {
        if ($this->accessControl->canSeeEverything()) {
            return;
        }

        $allowedCustomerIds = $this->accessControl->getAllowedCustomerIds();

        if (empty($allowedCustomerIds)) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $queryBuilder->andWhere(
            $queryBuilder->expr()->in(
                sprintf('%s.id_customer', $customerTableAlias),
                ':psm_allowed_customer_ids'
            )
        );
        $queryBuilder->setParameter(
            'psm_allowed_customer_ids',
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
     * This is called by hookActionCustomerGridQueryBuilderModifier
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
}

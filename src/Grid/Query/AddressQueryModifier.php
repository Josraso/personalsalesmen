<?php
/**
 * Address Query Modifier
 * Modifies address grid queries to apply access restrictions
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Grid\Query;

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\Module\PersonalSalesmen\Service\AccessControlService;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;

class AddressQueryModifier
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
     * Modify address search query to apply access restrictions
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
        // La tabla de direcciones usa 'a' como alias por defecto
        $queryBuilder->andWhere(
            $queryBuilder->expr()->in('a.id_customer', ':psm_allowed_address_customer_ids')
        );
        $queryBuilder->setParameter(
            'psm_allowed_address_customer_ids',
            $allowedCustomerIds,
            \Doctrine\DBAL\Connection::PARAM_INT_ARRAY
        );
    }

    /**
     * Modify address count query
     *
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function modifyCount(QueryBuilder $queryBuilder): void
    {
        $this->modify($queryBuilder);
    }

    /**
     * Check if a specific address is accessible by checking its customer
     *
     * @param int $addressId
     * @return bool
     */
    public function canAccessAddress(int $addressId): bool
    {
        if ($this->accessControl->canSeeEverything()) {
            return true;
        }

        // Obtener el customer_id de la dirección
        $address = new \Address($addressId);
        if (!\Validate::isLoadedObject($address)) {
            return false;
        }

        return $this->accessControl->canAccessCustomer((int)$address->id_customer);
    }

    /**
     * Apply restriction to a custom query builder
     * Useful for custom queries outside the grid system
     *
     * @param QueryBuilder $queryBuilder
     * @param string $addressTableAlias
     * @return void
     */
    public function applyRestriction(QueryBuilder $queryBuilder, string $addressTableAlias = 'a'): void
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
                sprintf('%s.id_customer', $addressTableAlias),
                ':psm_allowed_address_customers'
            )
        );
        $queryBuilder->setParameter(
            'psm_allowed_address_customers',
            $allowedCustomerIds,
            \Doctrine\DBAL\Connection::PARAM_INT_ARRAY
        );
    }

    /**
     * Get SQL WHERE clause for legacy queries
     * For backward compatibility with old PrestaShop code
     *
     * @param string $addressTableAlias
     * @return string
     */
    public function getLegacySQLFilter(string $addressTableAlias = 'a'): string
    {
        return $this->accessControl->getSQLFilter($addressTableAlias, 'id_customer');
    }

    /**
     * Hook into PrestaShop's grid query builder
     * This is called by hookActionAddressGridQueryBuilderModifier
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
     * Get addresses for a specific customer (respecting access control)
     *
     * @param int $customerId
     * @return array
     */
    public function getCustomerAddresses(int $customerId): array
    {
        // Verificar si puede acceder al cliente
        if (!$this->accessControl->canAccessCustomer($customerId)) {
            return [];
        }

        $sql = new \DbQuery();
        $sql->select('a.*');
        $sql->from('address', 'a');
        $sql->where('a.id_customer = ' . (int)$customerId);
        $sql->where('a.deleted = 0');
        $sql->orderBy('a.date_add DESC');

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Get address statistics for current employee
     *
     * @return array
     */
    public function getAddressStatistics(): array
    {
        if ($this->accessControl->canSeeEverything()) {
            return [
                'unrestricted' => true,
                'message' => 'SuperAdmin can see all addresses'
            ];
        }

        $allowedCustomerIds = $this->accessControl->getAllowedCustomerIds();

        if (empty($allowedCustomerIds)) {
            return [
                'total_addresses' => 0,
                'customers_with_addresses' => 0
            ];
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(DISTINCT a.id_address) as total_addresses');
        $sql->select('COUNT(DISTINCT a.id_customer) as customers_with_addresses');
        $sql->from('address', 'a');
        $sql->where('a.id_customer IN (' . implode(',', array_map('intval', $allowedCustomerIds)) . ')');
        $sql->where('a.deleted = 0');

        $result = \Db::getInstance()->getRow($sql);

        return [
            'total_addresses' => (int)($result['total_addresses'] ?? 0),
            'customers_with_addresses' => (int)($result['customers_with_addresses'] ?? 0)
        ];
    }
}

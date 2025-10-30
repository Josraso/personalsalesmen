<?php
/**
 * Assignment Grid Definition Factory
 * Defines the structure of the assignments grid in PrestaShop 8/9
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Grid\Definition;

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BadgeColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DateTimeColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;

class AssignmentGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    const GRID_ID = 'personalsalesmen_assignment';

    /**
     * {@inheritdoc}
     */
    protected function getId(): string
    {
        return self::GRID_ID;
    }

    /**
     * {@inheritdoc}
     */
    protected function getName(): string
    {
        return $this->trans('Personal Salesmen Assignments', [], 'Modules.Personalsalesmen.Admin');
    }

    /**
     * {@inheritdoc}
     */
    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add(
                (new DataColumn('id_assignment'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_assignment',
                    ])
            )
            ->add(
                (new DataColumn('employee_name'))
                    ->setName($this->trans('Employee', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'employee_name',
                    ])
            )
            ->add(
                (new DataColumn('employee_email'))
                    ->setName($this->trans('Email', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'employee_email',
                    ])
            )
            ->add(
                (new BadgeColumn('assignment_type'))
                    ->setName($this->trans('Type', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'assignment_type',
                        'badge_type' => 'assignment_type',
                    ])
            )
            ->add(
                (new DataColumn('target_name'))
                    ->setName($this->trans('Assigned To', [], 'Modules.Personalsalesmen.Admin'))
                    ->setOptions([
                        'field' => 'target_name',
                    ])
            )
            ->add(
                (new DateTimeColumn('date_add'))
                    ->setName($this->trans('Created', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'date_add',
                    ])
            )
            ->add(
                (new BadgeColumn('active'))
                    ->setName($this->trans('Status', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'active',
                        'true_value' => 'Active',
                        'false_value' => 'Inactive',
                        'true_class' => 'badge-success',
                        'false_class' => 'badge-danger',
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => $this->getRowActions(),
                    ])
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilters(): FilterCollection
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_assignment', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->trans('ID', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('id_assignment')
            )
            ->add(
                (new Filter('employee_name', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->trans('Search employee', [], 'Modules.Personalsalesmen.Admin'),
                        ],
                    ])
                    ->setAssociatedColumn('employee_name')
            )
            ->add(
                (new Filter('assignment_type', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->trans('Customer', [], 'Modules.Personalsalesmen.Admin') => 'customer',
                            $this->trans('Group', [], 'Modules.Personalsalesmen.Admin') => 'group',
                        ],
                        'placeholder' => $this->trans('All types', [], 'Modules.Personalsalesmen.Admin'),
                    ])
                    ->setAssociatedColumn('assignment_type')
            )
            ->add(
                (new Filter('active', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->trans('Active', [], 'Admin.Global') => 1,
                            $this->trans('Inactive', [], 'Admin.Global') => 0,
                        ],
                        'placeholder' => $this->trans('All statuses', [], 'Admin.Global'),
                    ])
                    ->setAssociatedColumn('active')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'admin_common_reset_search_by_filter_id',
                        'reset_route_params' => [
                            'filterId' => self::GRID_ID,
                        ],
                        'redirect_route' => 'admin_personalsalesmen_index',
                    ])
                    ->setAssociatedColumn('actions')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGridActions(): GridActionCollection
    {
        return (new GridActionCollection())
            ->add(
                (new SimpleGridAction('common_refresh_list'))
                    ->setName($this->trans('Refresh list', [], 'Admin.Advparameters.Feature'))
                    ->setIcon('refresh')
            )
            ->add(
                (new SimpleGridAction('common_show_query'))
                    ->setName($this->trans('Show SQL query', [], 'Admin.Actions'))
                    ->setIcon('code')
            )
            ->add(
                (new SimpleGridAction('common_export_sql_manager'))
                    ->setName($this->trans('Export to SQL Manager', [], 'Admin.Actions'))
                    ->setIcon('storage')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function getBulkActions(): BulkActionCollection
    {
        return (new BulkActionCollection())
            ->add(
                (new SubmitBulkAction('enable_selection'))
                    ->setName($this->trans('Enable selection', [], 'Admin.Actions'))
                    ->setOptions([
                        'submit_route' => 'admin_personalsalesmen_bulk_enable',
                    ])
            )
            ->add(
                (new SubmitBulkAction('disable_selection'))
                    ->setName($this->trans('Disable selection', [], 'Admin.Actions'))
                    ->setOptions([
                        'submit_route' => 'admin_personalsalesmen_bulk_disable',
                    ])
            )
            ->add(
                (new SubmitBulkAction('delete_selection'))
                    ->setName($this->trans('Delete selection', [], 'Admin.Actions'))
                    ->setOptions([
                        'submit_route' => 'admin_personalsalesmen_bulk_delete',
                        'confirm_message' => $this->trans(
                            'Delete selected items?',
                            [],
                            'Admin.Notifications.Warning'
                        ),
                    ])
            );
    }

    /**
     * Get row actions
     */
    private function getRowActions(): RowActionCollection
    {
        return (new RowActionCollection())
            ->add(
                (new LinkRowAction('edit'))
                    ->setName($this->trans('Edit', [], 'Admin.Actions'))
                    ->setIcon('edit')
                    ->setOptions([
                        'route' => 'admin_personalsalesmen_edit',
                        'route_param_name' => 'id',
                        'route_param_field' => 'id_assignment',
                    ])
            )
            ->add(
                (new LinkRowAction('delete'))
                    ->setName($this->trans('Delete', [], 'Admin.Actions'))
                    ->setIcon('delete')
                    ->setOptions([
                        'route' => 'admin_personalsalesmen_delete',
                        'route_param_name' => 'id',
                        'route_param_field' => 'id_assignment',
                        'confirm_message' => $this->trans(
                            'Delete selected item?',
                            [],
                            'Admin.Notifications.Warning'
                        ),
                    ])
            );
    }
}

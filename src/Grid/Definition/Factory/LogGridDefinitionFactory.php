<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DateTimeColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Factory for log grid definition
 */
class LogGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    /**
     * @param HookDispatcherInterface $hookDispatcher Hook dispatcher service
     * @param TranslatorInterface $translator Translation service
     */
    public function __construct(
        HookDispatcherInterface $hookDispatcher,
        protected $translator,
    ) {
        parent::__construct($hookDispatcher);
    }

    /**
     * Gets the unique grid identifier
     *
     * @return string
     */
    protected function getId(): string
    {
        return 'evo_linkmanager_log';
    }

    /**
     * Gets the grid name
     *
     * @return string
     */
    protected function getName(): string
    {
        return $this->translator->trans('Activity Logs', [], 'Modules.Evolinkmanager.Admin');
    }

    /**
     * Configure grid columns
     *
     * @return ColumnCollection
     */
    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add(
                (new DataColumn('id_log'))
                    ->setName($this->translator->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_log',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DateTimeColumn('date_add'))
                    ->setName($this->translator->trans('Date', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'date_add',
                        'format' => 'Y-m-d H:i:s',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('employee_name'))
                    ->setName($this->translator->trans('Employee', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'employee_name',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('severity'))
                    ->setName($this->translator->trans('Severity', [], 'Modules.Evolinkmanager.Admin'))
                    ->setOptions([
                        'field' => 'severity',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('action'))
                    ->setName($this->translator->trans('Action', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'action',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('resource_type'))
                    ->setName($this->translator->trans('Resource Type', [], 'Modules.Evolinkmanager.Admin'))
                    ->setOptions([
                        'field' => 'resource_type',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('resource_id'))
                    ->setName($this->translator->trans('Resource ID', [], 'Modules.Evolinkmanager.Admin'))
                    ->setOptions([
                        'field' => 'resource_id',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('message'))
                    ->setName($this->translator->trans('Message', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'message',
                        'sortable' => false,
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->translator->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => $this->getRowActions(),
                    ])
            );
    }

    /**
     * Configure row actions
     *
     * @return RowActionCollection
     */
    protected function getRowActions(): RowActionCollection
    {
        return (new RowActionCollection())
            ->add(
                (new LinkRowAction('view'))
                    ->setName($this->translator->trans('View', [], 'Admin.Actions'))
                    ->setIcon('visibility')
                    ->setOptions([
                        'route' => 'evo_linkmanager_log_view',
                        'route_param_name' => 'logId',
                        'route_param_field' => 'id_log',
                        'clickable_row' => true,
                    ])
            );
    }

    /**
     * Configure grid actions
     *
     * @return GridActionCollection
     */
    protected function getGridActions(): GridActionCollection
    {
        return (new GridActionCollection())
            ->add(
                (new SimpleGridAction('common_refresh_list'))
                    ->setName($this->translator->trans('Refresh list', [], 'Admin.Advparameters.Feature'))
                    ->setIcon('refresh')
            );
    }

    /**
     * Configure filters
     *
     * @return FilterCollection
     */
    protected function getFilters(): FilterCollection
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_log', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('ID', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('id_log')
            )
            ->add(
                (new Filter('date_add', DateRangeType::class))
                    ->setTypeOptions([
                        'required' => false,
                    ])
                    ->setAssociatedColumn('date_add')
            )
            ->add(
                (new Filter('employee_name', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('Employee', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('employee_name')
            )
            ->add(
                (new Filter('severity', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->translator->trans('Info', [], 'Admin.Global') => 'info',
                            $this->translator->trans('Success', [], 'Admin.Global') => 'success',
                            $this->translator->trans('Warning', [], 'Admin.Global') => 'warning',
                            $this->translator->trans('Error', [], 'Admin.Global') => 'error',
                        ],
                    ])
                    ->setAssociatedColumn('severity')
            )
            ->add(
                (new Filter('action', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->translator->trans('Create', [], 'Admin.Actions') => 'create',
                            $this->translator->trans('Update', [], 'Admin.Actions') => 'update',
                            $this->translator->trans('Delete', [], 'Admin.Actions') => 'delete',
                            $this->translator->trans('Toggle', [], 'Modules.Evolinkmanager.Admin') => 'toggle',
                            $this->translator->trans('Install', [], 'Admin.Actions') => 'install',
                            $this->translator->trans('Uninstall', [], 'Admin.Actions') => 'uninstall',
                            $this->translator->trans('Associate', [], 'Modules.Evolinkmanager.Admin') => 'associate',
                            $this->translator->trans('Dissociate', [], 'Modules.Evolinkmanager.Admin') => 'dissociate',
                        ],
                    ])
                    ->setAssociatedColumn('action')
            )
            ->add(
                (new Filter('resource_type', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->translator->trans('Link', [], 'Modules.Evolinkmanager.Admin') => 'link',
                            $this->translator->trans('Placement', [], 'Modules.Evolinkmanager.Admin') => 'placement',
                            $this->translator->trans('Configuration', [], 'Modules.Evolinkmanager.Admin') => 'configuration',
                            $this->translator->trans('Module', [], 'Admin.Global') => 'module',
                        ],
                    ])
                    ->setAssociatedColumn('resource_type')
            )
            ->add(
                (new Filter('resource_id', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('Resource ID', [], 'Modules.Evolinkmanager.Admin'),
                        ],
                    ])
                    ->setAssociatedColumn('resource_id')
            )
            ->add(
                (new Filter('message', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('Message', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('message')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'evo_linkmanager_log_index',
                        'reset_route_params' => [],
                        'redirect_route' => 'evo_linkmanager_log_index',
                    ])
                    ->setAssociatedColumn('actions')
            );
    }

    /**
     * Configure bulk actions
     *
     * @return BulkActionCollection
     */
    protected function getBulkActions(): BulkActionCollection
    {
        return new BulkActionCollection();
    }
}

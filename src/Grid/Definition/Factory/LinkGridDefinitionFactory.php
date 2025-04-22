<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\LinkGridAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Factory for link grid definition
 */
class LinkGridDefinitionFactory extends AbstractGridDefinitionFactory
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
        return 'link';
    }

    /**
     * Gets the grid name
     *
     * @return string
     */
    protected function getName(): string
    {
        return $this->translator->trans('Links', [], 'Modules.Evolinkmanager.Admin');
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
                (new BulkActionColumn('bulk'))
                    ->setOptions([
                        'bulk_field' => 'id_link',
                    ])
            )
            ->add(
                (new DataColumn('id_link'))
                    ->setName($this->translator->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_link',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('name'))
                    ->setName($this->translator->trans('Name', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'name',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('identifier'))
                    ->setName($this->translator->trans('Identifier', [], 'Modules.Evolinkmanager.Admin'))
                    ->setOptions([
                        'field' => 'identifier',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('url'))
                    ->setName($this->translator->trans('URL', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'url',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('link_type'))
                    ->setName($this->translator->trans('Type', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'link_type',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new DataColumn('position'))
                    ->setName($this->translator->trans('Position', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'position',
                        'sortable' => true,
                    ])
            )
            ->add(
                (new ToggleColumn('active'))
                    ->setName($this->translator->trans('Active', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'active',
                        'primary_field' => 'id_link',
                        'route' => 'evo_linkmanager_link_toggle_active',
                        'route_param_name' => 'linkId',
                        'sortable' => true,
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
                (new LinkRowAction('edit'))
                    ->setName($this->translator->trans('Edit', [], 'Admin.Actions'))
                    ->setIcon('edit')
                    ->setOptions([
                        'route' => 'evo_linkmanager_link_edit',
                        'route_param_name' => 'linkId',
                        'route_param_field' => 'id_link',
                        'clickable_row' => true,
                    ])
            )
            ->add(
                (new LinkRowAction('delete'))
                    ->setName($this->translator->trans('Delete', [], 'Admin.Actions'))
                    ->setIcon('delete')
                    ->setOptions([
                        'route' => 'evo_linkmanager_link_delete',
                        'route_param_name' => 'linkId',
                        'route_param_field' => 'id_link',
                        'confirm_message' => $this->translator->trans(
                            'Delete selected item?',
                            [],
                            'Admin.Notifications.Warning'
                        ),
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
            )
            ->add(
                (new LinkGridAction('create_link'))
                    ->setName($this->translator->trans('Add new link', [], 'Modules.Evolinkmanager.Admin'))
                    ->setIcon('add_circle_outline')
                    ->setOptions([
                        'route' => 'evo_linkmanager_link_create',
                    ])
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
                (new Filter('id_link', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('ID', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('id_link')
            )
            ->add(
                (new Filter('name', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('Name', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('name')
            )
            ->add(
                (new Filter('identifier', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('Identifier', [], 'Modules.Evolinkmanager.Admin'),
                        ],
                    ])
                    ->setAssociatedColumn('identifier')
            )
            ->add(
                (new Filter('url', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->translator->trans('URL', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('url')
            )
            ->add(
                (new Filter('link_type', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->translator->trans('Custom', [], 'Modules.Evolinkmanager.Admin') => 'custom',
                            $this->translator->trans('Contact', [], 'Modules.Evolinkmanager.Admin') => 'contact',
                            $this->translator->trans('CMS Page', [], 'Modules.Evolinkmanager.Admin') => 'cms',
                        ],
                    ])
                    ->setAssociatedColumn('link_type')
            )
            ->add(
                (new Filter('active', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->translator->trans('Yes', [], 'Admin.Global') => 1,
                            $this->translator->trans('No', [], 'Admin.Global') => 0,
                        ],
                    ])
                    ->setAssociatedColumn('active')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'evo_linkmanager_link_index',
                        'reset_route_params' => [],
                        'redirect_route' => 'evo_linkmanager_link_index',
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

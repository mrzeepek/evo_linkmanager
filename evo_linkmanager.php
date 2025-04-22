<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Evolutive\Module\EvoLinkManager\Install\Installer;
use Psr\Log\LoggerInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Evo Link Manager Module.
 *
 * Manages various links like Contact, FAQ and custom links.
 */
class evo_linkmanager extends Module
{
    /**
     * Module constructor.
     */
    public function __construct()
    {
        $this->name = 'evo_linkmanager';
        $this->tab = 'front_office_features';
        $this->author = 'Evolutive';
        $this->version = '1.0.0';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->ps_versions_compliancy = [
            'min' => '8.0',
            'max' => _PS_VERSION_,
        ];

        $this->displayName = $this->trans('Link Manager', [], 'Modules.Evolinkmanager.Admin');
        $this->description = $this->trans('Manage various links like Contact, FAQ and custom links', [], 'Modules.Evolinkmanager.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Evolinkmanager.Admin');

        if (isset($this->context) && $this->context) {
            $this->registerSmartyFunction();
        }
    }

    /**
     * Install the module
     *
     * @return bool Success status
     */
    public function install(): bool
    {
        try {
            if (!parent::install()) {
                $this->_errors[] = 'Parent::install() failed.';

                return false;
            }

            /** @var LoggerInterface $logger */
            $logger = $this->get('logger');

            return (new Installer($logger))->install($this);
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();

            return false;
        }
    }

    /**
     * Uninstall the module
     *
     * @return bool Success status
     */
    public function uninstall(): bool
    {
        try {
            /** @var LoggerInterface $logger */
            $logger = $this->get('logger');

            return parent::uninstall() && (new Installer($logger))->uninstall($this);
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();

            return false;
        }
    }

    /**
     * Indicates if this module uses the new translation system
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Redirect to the module configuration page
     */
    public function getContent(): void
    {
        $route = $this->get('router')->generate('evo_linkmanager_configuration');
        Tools::redirectAdmin($route);
    }

    /**
     * Add CSS/JS in header
     *
     * @param array $params Hook parameters
     */
    public function hookActionAdminControllerSetMedia(array $params): void
    {
        $currentController = $this->context->controller->controller_name;

        if ($currentController === 'AdminEvoLinkManagerConfiguration') {
            $this->context->controller->addCSS($this->_path . 'views/css/admin-configuration.css');
        }

        // Add resources for logs page
        if ($currentController === 'AdminEvoLinkManagerLogs') {
            $this->context->controller->addCSS($this->_path . 'views/css/admin-logs.css');
        }

        // Add resources for link form
        if ($currentController === 'AdminEvoLinkManagerLinks') {
            $this->context->controller->addJS($this->_path . 'views/js/admin-link-form.js');
        }
    }

    /**
     * Assign placement URLs to Smarty variables
     *
     * @param array $params Hook parameters
     */
    public function hookActionFrontControllerSetMedia(array $params): void
    {
        try {
            // Direct access to repository and service
            $linkRepository = $this->get('evolutive.evo_linkmanager.repository.link_repository');
            $placementRepository = $this->get('evolutive.evo_linkmanager.repository.placement_repository');
            $cmsService = $this->get('evolutive.evo_linkmanager.service.cms_service');

            // Get active links
            $links = $linkRepository->getActiveLinks();
            $formattedLinks = [];

            // Format links
            foreach ($links as $link) {
                $url = $link['url'];
                if ($link['link_type'] === 'cms' && $link['id_cms']) {
                    $url = $cmsService->getCMSPageUrl((int) $link['id_cms']);
                }

                $formattedLinks[] = [
                    'id' => (int) $link['id_link'],
                    'name' => $link['name'],
                    'url' => $url,
                    'type' => $link['link_type'],
                    'active' => (bool) $link['active'],
                    'position' => (int) $link['position'],
                ];
            }

            // Get placements with their URLs
            $placements = $placementRepository->getPlacementsWithLinks();
            $placementUrls = [];

            foreach ($placements as $placement) {
                if (!isset($placement['link']) || !$placement['link']) {
                    $placementUrls[$placement['identifier']] = '#';
                    continue;
                }

                $link = $placement['link'];

                if ($link['link_type'] === 'cms' && $link['id_cms']) {
                    $placementUrls[$placement['identifier']] = $cmsService->getCMSPageUrl((int) $link['id_cms']);
                } else {
                    $placementUrls[$placement['identifier']] = $link['url'];
                }
            }

            // Assign to Smarty
            $this->context->smarty->assign([
                'evo_placement_urls' => $placementUrls,
                'evo_all_links' => $formattedLinks,
            ]);

            PrestaShopLogger::addLog(
                'Successfully assigned Smarty variables for links',
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Error in hookActionFrontControllerSetMedia: ' . $e->getMessage(),
                3,  // Error level
                null,
                'EvoLinkManager',
                0,
                true
            );
            // Assign default value
            $this->context->smarty->assign([
                'evo_placement_urls' => [],
                'evo_all_links' => [],
            ]);
        }
    }

    /**
     * Register Smarty functions for placement links
     *
     * @throws Exception If unable to register smarty functions
     */
    private function registerSmartyFunction(): void
    {
        // Register simplified function
        $smartyFunctionPath = _PS_MODULE_DIR_ . $this->name . '/smarty/function.evo_link.php';
        if (file_exists($smartyFunctionPath)) {
            require_once $smartyFunctionPath;
            \smartyRegisterFunction(
                $this->context->smarty,
                'function',
                'evo_link',
                'smarty_function_evo_link'
            );

            PrestaShopLogger::addLog(
                'evo_link smarty function registered successfully',
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );
        } else {
            PrestaShopLogger::addLog(
                'evo_link smarty function file not found: ' . $smartyFunctionPath,
                3,
                null,
                'EvoLinkManager',
                0,
                true
            );
        }

        // Legacy function - keep for backward compatibility
        $oldFunctionPath = _PS_MODULE_DIR_ . $this->name . '/smarty/function.get_evo_link_by_placement.php';
        if (file_exists($oldFunctionPath)) {
            require_once $oldFunctionPath;
            \smartyRegisterFunction(
                $this->context->smarty,
                'function',
                'get_evo_link_by_placement',
                'smarty_function_get_evo_link_by_placement'
            );
        }
    }
}

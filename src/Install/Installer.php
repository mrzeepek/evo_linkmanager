<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Install;

use Evolutive\Module\EvoLinkManager\Exception\EvoLinkManagerException;
use Psr\Log\LoggerInterface;
use Tab;

/**
 * Handles installation, uninstallation and upgrade operations for the module.
 */
class Installer
{
    /**
     * @param LoggerInterface $logger Logger service for recording installation events
     */
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Install the module with hooks, tabs and database tables
     *
     * @param \evo_linkmanager $module Module instance
     *
     * @return bool Success status
     *
     * @throws EvoLinkManagerException If installation fails
     */
    public function install(\evo_linkmanager $module): bool
    {
        try {
            // Create the smarty folder if it doesn't exist
            $smartyDir = _PS_MODULE_DIR_ . $module->name . '/smarty';
            if (!is_dir($smartyDir) && !mkdir($smartyDir, 0755, true)) {
                throw new EvoLinkManagerException('Failed to create smarty directory');
            }

            // Create the logs folder if it doesn't exist
            $logsDir = _PS_MODULE_DIR_ . $module->name . '/logs';
            if (!is_dir($logsDir) && !mkdir($logsDir, 0755, true)) {
                throw new EvoLinkManagerException('Failed to create logs directory');
            }

            return $module->registerHook($this->getHooks())
                && $this->addTabs($module)
                && $this->executeSqlFromFile($module->getLocalPath() . 'src/Resources/data/install.sql');
        } catch (\Exception $e) {
            $this->logger->error('Module installation failed', [
                'error' => $e->getMessage(),
                'module' => $module->name,
            ]);

            throw new EvoLinkManagerException('Failed to install module: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Uninstall the module with database cleanup
     *
     * @param \evo_linkmanager $module Module instance
     *
     * @return bool Success status
     */
    public function uninstall(\evo_linkmanager $module): bool
    {
        return $this->removeTabs()
            && $this->executeSqlFromFile($module->getLocalPath() . 'src/Resources/data/uninstall.sql');
    }

    /**
     * Get the list of hooks used by the module
     *
     * @return string[] List of hook names
     */
    public function getHooks(): array
    {
        return [
            'actionAdminControllerSetMedia',
            'actionFrontControllerSetMedia',
        ];
    }

    /**
     * Get tab definitions for the module
     *
     * @return array Tab configuration data
     */
    private function getTabDefinitions(): array
    {
        $mainTab = \Tab::getInstanceFromClassName('AdminParentModulesSf');
        $mainTabId = $mainTab->id;

        $tabNames = [];
        $tabConfigurationName = [];
        $tabLinksName = [];
        $tabPlacementsName = [];
        $tabLogsName = [];

        foreach (\Language::getLanguages() as $language) {
            $tabNames[$language['id_lang']] = 'Link Manager';
            $tabConfigurationName[$language['id_lang']] = 'Configuration';
            $tabLinksName[$language['id_lang']] = 'Links';
            $tabPlacementsName[$language['id_lang']] = 'Placements';
            $tabLogsName[$language['id_lang']] = 'Activity Logs';
        }

        return [
            [
                'class_name' => 'AdminEvoLinkManager',
                'id_parent' => $mainTabId,
                'module' => 'evo_linkmanager',
                'name' => $tabNames,
                'wording' => 'Link Manager',
                'wording_domain' => 'Modules.Evolinkmanager.Admin',
            ],
            [
                'class_name' => 'AdminEvoLinkManagerConfiguration',
                'route_name' => 'evo_linkmanager_configuration',
                'id_parent' => null, // Will be set dynamically
                'module' => 'evo_linkmanager',
                'name' => $tabConfigurationName,
                'wording' => 'Configuration',
                'wording_domain' => 'Modules.Evolinkmanager.Admin',
            ],
            [
                'class_name' => 'AdminEvoLinkManagerLinks',
                'route_name' => 'evo_linkmanager_link_index',
                'id_parent' => null, // Will be set dynamically
                'module' => 'evo_linkmanager',
                'name' => $tabLinksName,
                'wording' => 'Links',
                'wording_domain' => 'Modules.Evolinkmanager.Admin',
            ],
            [
                'class_name' => 'AdminEvoLinkManagerLogs',
                'route_name' => 'evo_linkmanager_log_index',
                'id_parent' => null, // Will be set dynamically
                'module' => 'evo_linkmanager',
                'name' => $tabLogsName,
                'wording' => 'Activity Logs',
                'wording_domain' => 'Modules.Evolinkmanager.Admin',
            ],
        ];
    }

    /**
     * Add tabs for the module administration
     *
     * @param \evo_linkmanager $module Module instance
     *
     * @return bool Success status
     */
    private function addTabs(\evo_linkmanager $module): bool
    {
        try {
            $tabs = $this->getTabDefinitions();
            $parentTabId = null;

            // First pass: install the parent tab
            foreach ($tabs as $tabData) {
                if ($tabData['class_name'] === 'AdminEvoLinkManager') {
                    $tab = \Tab::getInstanceFromClassName($tabData['class_name']);

                    if (null === $tab->id) {
                        $tab = new \Tab();
                        $tab->class_name = $tabData['class_name'];
                        $tab->id_parent = $tabData['id_parent'];
                        $tab->module = $tabData['module'];
                        $tab->name = $tabData['name'];
                        $tab->wording = $tabData['wording'];
                        $tab->wording_domain = $tabData['wording_domain'];
                        $tab->add();

                        \PrestaShopLogger::addLog(
                            sprintf('[EvoLinkManager] Created parent tab %s with ID: %d', $tabData['class_name'], $tab->id),
                            1
                        );
                    }

                    $parentTabId = $tab->id;
                    break;
                }
            }

            // Second pass: install child tabs
            if ($parentTabId) {
                foreach ($tabs as $tabData) {
                    if ($tabData['class_name'] !== 'AdminEvoLinkManager') {
                        $tab = \Tab::getInstanceFromClassName($tabData['class_name']);

                        if (null === $tab->id) {
                            $tab = new \Tab();
                            $tab->class_name = $tabData['class_name'];
                            $tab->route_name = $tabData['route_name'];
                            $tab->id_parent = $parentTabId;
                            $tab->module = $tabData['module'];
                            $tab->name = $tabData['name'];
                            $tab->wording = $tabData['wording'];
                            $tab->wording_domain = $tabData['wording_domain'];
                            $tab->add();

                            \PrestaShopLogger::addLog(
                                sprintf('[EvoLinkManager] Created tab %s with ID: %d', $tabData['class_name'], $tab->id),
                                1
                            );
                        }
                    }
                }
            } else {
                \PrestaShopLogger::addLog('[EvoLinkManager] Failed to get parent tab ID', 3);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                sprintf('[EvoLinkManager] Error adding tabs: %s', $e->getMessage()),
                3
            );

            return false;
        }
    }

    /**
     * Remove tabs for the module
     *
     * @return bool Success status
     */
    private function removeTabs(): bool
    {
        try {
            $tabs = $this->getTabDefinitions();

            // First pass: remove child tabs
            foreach ($tabs as $tabData) {
                if ($tabData['class_name'] !== 'AdminEvoLinkManager') {
                    $id_tab = (int) \Tab::getIdFromClassName($tabData['class_name']);

                    if ($id_tab) {
                        $tab = new \Tab($id_tab);
                        $tab->delete();

                        \PrestaShopLogger::addLog(
                            sprintf('[EvoLinkManager] Removed tab %s', $tabData['class_name']),
                            1
                        );
                    }
                }
            }

            // Second pass: remove parent tab
            foreach ($tabs as $tabData) {
                if ($tabData['class_name'] === 'AdminEvoLinkManager') {
                    $id_tab = (int) \Tab::getIdFromClassName($tabData['class_name']);

                    if ($id_tab) {
                        $tab = new \Tab($id_tab);
                        $tab->delete();

                        \PrestaShopLogger::addLog(
                            sprintf('[EvoLinkManager] Removed parent tab %s', $tabData['class_name']),
                            1
                        );
                    }

                    break;
                }
            }

            return true;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                sprintf('[EvoLinkManager] Error removing tabs: %s', $e->getMessage()),
                3
            );

            return false;
        }
    }

    /**
     * Execute SQL from a file
     *
     * @param string $filepath Path to the SQL file
     *
     * @return bool Success status
     */
    private function executeSqlFromFile(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            $this->logger->error('SQL file not found', ['filepath' => $filepath]);

            return false;
        }

        $sqlContent = file_get_contents($filepath);
        $queries = preg_split('/;\s*[\r\n]+/', $sqlContent);

        foreach ($queries as $query) {
            $query = trim(str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $query));
            if (!empty($query)) {
                try {
                    if (!\Db::getInstance()->execute($query)) {
                        $error = \Db::getInstance()->getMsgError();
                        $this->logger->error('SQL execution error', [
                            'error' => $error,
                            'query' => $query,
                        ]);
                        \PrestaShopLogger::addLog(
                            'Error executing SQL query during module installation: ' . $query . '. Error: ' . $error,
                            3,
                            null,
                            'EvoLinkManager',
                            0,
                            true
                        );

                        return false;
                    } else {
                        // Log successful query execution for debugging
                        \PrestaShopLogger::addLog(
                            'Successfully executed SQL query: ' . (strlen($query) > 100 ? substr($query, 0, 100) . '...' : $query),
                            1,
                            null,
                            'EvoLinkManager',
                            0,
                            true
                        );
                    }
                } catch (\Exception $e) {
                    $this->logger->error('SQL execution exception', [
                        'message' => $e->getMessage(),
                        'query' => $query,
                    ]);
                    \PrestaShopLogger::addLog(
                        'Exception executing SQL query: ' . $e->getMessage() . ' for query: ' . $query,
                        3,
                        null,
                        'EvoLinkManager',
                        0,
                        true
                    );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Log installation event
     *
     * @param \evo_linkmanager $module Module instance
     *
     * @return void
     */
    private function logInstallation(\evo_linkmanager $module): void
    {
        try {
            if ($module->get('service_container')
                && $module->get('service_container')->has('evolutive.evo_linkmanager.service.log_service')) {
                $logService = $module->get('evolutive.evo_linkmanager.service.log_service');

                $logService->log(
                    'install',
                    'module',
                    null,
                    'Module successfully installed',
                    'success',
                    [
                        'version' => $module->version,
                        'hooks' => $this->getHooks(),
                    ]
                );
            }
        } catch (\Exception $e) {
            // Silent fail - if logging fails, we don't want to prevent installation
            $this->logger->error('Failed to log installation event', [
                'error' => $e->getMessage(),
                'module' => $module->name,
            ]);
        }
    }

    /**
     * Log uninstallation event
     *
     * @param \evo_linkmanager $module Module instance
     *
     * @return void
     */
    private function logUninstallation(\evo_linkmanager $module): void
    {
        try {
            if ($module->get('service_container')
                && $module->get('service_container')->has('evolutive.evo_linkmanager.service.log_service')) {
                $logService = $module->get('evolutive.evo_linkmanager.service.log_service');

                $logService->log(
                    'uninstall',
                    'module',
                    null,
                    'Module uninstallation initiated',
                    'warning',
                    [
                        'version' => $module->version,
                    ]
                );
            }
        } catch (\Exception $e) {
            // Silent fail - if logging fails, we don't want to prevent uninstallation
            $this->logger->error('Failed to log uninstallation event', [
                'error' => $e->getMessage(),
                'module' => $module->name,
            ]);
        }
    }
}

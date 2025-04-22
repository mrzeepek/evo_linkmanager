<?php

declare(strict_types=1);

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Smarty function for getting a link URL by placement identifier
 *
 * @param array $params Parameters passed from Smarty
 * @param Smarty_Internal_Template $template Smarty template instance
 *
 * @return string Link URL
 *
 * @throws Exception If database query fails
 */
function smarty_function_get_evo_link_by_placement(array $params, Smarty_Internal_Template $template): string
{
    if (empty($params['identifier'])) {
        return '#';
    }

    $identifier = $params['identifier'];
    $context = Context::getContext();

    // Check if the placement URL exists in the already assigned variables
    if (isset($context->smarty->tpl_vars['evo_placement_urls'],
        $context->smarty->tpl_vars['evo_placement_urls']->value[$identifier])) {
        return $context->smarty->tpl_vars['evo_placement_urls']->value[$identifier];
    }

    // Try to get it from the module service or fallback to direct DB query
    return getUrlFromModule($identifier) ?? getLinkFromDatabase($identifier, 'placement');
}

/**
 * Smarty function for getting a link URL by link name
 *
 * @param array $params Parameters passed from Smarty
 * @param Smarty_Internal_Template $template Smarty template instance
 *
 * @return string Link URL
 *
 * @throws Exception If database query fails
 */
function smarty_function_get_evo_link_by_name(array $params, Smarty_Internal_Template $template): string
{
    if (empty($params['name'])) {
        return '#';
    }

    $name = $params['name'];
    $context = Context::getContext();

    // Check in already assigned links
    if (isset($context->smarty->tpl_vars['evo_all_links'])) {
        $allLinks = $context->smarty->tpl_vars['evo_all_links']->value;

        foreach ($allLinks as $link) {
            if ($link['name'] === $name) {
                return $link['url'];
            }
        }
    }

    // Try to get via the module service or fallback to direct DB query
    return getUrlFromModuleByName($name) ?? getLinkFromDatabase($name, 'name');
}

/**
 * Get a configured logger instance with file rotation
 *
 * @return Logger
 */
function getLogger(): Logger
{
    static $logger = null;

    if ($logger === null) {
        $logger = new Logger('evo_linkmanager');

        // Set up a rotating file handler (keep 7 days of logs, rotate daily)
        $logPath = _PS_MODULE_DIR_ . 'evo_linkmanager/logs/linkmanager.log';

        $logsDir = dirname($logPath);

        if (!is_dir($logsDir)) {
            if (!mkdir($logsDir, 0755, true)) {
                error_log('EvoLinkManager: Could not create logs directory, falling back to system temp directory');
                $logPath = sys_get_temp_dir() . '/evo_linkmanager_' . date('Y-m-d') . '.log';
            }
        }

        $handler = new RotatingFileHandler($logPath, 7, Logger::INFO);

        // Format: [yyyy-mm-dd hh:mm:ss] channel.LEVEL: message
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message%\n",
            "Y-m-d H:i:s"
        );

        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);
    }

    return $logger;
}

/**
 * Get link URL from module service
 *
 * @param string $identifier Placement identifier
 *
 * @return string|null Link URL or null if not found
 *
 * @throws \Exception If service retrieval fails
 */
function getUrlFromModule(string $identifier): ?string
{
    try {
        $module = Module::getInstanceByName('evo_linkmanager');

        if (!$module || !$module->active) {
            return null;
        }

        // Try fallback method first
        if (method_exists($module, 'getLinkService')) {
            $linkService = $module->getLinkService();
            getLogger()->info(
                'Using getLinkService() fallback method for placement: ' . $identifier
            );

            return $linkService->getLinkUrlByPlacement($identifier);
        }

        // Then try classic method with proper type hint
        /** @var \Evolutive\Module\EvoLinkManager\Service\LinkService $linkService */
        $linkService = $module->get('evolutive.evo_linkmanager.service.link_service');

        return $linkService->getLinkUrlByPlacement($identifier);
    } catch (\Exception $e) {
        // Log error but don't break the page
        getLogger()->error(
            'Error in getUrlFromModule: ' . $e->getMessage(),
            ['identifier' => $identifier, 'exception' => get_class($e)]
        );

        // Also log critical errors to PrestaShop logger for admin visibility
        PrestaShopLogger::addLog(
            'Critical error in getUrlFromModule: ' . $e->getMessage(),
            3,
            null,
            'EvoLinkManager',
            0,
            true
        );

        return null;
    }
}

/**
 * Get link URL from module service by name
 *
 * @param string $name Link name
 *
 * @return string|null Link URL or null if not found
 */
function getUrlFromModuleByName(string $name): ?string
{
    try {
        $module = Module::getInstanceByName('evo_linkmanager');

        if (!$module || !$module->active) {
            return null;
        }

        // Try using getLinkService method if it exists
        if (method_exists($module, 'getLinkService')) {
            $linkService = $module->getLinkService();
            getLogger()->info(
                'Using getLinkService() method for name: ' . $name
            );

            return $linkService->getLinkUrlByName($name);
        }

        // If no method exists, return null
        getLogger()->notice(
            'No method available to get URL from module for name: ' . $name
        );
        return null;
    } catch (\Exception $e) {
        // Log error but don't break the page
        getLogger()->error(
            'Error in getUrlFromModuleByName: ' . $e->getMessage(),
            ['name' => $name, 'exception' => get_class($e)]
        );

        // Log critical errors to PrestaShop logger for admin visibility
        PrestaShopLogger::addLog(
            'Critical error in getUrlFromModuleByName: ' . $e->getMessage(),
            3,
            null,
            'EvoLinkManager',
            0,
            true
        );

        return null;
    }
}

/**
 * Get link from database as ultimate fallback
 *
 * @param string $value The value to search for (identifier or name)
 * @param string $type Search type ('placement' or 'name')
 *
 * @return string Link URL or '#' if not found
 */
function getLinkFromDatabase(string $value, string $type): string
{
    try {
        $db = Db::getInstance();
        $dbPrefix = _DB_PREFIX_;

        if ($type === 'placement') {
            return getLinkByPlacementFromDb($db, $dbPrefix, $value);
        }

        return getLinkByNameFromDb($db, $value);
    } catch (Exception $e) {
        getLogger()->error(
            'Error in database fallback getLinkFromDatabase: ' . $e->getMessage(),
            ['value' => $value, 'type' => $type]
        );

        // Also log critical errors to PrestaShop logger for admin visibility
        PrestaShopLogger::addLog(
            'Critical error in getLinkFromDatabase: ' . $e->getMessage(),
            3,
            null,
            'EvoLinkManager',
            0,
            true
        );

        return '#';
    }
}

/**
 * Get link by placement identifier from database
 *
 * @param Db $db Database instance
 * @param string $dbPrefix Database prefix
 * @param string $identifier Placement identifier
 *
 * @return string Link URL or '#' if not found
 *
 * @throws \PrestaShopDatabaseException If database query fails
 * @throws \PrestaShopException If CMS link generation fails
 */
function getLinkByPlacementFromDb(Db $db, string $dbPrefix, string $identifier): string
{
    try {
        $query = 'SELECT l.*
                FROM `' . $dbPrefix . 'evo_linkmanager_placement` p
                LEFT JOIN `' . $dbPrefix . 'evo_linkmanager_placement_link` pl
                    ON p.id_placement = pl.id_placement
                LEFT JOIN `' . $dbPrefix . 'evo_linkmanager_link` l
                    ON pl.id_link = l.id_link
                WHERE p.identifier = "' . pSQL($identifier) . '"
                AND p.active = 1
                AND l.active = 1';

        $result = $db->executeS($query);

        if (empty($result) || !isset($result[0])) {
            getLogger()->notice(
                'No link found for placement identifier: ' . $identifier
            );
            return '#';
        }

        $link = $result[0];

        // If it's a CMS, get the CMS URL
        if ($link['link_type'] === 'cms' && $link['id_cms']) {
            $cmsLink = new Link();
            getLogger()->info(
                'Direct DB fallback used for CMS link: ' . $identifier
            );

            return $cmsLink->getCMSLink((int) $link['id_cms']);
        }

        getLogger()->info(
            'Direct DB fallback used for link: ' . $identifier
        );

        return $link['url'];
    } catch (\Exception $e) {
        getLogger()->error(
            'Error in getLinkByPlacementFromDb: ' . $e->getMessage(),
            ['identifier' => $identifier, 'exception' => get_class($e)]
        );
        return '#';
    }
}

/**
 * Get link by name from database
 *
 * @param Db $db Database instance
 * @param string $name Link name
 *
 * @return string Link URL or '#' if not found
 *
 * @throws \PrestaShopDatabaseException If database query fails
 * @throws \PrestaShopException If CMS link generation fails
 */
function getLinkByNameFromDb(Db $db, string $name): string
{
    try {
        $query = new DbQuery();
        $query->select('*')
            ->from('evo_linkmanager_link')
            ->where('name = "' . pSQL($name) . '"')
            ->where('active = 1')
            ->orderBy('position', 'ASC');

        $result = $db->executeS($query);

        if (empty($result) || !isset($result[0])) {
            getLogger()->notice(
                'No link found for name: ' . $name
            );
            return '#';
        }

        $link = $result[0];

        // If it's a CMS, get the CMS URL
        if ($link['link_type'] === 'cms' && $link['id_cms']) {
            $cmsLink = new Link();
            getLogger()->info(
                'Direct DB fallback used for CMS link by name: ' . $name
            );

            return $cmsLink->getCMSLink((int) $link['id_cms']);
        }

        getLogger()->info(
            'Direct DB fallback used for link by name: ' . $name
        );

        return $link['url'];
    } catch (\Exception $e) {
        getLogger()->error(
            'Error in getLinkByNameFromDb: ' . $e->getMessage(),
            ['name' => $name, 'exception' => get_class($e)]
        );
        return '#';
    }
}


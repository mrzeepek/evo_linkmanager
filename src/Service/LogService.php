<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Employee;

/**
 * Service for managing logs in the module
 */
class LogService
{
    /**
     * Severity levels
     */
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_SUCCESS = 'success';

    /**
     * Resource types
     */
    public const RESOURCE_LINK = 'link';
    public const RESOURCE_PLACEMENT = 'placement';
    public const RESOURCE_CONFIGURATION = 'configuration';
    public const RESOURCE_MODULE = 'module';

    /**
     * Action types
     */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_TOGGLE = 'toggle';
    public const ACTION_INSTALL = 'install';
    public const ACTION_UNINSTALL = 'uninstall';
    public const ACTION_ASSOCIATE = 'associate';
    public const ACTION_DISSOCIATE = 'dissociate';

    /**
     * @param Connection $connection Database connection
     * @param string $dbPrefix Database table prefix
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $dbPrefix,
    ) {
    }

    /**
     * Add a log entry
     *
     * @param string $action The action performed
     * @param string $resourceType The type of resource
     * @param int|null $resourceId The ID of the resource (optional)
     * @param string $message The log message
     * @param string $severity The severity level
     * @param array|null $details Additional details (will be JSON encoded)
     *
     * @return int The ID of the created log entry
     *
     * @throws \Exception If database operation fails
     */
    public function log(
        string $action,
        string $resourceType,
        ?int $resourceId,
        string $message,
        string $severity = self::SEVERITY_INFO,
        ?array $details = null,
    ): int {
        $tableName = $this->dbPrefix . 'evo_linkmanager_log';
        $now = new \DateTime();

        // Get employee information if available
        [$idEmployee, $employeeName] = $this->getEmployeeInfo();

        try {
            $detailsJson = $details ? json_encode($details, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) : null;

            $this->connection->insert($tableName, [
                'id_employee' => $idEmployee,
                'employee_name' => $employeeName,
                'severity' => $severity,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'action' => $action,
                'message' => $message,
                'details' => $detailsJson,
                'date_add' => $now->format('Y-m-d H:i:s'),
            ]);

            return (int) $this->connection->lastInsertId();
        } catch (\Exception $e) {
            // If we can't log to database, at least log to PrestaShopLogger
            \PrestaShopLogger::addLog(
                'Failed to add log: ' . $e->getMessage() . ' - Original message: ' . $message,
                3,
                null,
                'EvoLinkManager',
                $resourceId ?? 0,
                true
            );

            throw $e;
        }
    }

    /**
     * Get logs with filtering options
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Items per page
     * @param string $orderBy Order by field
     * @param string $orderDirection Order direction
     *
     * @return array Logs data and pagination info
     *
     * @throws \Exception If database operation fails
     */
    public function getLogs(
        array $filters = [],
        int $page = 1,
        int $limit = 50,
        string $orderBy = 'date_add',
        string $orderDirection = 'DESC',
    ): array {
        $tableName = $this->dbPrefix . 'evo_linkmanager_log';
        $offset = ($page - 1) * $limit;

        // Build query
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName);

        $this->applyLogFilters($qb, $filters);

        // Get total count
        $countQb = clone $qb;
        $countQb->select('COUNT(*) as total');
        $totalLogs = (int) $countQb->execute()->fetchOne();

        // Order and limit for results
        $qb->orderBy($orderBy, $orderDirection)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $logs = $qb->execute()->fetchAllAssociative();

        return [
            'logs' => $logs,
            'pagination' => [
                'total' => $totalLogs,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalLogs / $limit),
            ],
        ];
    }

    /**
     * Log a successful link creation
     *
     * @param int $linkId The ID of the created link
     * @param array $linkData The link data
     *
     * @return int The ID of the created log entry
     *
     * @throws \Exception If database operation fails
     */
    public function logLinkCreation(int $linkId, array $linkData): int
    {
        return $this->log(
            self::ACTION_CREATE,
            self::RESOURCE_LINK,
            $linkId,
            sprintf('Link "%s" (ID: %d) has been created', $linkData['name'] ?? 'Unknown', $linkId),
            self::SEVERITY_SUCCESS,
            $linkData
        );
    }

    /**
     * Log a successful link update
     *
     * @param int $linkId The ID of the updated link
     * @param array $linkData The updated link data
     *
     * @return int The ID of the created log entry
     *
     * @throws \Exception If database operation fails
     */
    public function logLinkUpdate(int $linkId, array $linkData): int
    {
        return $this->log(
            self::ACTION_UPDATE,
            self::RESOURCE_LINK,
            $linkId,
            sprintf('Link "%s" (ID: %d) has been updated', $linkData['name'] ?? 'Unknown', $linkId),
            self::SEVERITY_INFO,
            $linkData
        );
    }

    /**
     * Log a successful link deletion
     *
     * @param int $linkId The ID of the deleted link
     * @param array $linkData The deleted link data
     *
     * @return int The ID of the created log entry
     *
     * @throws \Exception If database operation fails
     */
    public function logLinkDeletion(int $linkId, array $linkData): int
    {
        return $this->log(
            self::ACTION_DELETE,
            self::RESOURCE_LINK,
            $linkId,
            sprintf('Link "%s" (ID: %d) has been deleted', $linkData['name'] ?? 'Unknown', $linkId),
            self::SEVERITY_WARNING,
            $linkData
        );
    }

    /**
     * Log a link status toggle
     *
     * @param int $linkId The ID of the toggled link
     * @param bool $newStatus The new status
     * @param array $linkData The link data
     *
     * @return int The ID of the created log entry
     *
     * @throws \Exception If database operation fails
     */
    public function logLinkToggle(int $linkId, bool $newStatus, array $linkData): int
    {
        return $this->log(
            self::ACTION_TOGGLE,
            self::RESOURCE_LINK,
            $linkId,
            sprintf(
                'Link "%s" (ID: %d) has been %s',
                $linkData['name'] ?? 'Unknown',
                $linkId,
                $newStatus ? 'activated' : 'deactivated'
            ),
            self::SEVERITY_INFO,
            array_merge($linkData, ['new_status' => $newStatus])
        );
    }

    /**
     * Log a successful placement-link association
     *
     * @param int $placementId The ID of the placement
     * @param int $linkId The ID of the link
     * @param array $data Additional data
     *
     * @return int The ID of the created log entry
     *
     * @throws \Exception If database operation fails
     */
    public function logPlacementLinkAssociation(int $placementId, int $linkId, array $data): int
    {
        return $this->log(
            self::ACTION_ASSOCIATE,
            self::RESOURCE_PLACEMENT,
            $placementId,
            sprintf('Placement (ID: %d) has been associated with Link (ID: %d)', $placementId, $linkId),
            self::SEVERITY_INFO,
            array_merge($data, ['link_id' => $linkId])
        );
    }

    /**
     * Clear all logs
     *
     * @return int Number of deleted logs
     *
     * @throws \Exception If database operation fails
     */
    public function clearLogs(): int
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_log';

        try {
            $qb = $this->connection->createQueryBuilder();
            $qb->delete($tableName);

            return $qb->execute();
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error clearing logs: ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                0,
                true
            );
            throw $e;
        }
    }

    /**
     * Get available severity levels for filtering
     *
     * @return array Severity levels
     */
    public function getAvailableSeverities(): array
    {
        return [
            self::SEVERITY_INFO => 'Information',
            self::SEVERITY_SUCCESS => 'Success',
            self::SEVERITY_WARNING => 'Warning',
            self::SEVERITY_ERROR => 'Error',
        ];
    }

    /**
     * Get available resource types for filtering
     *
     * @return array Resource types
     */
    public function getAvailableResourceTypes(): array
    {
        return [
            self::RESOURCE_LINK => 'Link',
            self::RESOURCE_PLACEMENT => 'Placement',
            self::RESOURCE_CONFIGURATION => 'Configuration',
            self::RESOURCE_MODULE => 'Module',
        ];
    }

    /**
     * Get available actions for filtering
     *
     * @return array Actions
     */
    public function getAvailableActions(): array
    {
        return [
            self::ACTION_CREATE => 'Create',
            self::ACTION_UPDATE => 'Update',
            self::ACTION_DELETE => 'Delete',
            self::ACTION_TOGGLE => 'Toggle Status',
            self::ACTION_INSTALL => 'Install',
            self::ACTION_UNINSTALL => 'Uninstall',
            self::ACTION_ASSOCIATE => 'Associate',
            self::ACTION_DISSOCIATE => 'Dissociate',
        ];
    }

    /**
     * Get log by ID
     *
     * @param int $logId Log ID
     *
     * @return array|null Log data or null if not found
     *
     * @throws \Exception If database operation fails
     */
    public function getLogById(int $logId): ?array
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_log';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName)
            ->where('id_log = :id_log')
            ->setParameter('id_log', $logId);

        $result = $qb->execute()->fetchAssociative();

        return $result ?: null;
    }

    /**
     * Format log details
     *
     * @param array $log Log data
     *
     * @return array Log data with formatted fields
     */
    public function formatLogForDisplay(array $log): array
    {
        if (!empty($log['details'])) {
            try {
                $log['details_array'] = json_decode($log['details'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // Keep details as they are if not valid JSON
            }
        }

        if (!empty($log['date_add'])) {
            $dateTime = new \DateTime($log['date_add']);
            $log['date_formatted'] = $dateTime->format('Y-m-d H:i:s');
            $log['date_relative'] = $this->getRelativeTime($dateTime);
        }

        // Translate action names for better display
        if (!empty($log['action'])) {
            $log['action_label'] = $this->getActionLabel($log['action']);
        }

        // Format resource types for better display
        if (!empty($log['resource_type'])) {
            $log['resource_label'] = $this->getResourceLabel($log['resource_type']);
        }

        return $log;
    }

    /**
     * Apply filters to the log query
     *
     * @param QueryBuilder $qb Query builder
     * @param array $filters Filters to apply
     *
     * @return void
     */
    private function applyLogFilters(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['severity'], $filters['severity']) && $filters['severity']) {
            $qb->andWhere('severity = :severity')
                ->setParameter('severity', $filters['severity']);
        }

        if (isset($filters['resource_type'], $filters['resource_type']) && $filters['resource_type']) {
            $qb->andWhere('resource_type = :resource_type')
                ->setParameter('resource_type', $filters['resource_type']);
        }

        if (isset($filters['resource_id'], $filters['resource_id']) && $filters['resource_id']) {
            $qb->andWhere('resource_id = :resource_id')
                ->setParameter('resource_id', $filters['resource_id']);
        }

        if (isset($filters['action'], $filters['action']) && $filters['action']) {
            $qb->andWhere('action = :action')
                ->setParameter('action', $filters['action']);
        }

        if (isset($filters['date_from'], $filters['date_from']) && $filters['date_from']) {
            $qb->andWhere('date_add >= :date_from')
                ->setParameter('date_from', $filters['date_from'] . ' 00:00:00');
        }

        if (isset($filters['date_to'], $filters['date_to']) && $filters['date_to']) {
            $qb->andWhere('date_add <= :date_to')
                ->setParameter('date_to', $filters['date_to'] . ' 23:59:59');
        }

        if (isset($filters['search'], $filters['search']) && $filters['search']) {
            $searchTerm = '%' . $filters['search'] . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('message', ':search'),
                    $qb->expr()->like('details', ':search'),
                    $qb->expr()->like('employee_name', ':search')
                )
            )
                ->setParameter('search', $searchTerm);
        }
    }

    /**
     * Get employee information
     *
     * @return array [id_employee, employee_name]
     */
    private function getEmployeeInfo(): array
    {
        $idEmployee = null;
        $employeeName = null;

        if (\Context::getContext()->employee instanceof \Employee) {
            $idEmployee = (int) \Context::getContext()->employee->id;
            $employeeName = \Context::getContext()->employee->firstname . ' ' . \Context::getContext()->employee->lastname;
        }

        return [$idEmployee, $employeeName];
    }

    /**
     * Get action label
     *
     * @param string $action Action name
     *
     * @return string Human-readable action label
     */
    private function getActionLabel(string $action): string
    {
        $actionLabels = [
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'toggle' => 'Toggle',
            'install' => 'Install',
            'uninstall' => 'Uninstall',
            'associate' => 'Associate',
            'dissociate' => 'Dissociate',
        ];

        return $actionLabels[$action] ?? $action;
    }

    /**
     * Get resource label
     *
     * @param string $resourceType Resource type
     *
     * @return string Human-readable resource label
     */
    private function getResourceLabel(string $resourceType): string
    {
        $resourceLabels = [
            'link' => 'Link',
            'placement' => 'Placement',
            'configuration' => 'Configuration',
            'module' => 'Module',
        ];

        return $resourceLabels[$resourceType] ?? $resourceType;
    }

    /**
     * Get relative time description
     *
     * @param \DateTime $dateTime Date to format
     *
     * @return string Relative time string
     */
    private function getRelativeTime(\DateTime $dateTime): string
    {
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $dateTime->getTimestamp();

        if ($diff < 60) {
            return $diff . ' seconds ago';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);

            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);

            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        $days = floor($diff / 86400);

        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

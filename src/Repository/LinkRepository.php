<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Repository;

use Doctrine\DBAL\Connection;
use Evolutive\Module\EvoLinkManager\Exception\LinkNotFoundException;
use Evolutive\Module\EvoLinkManager\Service\LogService;

/**
 * Repository for link operations
 */
class LinkRepository
{
    /**
     * @param Connection $connection Database connection
     * @param string $dbPrefix Database table prefix
     * @param LogService|null $logService Optional log service
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $dbPrefix,
        private readonly ?LogService $logService = null,
    ) {
    }

    /**
     * Get link by ID
     *
     * @param int $linkId Link ID
     *
     * @return array Link data
     *
     * @throws LinkNotFoundException If link not found
     */
    public function getById(int $linkId): array
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName)
            ->where('id_link = :id_link')
            ->setParameter('id_link', $linkId);

        $result = $qb->execute()->fetchAssociative();

        if (!$result) {
            throw new LinkNotFoundException(sprintf('Link with ID %d not found', $linkId));
        }

        return $result;
    }

    /**
     * Get all links
     *
     * @param bool|null $activeOnly Filter by active status
     * @param string|null $orderBy Order by field
     * @param string $orderDirection Order direction
     *
     * @return array Links data
     *
     * @throws \Exception If database operation fails
     */
    public function getLinks(
        ?bool $activeOnly = null,
        ?string $orderBy = 'position',
        string $orderDirection = 'ASC',
    ): array {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName);

        if ($activeOnly !== null) {
            $qb->andWhere('active = :active')
                ->setParameter('active', $activeOnly ? 1 : 0);
        }

        $qb->orderBy($orderBy ?? 'position', $orderDirection);

        return $qb->execute()->fetchAllAssociative();
    }

    /**
     * Get active links
     *
     * @return array Active links
     *
     * @throws \Exception If database operation fails
     */
    public function getActiveLinks(): array
    {
        return $this->getLinks(true);
    }

    /**
     * Create a new link
     *
     * @param array $data Link data
     *
     * @return int ID of the created link
     *
     * @throws \Exception If database operation fails
     */
    public function create(array $data): int
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';
        $now = new \DateTime();

        $position = $this->getNextPosition();

        $this->connection->insert($tableName, [
            'name' => $data['name'],
            'url' => $data['url'],
            'link_type' => $data['link_type'],
            'id_cms' => $data['id_cms'],
            'position' => $data['position'] ?? $position,
            'active' => (int) ($data['active'] ?? true),
            'date_add' => $now->format('Y-m-d H:i:s'),
            'date_upd' => $now->format('Y-m-d H:i:s'),
        ]);

        $newLinkId = (int) $this->connection->lastInsertId();

        // Log the link creation
        $this->logAction('create', $newLinkId, $data);

        return $newLinkId;
    }

    /**
     * Update an existing link
     *
     * @param int $linkId Link ID
     * @param array $data Link data
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function update(int $linkId, array $data): bool
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';
        $now = new \DateTime();

        $updateData = [
            'date_upd' => $now->format('Y-m-d H:i:s'),
        ];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['url'])) {
            $updateData['url'] = $data['url'];
        }

        if (isset($data['link_type'])) {
            $updateData['link_type'] = $data['link_type'];
        }

        if (array_key_exists('id_cms', $data)) {
            $updateData['id_cms'] = $data['id_cms'];
        }

        if (isset($data['position'])) {
            $updateData['position'] = $data['position'];
        }

        if (isset($data['active'])) {
            $updateData['active'] = (int) $data['active'];
        }

        // Fetch old data for logging purposes
        $oldData = null;
        try {
            $oldData = $this->getById($linkId);
        } catch (\Exception $e) {
            // Silently continue if we can't get the old data
        }

        $result = $this->connection->update($tableName, $updateData, [
            'id_link' => $linkId,
        ]);

        // Log the link update
        if ($result > 0) {
            $this->logAction('update', $linkId, array_merge($oldData ?? [], $data));
        }

        return $result > 0;
    }

    /**
     * Delete a link and its associated placements
     *
     * @param int $linkId Link ID
     * @param bool $deletePlacement Whether to also delete orphaned placements
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function delete(int $linkId, bool $deletePlacement = false): bool
    {
        $tableLinkName = $this->dbPrefix . 'evo_linkmanager_link';
        $tablePlacementLinkName = $this->dbPrefix . 'evo_linkmanager_placement_link';
        $tablePlacementName = $this->dbPrefix . 'evo_linkmanager_placement';

        $this->connection->beginTransaction();

        try {
            // Fetch the link data before deletion for logging
            $linkData = null;
            try {
                $linkData = $this->getById($linkId);
            } catch (\Exception $e) {
                // If we can't get the link data, continue without it
            }

            // Get placements associated with this link if needed
            $placementIds = [];
            if ($deletePlacement) {
                $qb = $this->connection->createQueryBuilder();
                $qb->select('id_placement')
                    ->from($tablePlacementLinkName)
                    ->where('id_link = :linkId')
                    ->setParameter('linkId', $linkId);

                $result = $qb->execute()->fetchAllAssociative();
                $placementIds = array_column($result, 'id_placement');
            }

            // Delete the link
            $linkDeleted = $this->connection->delete($tableLinkName, [
                'id_link' => $linkId,
            ]);

            // Log the link deletion
            if ($linkDeleted > 0 && $linkData) {
                $this->logAction('delete', $linkId, $linkData);
            }

            // Delete orphaned placements if requested
            if ($deletePlacement && !empty($placementIds)) {
                $this->deleteOrphanedPlacements($placementIds, $tablePlacementLinkName, $tablePlacementName, $linkId);
            }

            $this->connection->commit();

            return $linkDeleted > 0;
        } catch (\Exception $e) {
            $this->connection->rollBack();

            \PrestaShopLogger::addLog(
                'Error in delete: ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                $linkId,
                true
            );

            // Log error
            if ($this->logService) {
                $this->logService->log(
                    'delete',
                    'link',
                    $linkId,
                    sprintf('Error during link deletion: %s', $e->getMessage()),
                    'error',
                    ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                );
            }

            throw $e;
        }
    }

    /**
     * Toggle active status for a link
     *
     * @param int $linkId Link ID
     *
     * @return bool Success status
     *
     * @throws LinkNotFoundException If link not found
     * @throws \Exception If database operation fails
     */
    public function toggleActive(int $linkId): bool
    {
        $link = $this->getById($linkId);
        $newActiveStatus = !(bool) $link['active'];

        $result = $this->update($linkId, [
            'active' => $newActiveStatus,
        ]);

        // Log the toggle action specifically
        if ($result) {
            $this->logAction('toggle', $linkId, [
                'name' => $link['name'],
                'previous_status' => (bool) $link['active'],
                'new_status' => $newActiveStatus,
            ]);
        }

        return $result;
    }

    /**
     * Update link positions
     *
     * @param array $positions Array of link positions [id => position]
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function updatePositions(array $positions): bool
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';
        $now = new \DateTime();
        $success = true;

        foreach ($positions as $linkId => $position) {
            $result = $this->connection->update($tableName, [
                'position' => (int) $position,
                'date_upd' => $now->format('Y-m-d H:i:s'),
            ], [
                'id_link' => (int) $linkId,
            ]);

            if ($result <= 0) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get links by name
     *
     * @param string $name Link name
     *
     * @return array Links matching the name
     *
     * @throws \Exception If database operation fails
     */
    public function getLinksByName(string $name): array
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName)
            ->where('name = :name')
            ->setParameter('name', $name)
            ->andWhere('active = 1')
            ->orderBy('position', 'ASC');

        return $qb->execute()->fetchAllAssociative();
    }

    /**
     * Get the next available position
     *
     * @return int Next position
     *
     * @throws \Exception If database operation fails
     */
    private function getNextPosition(): int
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('IFNULL(MAX(position), 0) + 1 as next_position')
            ->from($tableName);

        $result = $qb->execute()->fetchAssociative();

        return (int) ($result['next_position'] ?? 1);
    }

    /**
     * Delete orphaned placements
     *
     * @param array $placementIds Placement IDs to check
     * @param string $tablePlacementLinkName Placement link table name
     * @param string $tablePlacementName Placement table name
     * @param int $linkId Link ID for logging purposes
     *
     * @return void
     *
     * @throws \Exception If database operation fails
     */
    private function deleteOrphanedPlacements(
        array $placementIds,
        string $tablePlacementLinkName,
        string $tablePlacementName,
        int $linkId,
    ): void {
        foreach ($placementIds as $placementId) {
            // Check if this placement has no other links associated
            $qb = $this->connection->createQueryBuilder();
            $qb->select('COUNT(*)')
                ->from($tablePlacementLinkName)
                ->where('id_placement = :placementId')
                ->setParameter('placementId', $placementId);

            $count = (int) $qb->execute()->fetchOne();

            // If no more links are associated, delete the placement
            if ($count === 0) {
                $this->connection->delete($tablePlacementName, [
                    'id_placement' => $placementId,
                ]);

                \PrestaShopLogger::addLog(
                    'Orphaned placement deleted: ' . $placementId,
                    1,
                    null,
                    'EvoLinkManager',
                    $linkId,
                    true
                );

                // Log placement deletion
                if ($this->logService) {
                    $this->logService->log(
                        'delete',
                        'placement',
                        (int) $placementId,
                        sprintf('Orphaned placement (ID: %d) deleted during link deletion', $placementId),
                        'warning'
                    );
                }
            }
        }
    }

    /**
     * Helper method to log actions via LogService if available
     *
     * @param string $action Action name
     * @param int $linkId Link ID
     * @param array $data Related data
     *
     * @return void
     */
    private function logAction(string $action, int $linkId, array $data): void
    {
        if (!$this->logService) {
            return;
        }

        switch ($action) {
            case 'create':
                $this->logService->logLinkCreation($linkId, $data);
                break;
            case 'update':
                $this->logService->logLinkUpdate($linkId, $data);
                break;
            case 'delete':
                $this->logService->logLinkDeletion($linkId, $data);
                break;
            case 'toggle':
                $this->logService->logLinkToggle($linkId, $data['new_status'] ?? false, $data);
                break;
            default:
                $this->logService->log(
                    $action,
                    'link',
                    $linkId,
                    sprintf('Action "%s" performed on link ID: %d', $action, $linkId),
                    'info',
                    $data
                );
        }
    }
}

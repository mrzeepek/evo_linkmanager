<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Repository;

use Doctrine\DBAL\Connection;
use Evolutive\Module\EvoLinkManager\Exception\PlacementNotFoundException;

/**
 * Repository for placement operations
 */
class PlacementRepository
{
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
     * Get placement by ID
     *
     * @param int $placementId Placement ID
     *
     * @return array Placement data
     *
     * @throws PlacementNotFoundException If placement not found
     * @throws \Exception If database operation fails
     */
    public function getById(int $placementId): array
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName)
            ->where('id_placement = :id_placement')
            ->setParameter('id_placement', $placementId);

        $result = $qb->execute()->fetchAssociative();

        if (!$result) {
            throw new PlacementNotFoundException(sprintf('Placement with ID %d not found', $placementId));
        }

        return $result;
    }

    /**
     * Get placement by identifier
     *
     * @param string $identifier Placement identifier
     *
     * @return array|null Placement data or null if not found
     *
     * @throws \Exception If database operation fails
     */
    public function getByIdentifier(string $identifier): ?array
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName)
            ->where('identifier = :identifier')
            ->setParameter('identifier', $identifier);

        $result = $qb->execute()->fetchAssociative();

        \PrestaShopLogger::addLog(
            'getByIdentifier: ' . $identifier . ', Result: ' . ($result ? 'found' : 'not found'),
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        return $result ?: null;
    }

    /**
     * Get link ID by placement identifier
     *
     * @param string $identifier Placement identifier
     *
     * @return int|null Link ID or null if not found
     *
     * @throws \Exception If database operation fails
     */
    public function getLinkIdByPlacementIdentifier(string $identifier): ?int
    {
        $placementTable = $this->dbPrefix . 'evo_linkmanager_placement';
        $junctionTable = $this->dbPrefix . 'evo_linkmanager_placement_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('j.id_link')
            ->from($placementTable, 'p')
            ->innerJoin('p', $junctionTable, 'j', 'p.id_placement = j.id_placement')
            ->where('p.identifier = :identifier')
            ->andWhere('p.active = 1')
            ->setParameter('identifier', $identifier);

        $result = $qb->execute()->fetchOne();

        return $result ? (int) $result : null;
    }

    /**
     * Get placement by link ID
     *
     * @param int $linkId Link ID
     *
     * @return array|null Placement data or null if not found
     *
     * @throws \Exception If database operation fails
     */
    public function getPlacementByLinkId(int $linkId): ?array
    {
        $placementTable = $this->dbPrefix . 'evo_linkmanager_placement';
        $junctionTable = $this->dbPrefix . 'evo_linkmanager_placement_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.*')
            ->from($placementTable, 'p')
            ->innerJoin('p', $junctionTable, 'j', 'p.id_placement = j.id_placement')
            ->where('j.id_link = :id_link')
            ->setParameter('id_link', $linkId);

        $result = $qb->execute()->fetchAssociative();

        \PrestaShopLogger::addLog(
            'getPlacementByLinkId: ' . $linkId . ', Result: ' . ($result ? 'found' : 'not found'),
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        return $result ?: null;
    }

    /**
     * Get all placements
     *
     * @param bool|null $activeOnly Filter by active status
     *
     * @return array Placements data
     *
     * @throws \Exception If database operation fails
     */
    public function getPlacements(?bool $activeOnly = null): array
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($tableName);

        if ($activeOnly !== null) {
            $qb->andWhere('active = :active')
                ->setParameter('active', $activeOnly ? 1 : 0);
        }

        return $qb->execute()->fetchAllAssociative();
    }

    /**
     * Get placements with their associated links
     *
     * @param bool $activeOnly Filter by active status
     *
     * @return array Placements data with linked links
     *
     * @throws \Exception If database operation fails
     */
    public function getPlacementsWithLinks(bool $activeOnly = true): array
    {
        $placementTable = $this->dbPrefix . 'evo_linkmanager_placement';
        $junctionTable = $this->dbPrefix . 'evo_linkmanager_placement_link';
        $linkTable = $this->dbPrefix . 'evo_linkmanager_link';

        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.*, l.*')
            ->from($placementTable, 'p')
            ->leftJoin('p', $junctionTable, 'j', 'p.id_placement = j.id_placement')
            ->leftJoin('j', $linkTable, 'l', 'j.id_link = l.id_link');

        if ($activeOnly) {
            $qb->andWhere('p.active = 1');
        }

        $results = $qb->execute()->fetchAllAssociative();

        // Restructure the result to have links as nested objects
        $placements = [];
        foreach ($results as $row) {
            $placementId = $row['id_placement'];

            if (!isset($placements[$placementId])) {
                $placements[$placementId] = [
                    'id_placement' => $row['id_placement'],
                    'identifier' => $row['identifier'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'active' => $row['active'],
                    'date_add' => $row['date_add'],
                    'date_upd' => $row['date_upd'],
                    'link' => null,
                ];
            }

            // If there is a link associated with this placement
            if ($row['id_link']) {
                $placements[$placementId]['link'] = [
                    'id_link' => $row['id_link'],
                    'name' => $row['name'],
                    'url' => $row['url'],
                    'link_type' => $row['link_type'],
                    'id_cms' => $row['id_cms'],
                    'position' => $row['position'],
                    'active' => $row['active'],
                ];
            }
        }

        return array_values($placements);
    }

    /**
     * Create a new placement
     *
     * @param array $data Placement data
     *
     * @return int ID of the created placement
     *
     * @throws \Exception If database operation fails
     */
    public function create(array $data): int
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement';
        $now = new \DateTime();

        \PrestaShopLogger::addLog(
            'Creating placement with data: ' . json_encode($data),
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        try {
            $this->connection->insert($tableName, [
                'identifier' => $data['identifier'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => (int) ($data['active'] ?? true),
                'date_add' => $now->format('Y-m-d H:i:s'),
                'date_upd' => $now->format('Y-m-d H:i:s'),
            ]);

            $newId = (int) $this->connection->lastInsertId();

            \PrestaShopLogger::addLog(
                'Created placement with ID: ' . $newId,
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );

            return $newId;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error creating placement: ' . $e->getMessage(),
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
     * Update an existing placement
     *
     * @param int $placementId Placement ID
     * @param array $data Placement data
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function update(int $placementId, array $data): bool
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement';
        $now = new \DateTime();

        $updateData = [
            'date_upd' => $now->format('Y-m-d H:i:s'),
        ];

        if (isset($data['identifier'])) {
            $updateData['identifier'] = $data['identifier'];
        }

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['active'])) {
            $updateData['active'] = (int) $data['active'];
        }

        try {
            $result = $this->connection->update($tableName, $updateData, [
                'id_placement' => $placementId,
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error updating placement: ' . $e->getMessage(),
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
     * Delete a placement
     *
     * @param int $placementId Placement ID
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function delete(int $placementId): bool
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement';

        try {
            $result = $this->connection->delete($tableName, [
                'id_placement' => $placementId,
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error deleting placement: ' . $e->getMessage(),
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
     * Associate a link with a placement
     *
     * @param int $placementId Placement ID
     * @param int $linkId Link ID
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function associateLink(int $placementId, int $linkId): bool
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement_link';

        \PrestaShopLogger::addLog(
            'Associating placement ID: ' . $placementId . ' with link ID: ' . $linkId,
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        try {
            // First, remove any existing association for this placement
            $this->connection->delete($tableName, [
                'id_placement' => $placementId,
            ]);

            // Then, create the new association
            $result = $this->connection->insert($tableName, [
                'id_placement' => $placementId,
                'id_link' => $linkId,
            ]);

            \PrestaShopLogger::addLog(
                'Association created: ' . ($result ? 'success' : 'failure'),
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );

            return $result > 0;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error associating link: ' . $e->getMessage(),
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
     * Remove association between a placement and a link
     *
     * @param int $placementId Placement ID
     * @param int $linkId Link ID
     *
     * @return bool Success status
     *
     * @throws \Exception If database operation fails
     */
    public function dissociateLink(int $placementId, int $linkId): bool
    {
        $tableName = $this->dbPrefix . 'evo_linkmanager_placement_link';

        try {
            $result = $this->connection->delete($tableName, [
                'id_placement' => $placementId,
                'id_link' => $linkId,
            ]);

            \PrestaShopLogger::addLog(
                'Dissociated placement ID: ' . $placementId . ' from link ID: ' . $linkId . ', Result: ' . ($result ? 'success' : 'failure'),
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );

            return $result > 0;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error dissociating link: ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                0,
                true
            );
            throw $e;
        }
    }
}

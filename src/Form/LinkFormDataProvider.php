<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Form;

use Evolutive\Module\EvoLinkManager\Repository\LinkRepository;
use Evolutive\Module\EvoLinkManager\Repository\PlacementRepository;
use Evolutive\Module\EvoLinkManager\Service\CMSService;

/**
 * Data provider for link form
 */
class LinkFormDataProvider
{
    /**
     * @param LinkRepository $linkRepository Repository for link operations
     * @param CMSService $cmsService Service for CMS operations
     * @param PlacementRepository $placementRepository Repository for placement operations
     */
    public function __construct(
        private readonly LinkRepository $linkRepository,
        private readonly CMSService $cmsService,
        private readonly PlacementRepository $placementRepository,
    ) {
    }

    /**
     * Save form data
     *
     * @param array $data Form data
     * @param int|null $linkId Link ID for updates
     *
     * @return bool|int Returns the new ID or true for updates
     */
    public function saveData(array $data, ?int $linkId = null)
    {
        // Vérifier si l'ID est fourni dans les données du formulaire
        $effectiveLinkId = $linkId;
        if (isset($data['id_link']) && $data['id_link'] > 0) {
            $effectiveLinkId = (int) $data['id_link'];
        }

        \PrestaShopLogger::addLog(
            'Saving data with linkId: ' . ($effectiveLinkId ?? 'null') . ', Identifier: ' . ($data['identifier'] ?? 'null'),
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        $saveData = [
            'name' => $data['name'],
            'link_type' => $data['link_type'],
            'position' => (int) $data['position'],
            'active' => (bool) $data['active'],
        ];

        // Process URL based on link type
        if ($data['link_type'] === 'cms') {
            $saveData['url'] = ''; // URL is generated dynamically for CMS pages
            $saveData['id_cms'] = (int) $data['id_cms'];
        } else {
            $saveData['url'] = $data['url'];
            $saveData['id_cms'] = null;
        }

        // Vérifions que nous avons un ID valide avant de procéder à la mise à jour
        if ($effectiveLinkId !== null) {
            try {
                // Vérifier que le lien existe bien
                $existingLink = $this->linkRepository->getById($effectiveLinkId);

                $result = $this->linkRepository->update($effectiveLinkId, $saveData);

                \PrestaShopLogger::addLog(
                    'Link updated with ID: ' . $effectiveLinkId . ', Result: ' . ($result ? 'success' : 'failure'),
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );

                // Handle placement identifier if provided
                if (!empty($data['identifier'])) {
                    \PrestaShopLogger::addLog(
                        'Handling placement for existing link ID: ' . $effectiveLinkId . ' with identifier: ' . $data['identifier'],
                        1,
                        null,
                        'EvoLinkManager',
                        0,
                        true
                    );
                    $this->handlePlacementForLink($effectiveLinkId, $data['identifier']);
                } else {
                    // Remove placement association if identifier is empty
                    \PrestaShopLogger::addLog(
                        'Removing placement for link ID: ' . $effectiveLinkId . ' (empty identifier)',
                        1,
                        null,
                        'EvoLinkManager',
                        0,
                        true
                    );
                    $this->removePlacementForLink($effectiveLinkId);
                }

                return $result;
            } catch (\Exception $e) {
                \PrestaShopLogger::addLog(
                    'Error updating link: ' . $e->getMessage() . '. Creating new link instead.',
                    3,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );
                // Continuer vers la création si l'ID n'est pas valide
            }
        }

        // Création
        $newLinkId = $this->linkRepository->create($saveData);

        \PrestaShopLogger::addLog(
            'Link created with ID: ' . $newLinkId,
            1,
            null,
            'EvoLinkManager',
            0,
            true
        );

        // Handle placement identifier if provided
        if (!empty($data['identifier'])) {
            \PrestaShopLogger::addLog(
                'Handling placement for new link ID: ' . $newLinkId . ' with identifier: ' . $data['identifier'],
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );
            $this->handlePlacementForLink($newLinkId, $data['identifier']);
        }

        return $newLinkId;
    }

    /**
     * Get data for a form
     *
     * @param int|null $linkId
     *
     * @return array
     */
    public function getData(?int $linkId = null): array
    {
        if ($linkId === null) {
            return [
                'name' => '',
                'url' => '',
                'link_type' => 'custom',
                'id_cms' => null,
                'position' => $this->linkRepository->getLinks() ? count($this->linkRepository->getLinks()) + 1 : 1,
                'active' => true,
                'identifier' => '',
            ];
        }

        $link = $this->linkRepository->getById($linkId);

        // If it's a CMS link, get the URL from the CMS service
        $url = $link['url'];
        if ($link['link_type'] === 'cms' && $link['id_cms']) {
            $url = $this->cmsService->getCMSPageUrl((int) $link['id_cms']);
        }

        // Get placement identifier for this link if it exists
        $identifier = '';
        try {
            $placement = $this->placementRepository->getPlacementByLinkId($linkId);
            if ($placement) {
                $identifier = $placement['identifier'];

                \PrestaShopLogger::addLog(
                    'Found placement for link ID: ' . $linkId . ', identifier: ' . $identifier,
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );
            }
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error getting placement for link ID: ' . $linkId . ': ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                0,
                true
            );
        }

        return [
            'name' => $link['name'],
            'url' => $url,
            'link_type' => $link['link_type'],
            'id_cms' => $link['id_cms'],
            'position' => (int) $link['position'],
            'active' => (bool) $link['active'],
            'identifier' => $identifier,
        ];
    }

    /**
     * Create or update a placement for a link
     *
     * @param int $linkId Link ID
     * @param string $identifier Placement identifier
     *
     * @return void
     */
    private function handlePlacementForLink(int $linkId, string $identifier): void
    {
        try {
            \PrestaShopLogger::addLog(
                'Handling placement for linkId: ' . $linkId . ', identifier: ' . $identifier,
                1,
                null,
                'EvoLinkManager',
                0,
                true
            );

            // Check if the placement already exists with this identifier
            $existingPlacement = $this->placementRepository->getByIdentifier($identifier);

            if ($existingPlacement) {
                \PrestaShopLogger::addLog(
                    'Placement exists with ID: ' . $existingPlacement['id_placement'] . ', associating with link ID: ' . $linkId,
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );

                // If placement exists, associate it with this link
                $this->placementRepository->associateLink((int) $existingPlacement['id_placement'], $linkId);
            } else {
                \PrestaShopLogger::addLog(
                    'Creating new placement with identifier: ' . $identifier . ' for link ID: ' . $linkId,
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );

                // Create a new placement
                $placementId = $this->placementRepository->create([
                    'identifier' => $identifier,
                    'name' => 'Placement for link #' . $linkId,
                    'description' => 'Automatically created placement for link: ' . $linkId,
                    'active' => true,
                ]);

                \PrestaShopLogger::addLog(
                    'Created placement with ID: ' . $placementId . ', associating with link ID: ' . $linkId,
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );

                // Associate the new placement with the link
                $this->placementRepository->associateLink($placementId, $linkId);
            }
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error in handlePlacementForLink: ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                $linkId,
                true
            );
            throw $e; // Re-throw to ensure the error is handled at higher level
        }
    }

    /**
     * Remove placement association for a link
     *
     * @param int $linkId Link ID
     *
     * @return void
     */
    private function removePlacementForLink(int $linkId): void
    {
        try {
            // Find current placement for this link
            $placement = $this->placementRepository->getPlacementByLinkId($linkId);

            if ($placement) {
                \PrestaShopLogger::addLog(
                    'Removing association between placement ID: ' . $placement['id_placement'] . ' and link ID: ' . $linkId,
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );

                // Remove the association
                $this->placementRepository->dissociateLink((int) $placement['id_placement'], $linkId);
            } else {
                \PrestaShopLogger::addLog(
                    'No placement found for link ID: ' . $linkId . ' to remove',
                    1,
                    null,
                    'EvoLinkManager',
                    0,
                    true
                );
            }
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error in removePlacementForLink: ' . $e->getMessage(),
                3,
                null,
                'EvoLinkManager',
                $linkId,
                true
            );
            throw $e; // Re-throw to ensure the error is handled at higher level
        }
    }
}

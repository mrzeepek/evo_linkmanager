<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Controller\Admin;

use Evolutive\Module\EvoLinkManager\Exception\EvoLinkManagerException;
use Evolutive\Module\EvoLinkManager\Exception\PlacementNotFoundException;
use Evolutive\Module\EvoLinkManager\Repository\LinkRepository;
use Evolutive\Module\EvoLinkManager\Repository\PlacementRepository;
use Evolutive\Module\EvoLinkManager\Service\CMSService;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for managing the module configuration
 */
class ConfigurationController extends FrameworkBundleAdminController
{
    /**
     * @param LinkRepository $linkRepository Repository for link operations
     * @param RouterInterface $router Router service
     * @param TranslatorInterface $translator Translator service
     * @param PlacementRepository|null $placementRepository Repository for placement operations
     * @param CMSService|null $cmsService Service for CMS operations
     */
    public function __construct(
        private readonly LinkRepository $linkRepository,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly ?PlacementRepository $placementRepository = null,
        private readonly ?CMSService $cmsService = null,
    ) {
    }

    /**
     * Display configuration page with links and placements
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param Request $request Request object
     *
     * @return Response
     *
     * @throws PlacementNotFoundException If placement data cannot be retrieved
     * @throws EvoLinkManagerException If service retrieval fails
     */
    public function indexAction(Request $request): Response
    {
        try {
            // Get links for display
            $links = $this->linkRepository->getLinks();

            // Get placements with associated link information
            [$placements, $hasPlacement, $placementsWithLinks] = $this->getPlacementsData();

            // Enhance placements with link name information for better display
            $enhancedPlacements = $this->enhancePlacements($placements, $placementsWithLinks);

            return $this->render('@Modules/evo_linkmanager/views/templates/admin/configuration.html.twig', [
                'links' => $links,
                'placements' => $enhancedPlacements,
                'hasPlacement' => $hasPlacement,
                'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
                'enableSidebar' => true,
                'layoutTitle' => $this->translator->trans('Link Manager Configuration', [], 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (PlacementNotFoundException $e) {
            $this->addFlash(
                'warning',
                $this->translator->trans(
                    'Could not load placement information: %error%',
                    ['%error%' => $e->getMessage()],
                    'Modules.Evolinkmanager.Admin'
                )
            );

            // Continue with what we have - render the view without placements
            return $this->render('@Modules/evo_linkmanager/views/templates/admin/configuration.html.twig', [
                'links' => $this->linkRepository->getLinks(),
                'placements' => [],
                'hasPlacement' => [],
                'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
                'enableSidebar' => true,
                'layoutTitle' => $this->translator->trans('Link Manager Configuration', [], 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'Error loading configuration: %error%',
                    ['%error%' => $e->getMessage()],
                    'Modules.Evolinkmanager.Admin'
                )
            );

            return $this->redirectToRoute('admin_dashboard');
        } catch (\Exception $e) {
            // Wrap any unexpected exception in our custom exception for consistency
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'An unexpected error occurred: %error%',
                    ['%error%' => $e->getMessage()],
                    'Modules.Evolinkmanager.Admin'
                )
            );

            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * Get placements data including associations with links
     *
     * @return array Array containing [placements, hasPlacement, placementsWithLinks]
     *
     * @throws PlacementNotFoundException If placement data cannot be retrieved
     * @throws EvoLinkManagerException If service retrieval fails
     */
    private function getPlacementsData(): array
    {
        $placements = [];
        $hasPlacement = [];
        $placementsWithLinks = [];

        try {
            // Try to get the PlacementRepository via the container if not injected
            $placementRepository = $this->placementRepository ?? $this->get('evolutive.evo_linkmanager.repository.placement_repository');

            if (!$placementRepository) {
                throw new EvoLinkManagerException(
                    'Placement repository service not available',
                    EvoLinkManagerException::CONFIGURATION_ERROR
                );
            }

            $placements = $placementRepository->getPlacements();
            $placementsWithLinks = $placementRepository->getPlacementsWithLinks();

            // Prepare an array to store placement identifiers by link
            foreach ($placementsWithLinks as $placement) {
                if (isset($placement['link']) && $placement['link']) {
                    $linkId = $placement['link']['id_link'];
                    $hasPlacement[$linkId] = $placement['identifier'];
                }
            }
        } catch (PlacementNotFoundException $e) {
            // Re-throw placement specific exceptions
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions in our domain exception
            throw new EvoLinkManagerException(
                sprintf('Could not load placement information: %s', $e->getMessage()),
                EvoLinkManagerException::GENERAL_ERROR,
                $e
            );
        }

        return [$placements, $hasPlacement, $placementsWithLinks];
    }

    /**
     * Enhance placements with link name information
     *
     * @param array $placements Placements data
     * @param array $placementsWithLinks Placements with their associated links
     *
     * @return array Enhanced placements array
     */
    private function enhancePlacements(array $placements, array $placementsWithLinks): array
    {
        $enhancedPlacements = [];

        foreach ($placements as $placement) {
            $placementWithLink = $this->findPlacementWithLink($placement, $placementsWithLinks);

            $linkName = null;
            if ($placementWithLink && isset($placementWithLink['link'])) {
                $linkName = $placementWithLink['link']['name'];
            }

            $enhancedPlacements[] = array_merge($placement, [
                'link_name' => $linkName,
            ]);
        }

        return $enhancedPlacements;
    }

    /**
     * Find a placement with its associated link
     *
     * @param array $placement Placement to find
     * @param array $placementsWithLinks List of placements with their links
     *
     * @return array|null Placement with link or null if not found
     */
    private function findPlacementWithLink(array $placement, array $placementsWithLinks): ?array
    {
        foreach ($placementsWithLinks as $pwl) {
            if ($pwl['id_placement'] == $placement['id_placement']) {
                return $pwl;
            }
        }

        return null;
    }
}

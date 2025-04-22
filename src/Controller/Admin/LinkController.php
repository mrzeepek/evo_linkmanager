<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Controller\Admin;

use Evolutive\Module\EvoLinkManager\Exception\EvoLinkManagerException;
use Evolutive\Module\EvoLinkManager\Exception\LinkNotFoundException;
use Evolutive\Module\EvoLinkManager\Form\LinkFormHandler;
use Evolutive\Module\EvoLinkManager\Repository\LinkRepository;
use Evolutive\Module\EvoLinkManager\Service\LogService;
use PrestaShop\PrestaShop\Core\Grid\GridFactory;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for managing links
 */
class LinkController extends FrameworkBundleAdminController
{
    /**
     * @param GridFactory $linkGridFactory Factory for link grid
     * @param LinkFormHandler $linkFormHandler Form handler for links
     * @param LinkRepository $linkRepository Repository for link operations
     * @param LogService|null $logService Optional log service
     */
    public function __construct(
        private readonly GridFactory $linkGridFactory,
        private readonly LinkFormHandler $linkFormHandler,
        private readonly LinkRepository $linkRepository,
        private readonly ?LogService $logService = null,
    ) {
    }

    /**
     * Display the list of links
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param Request $request Current request
     *
     * @return Response
     */
    public function indexAction(Request $request): Response
    {
        try {
            $searchCriteria = new SearchCriteria(
                [],
                [],
                '',
                0,
                50
            );

            $linkGrid = $this->linkGridFactory->getGrid($searchCriteria);

            return $this->render('@Modules/evo_linkmanager/views/templates/admin/link/index.html.twig', [
                'linkGrid' => $this->presentGrid($linkGrid),
                'enableSidebar' => true,
                'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
                'layoutTitle' => $this->trans('Links', 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error loading links: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * Create a new link
     *
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     *
     * @param Request $request Current request
     *
     * @return Response
     */
    public function createAction(Request $request): Response
    {
        try {
            $form = $this->linkFormHandler->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $formData = $form->getData();
                $errors = $this->linkFormHandler->save($formData);

                if (empty($errors)) {
                    $this->addFlash(
                        'success',
                        $this->trans('Link created successfully.', 'Admin.Notifications.Success')
                    );

                    return $this->redirectToRoute('evo_linkmanager_link_index');
                }

                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                // Log form errors
                $this->logFormErrors('create', null, $formData, $errors);
            }

            return $this->render('@Modules/evo_linkmanager/views/templates/admin/link/form.html.twig', [
                'linkForm' => $form->createView(),
                'enableSidebar' => true,
                'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
                'layoutTitle' => $this->trans('Add New Link', 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error creating link: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            return $this->redirectToRoute('evo_linkmanager_link_index');
        }
    }

    /**
     * Edit an existing link
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     *
     * @param Request $request Current request
     * @param int $linkId ID of the link to edit
     *
     * @return Response
     *
     * @throws LinkNotFoundException If link does not exist
     */
    public function editAction(Request $request, int $linkId): Response
    {
        try {
            $form = $this->linkFormHandler->getForm($linkId);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $formData = $form->getData();
                $errors = $this->linkFormHandler->save($formData);

                if (empty($errors)) {
                    $this->addFlash(
                        'success',
                        $this->trans('Link updated successfully.', 'Admin.Notifications.Success')
                    );

                    return $this->redirectToRoute('evo_linkmanager_link_index');
                }

                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                // Log form errors
                $this->logFormErrors('update', $linkId, $formData, $errors);
            }

            return $this->render('@Modules/evo_linkmanager/views/templates/admin/link/form.html.twig', [
                'linkForm' => $form->createView(),
                'enableSidebar' => true,
                'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
                'layoutTitle' => $this->trans('Edit Link', 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (LinkNotFoundException $e) {
            $this->addFlash(
                'error',
                $this->trans('Link not found.', 'Modules.Evolinkmanager.Admin')
            );

            return $this->redirectToRoute('evo_linkmanager_link_index');
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error editing link: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            return $this->redirectToRoute('evo_linkmanager_link_index');
        }
    }

    /**
     * Delete a link
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", redirectRoute="evo_linkmanager_link_index")
     *
     * @param int $linkId ID of the link to delete
     *
     * @return Response
     */
    public function deleteAction(int $linkId): Response
    {
        try {
            // Check if the link exists before deletion
            $link = $this->linkRepository->getById($linkId);

            // Attempt deletion
            $result = $this->linkRepository->delete($linkId, true); // true to delete placements

            if ($result) {
                $this->addFlash(
                    'success',
                    $this->trans('Link deleted successfully.', 'Admin.Notifications.Success')
                );
            } else {
                $this->addFlash(
                    'error',
                    $this->trans('Cannot delete link. Link not found.', 'Modules.Evolinkmanager.Admin')
                );

                // Log deletion failure
                $this->logDeletionFailure($linkId);
            }
        } catch (LinkNotFoundException $e) {
            $this->addFlash(
                'error',
                $this->trans('Link not found.', 'Modules.Evolinkmanager.Admin')
            );

            // Log not found error
            $this->logLinkNotFound($linkId, $e->getCode());
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error deleting link: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            // Log module exception
            $this->logExceptionDuringDeletion($linkId, $e);
        } catch (\Exception $e) {
            $this->addFlash(
                'error',
                $this->trans('Cannot delete link: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            // Log generic exception
            $this->logExceptionDuringDeletion($linkId, $e);
        }

        return $this->redirectToRoute('evo_linkmanager_link_index');
    }

    /**
     * Toggle active status for a link
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     *
     * @param Request $request Current request
     * @param int $linkId ID of the link to toggle
     *
     * @return Response
     */
    public function toggleActiveAction(Request $request, int $linkId): Response
    {
        try {
            $success = $this->linkRepository->toggleActive($linkId);

            if ($success) {
                $this->addFlash(
                    'success',
                    $this->trans('Link status updated successfully.', 'Admin.Notifications.Success')
                );
            } else {
                $this->addFlash(
                    'error',
                    $this->trans('Could not update link status.', 'Admin.Notifications.Error')
                );

                // Log failure
                $this->logToggleFailure($linkId);
            }
        } catch (LinkNotFoundException $e) {
            $this->addFlash(
                'error',
                $this->trans('Link not found.', 'Modules.Evolinkmanager.Admin')
            );

            // Log not found error
            $this->logLinkNotFound($linkId, $e->getCode());
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error updating link status: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            // Log module exception
            $this->logExceptionDuringToggle($linkId, $e);
        } catch (\Exception $e) {
            $this->addFlash(
                'error',
                $this->trans('Error occurred: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            // Log generic exception
            $this->logExceptionDuringToggle($linkId, $e);
        }

        return $this->redirectToRoute('evo_linkmanager_link_index');
    }

    /**
     * Log form errors during link operations
     *
     * @param string $action Action name (create or update)
     * @param int|null $linkId Link ID (optional for creation)
     * @param array $formData Form data
     * @param array $errors Error messages
     *
     * @return void
     */
    private function logFormErrors(string $action, ?int $linkId, array $formData, array $errors): void
    {
        if ($this->logService) {
            $this->logService->log(
                $action,
                'link',
                $linkId,
                sprintf('Failed to %s link due to form errors', $action),
                'error',
                [
                    'form_data' => $formData,
                    'errors' => $errors,
                ]
            );
        }
    }

    /**
     * Log link not found error
     *
     * @param int $linkId Link ID
     * @param int $errorCode Error code from exception
     *
     * @return void
     */
    private function logLinkNotFound(int $linkId, int $errorCode = LinkNotFoundException::LINK_NOT_FOUND_BY_ID): void
    {
        if ($this->logService) {
            $this->logService->log(
                'delete',
                'link',
                $linkId,
                sprintf('Failed to find link: Link with ID %d not found', $linkId),
                'error',
                ['error_code' => $errorCode]
            );
        }
    }

    /**
     * Log deletion failure
     *
     * @param int $linkId Link ID
     *
     * @return void
     */
    private function logDeletionFailure(int $linkId): void
    {
        if ($this->logService) {
            $this->logService->log(
                'delete',
                'link',
                $linkId,
                sprintf('Failed to delete link ID %d: Link not found or deletion failed', $linkId),
                'error'
            );
        }
    }

    /**
     * Log exception during deletion
     *
     * @param int $linkId Link ID
     * @param \Exception $e Exception that occurred
     *
     * @return void
     */
    private function logExceptionDuringDeletion(int $linkId, \Exception $e): void
    {
        if ($this->logService) {
            $this->logService->log(
                'delete',
                'link',
                $linkId,
                sprintf('Exception during link deletion: %s', $e->getMessage()),
                'error',
                [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'error_code' => $e->getCode(),
                ]
            );
        }
    }

    /**
     * Log toggle failure
     *
     * @param int $linkId Link ID
     *
     * @return void
     */
    private function logToggleFailure(int $linkId): void
    {
        if ($this->logService) {
            $this->logService->log(
                'toggle',
                'link',
                $linkId,
                sprintf('Failed to toggle status for link ID %d', $linkId),
                'error'
            );
        }
    }

    /**
     * Log exception during toggle
     *
     * @param int $linkId Link ID
     * @param \Exception $e Exception that occurred
     *
     * @return void
     */
    private function logExceptionDuringToggle(int $linkId, \Exception $e): void
    {
        if ($this->logService) {
            $this->logService->log(
                'toggle',
                'link',
                $linkId,
                sprintf('Exception during link status toggle: %s', $e->getMessage()),
                'error',
                [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'error_code' => $e->getCode(),
                ]
            );
        }
    }
}

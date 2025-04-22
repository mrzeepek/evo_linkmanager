<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Controller\Admin;

use Evolutive\Module\EvoLinkManager\Exception\EvoLinkManagerException;
use Evolutive\Module\EvoLinkManager\Exception\LogNotFoundException;
use Evolutive\Module\EvoLinkManager\Service\LogService;
use PrestaShop\PrestaShop\Core\Grid\GridFactory;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for managing logs
 */
class LogController extends FrameworkBundleAdminController
{
    /**
     * @param GridFactory $logGridFactory Factory for log grid
     * @param LogService $logService Service for log operations
     */
    public function __construct(
        private readonly GridFactory $logGridFactory,
        private readonly LogService $logService,
    ) {
    }

    /**
     * Display logs grid
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
            $searchCriteria = $this->createSearchCriteria($request);
            $logGrid = $this->logGridFactory->getGrid($searchCriteria);

            return $this->render('@Modules/evo_linkmanager/views/templates/admin/log/index.html.twig', [
                'logGrid' => $this->presentGrid($logGrid),
                'enableSidebar' => true,
                'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
                'layoutTitle' => $this->trans('Activity Logs', 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error loading logs: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * View a log entry's details
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param int $logId ID of the log to view
     *
     * @return Response
     */
    public function viewAction(int $logId): Response
    {
        try {
            $log = $this->logService->getLogById($logId);

            if (!$log) {
                throw new LogNotFoundException(
                    sprintf('Log with ID %d not found', $logId),
                    LogNotFoundException::LOG_NOT_FOUND_BY_ID
                );
            }

            // Format details as JSON if it's a valid JSON string
            if (!empty($log['details']) && $this->isJson($log['details'])) {
                $log['details_array'] = json_decode($log['details'], true);
            }

            return $this->render('@Modules/evo_linkmanager/views/templates/admin/log/view.html.twig', [
                'log' => $log,
                'enableSidebar' => true,
                'layoutTitle' => $this->trans('Log Details', 'Modules.Evolinkmanager.Admin'),
            ]);
        } catch (LogNotFoundException $e) {
            $this->addFlash(
                'error',
                $this->trans('Log not found.', 'Modules.Evolinkmanager.Admin')
            );

            return $this->redirectToRoute('evo_linkmanager_log_index');
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans('Error viewing log: %error%', 'Modules.Evolinkmanager.Admin', ['%error%' => $e->getMessage()])
            );

            return $this->redirectToRoute('evo_linkmanager_log_index');
        }
    }

    /**
     * Clear all logs
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     *
     * @return RedirectResponse
     */
    public function clearAction(): RedirectResponse
    {
        try {
            $count = $this->logService->clearLogs();

            if ($count > 0) {
                $this->addFlash(
                    'success',
                    $this->trans(
                        'Successfully cleared %count% logs.',
                        'Modules.Evolinkmanager.Admin',
                        ['%count%' => $count]
                    )
                );
            } else {
                $this->addFlash(
                    'info',
                    $this->trans('No logs to clear.', 'Modules.Evolinkmanager.Admin')
                );
            }
        } catch (EvoLinkManagerException $e) {
            $this->addFlash(
                'error',
                $this->trans(
                    'Error clearing logs: %error%',
                    'Modules.Evolinkmanager.Admin',
                    ['%error%' => $e->getMessage()]
                )
            );
        }

        return $this->redirectToRoute('evo_linkmanager_log_index');
    }

    /**
     * Create search criteria from request
     *
     * @param Request $request Current request
     *
     * @return SearchCriteria
     */
    private function createSearchCriteria(Request $request): SearchCriteria
    {
        return new SearchCriteria(
            $request->query->all(),
            $request->query->get('orderBy', 'date_add'),
            $request->query->get('sortOrder', 'desc'),
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 50)
        );
    }

    /**
     * Create success response for log clearing
     *
     * @param int $count Number of logs cleared
     *
     * @return JsonResponse
     */
    private function createSuccessResponse(int $count): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $this->trans(
                'Successfully cleared %count% logs.',
                'Modules.Evolinkmanager.Admin',
                ['%count%' => $count]
            ),
            'count' => $count,
        ]);
    }

    /**
     * Create error response for log clearing
     *
     * @param \Exception $e Exception that occurred
     *
     * @return JsonResponse
     */
    private function createErrorResponse(\Exception $e): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->trans(
                'Error clearing logs: %error%',
                'Modules.Evolinkmanager.Admin',
                ['%error%' => $e->getMessage()]
            ),
        ], 500);
    }

    /**
     * Check if a string is valid JSON
     *
     * @param string $string String to check
     *
     * @return bool
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

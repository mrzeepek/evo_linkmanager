<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Service;

use CMS;

/**
 * Service for CMS operations
 */
class CMSService
{
    /**
     * Get all CMS pages
     *
     * @return array CMS pages data formatted for dropdown
     *
     * @throws \Exception If CMS pages cannot be retrieved
     */
    public function getCMSPages(): array
    {
        $idLang = (int) \Context::getContext()->language->id;
        $idShop = (int) \Context::getContext()->shop->id;

        // Get CMS pages
        $cmsPages = \CMS::getCMSPages($idLang, null, true, $idShop);

        // Format data for dropdown
        $formattedPages = [];
        foreach ($cmsPages as $page) {
            $formattedPages[$page['meta_title']] = (int) $page['id_cms'];
        }

        return $formattedPages;
    }

    /**
     * Get CMS page URL by ID
     *
     * @param int $idCms CMS page ID
     *
     * @return string CMS page URL
     *
     * @throws \Exception If URL generation fails
     */
    public function getCMSPageUrl(int $idCms): string
    {
        if (!$idCms) {
            return '';
        }

        try {
            $link = new \Link();

            return $link->getCMSLink($idCms);
        } catch (\Exception $e) {
            return '';
        }
    }
}

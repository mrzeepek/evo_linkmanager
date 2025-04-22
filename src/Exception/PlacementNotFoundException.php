<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Exception;

/**
 * Exception thrown when a placement is not found
 *
 * This exception is thrown when attempting to access, update or delete
 * a placement that does not exist in the database.
 */
class PlacementNotFoundException extends EvoLinkManagerException
{
    /**
     * Placement not found by ID error code
     */
    public const PLACEMENT_NOT_FOUND_BY_ID = 10200;

    /**
     * Placement not found by identifier error code
     */
    public const PLACEMENT_NOT_FOUND_BY_IDENTIFIER = 10201;

    /**
     * Placement not found by link error code
     */
    public const PLACEMENT_NOT_FOUND_BY_LINK = 10202;
}

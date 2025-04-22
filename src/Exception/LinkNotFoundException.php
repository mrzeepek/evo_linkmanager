<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Exception;

/**
 * Exception thrown when a link is not found
 *
 * This exception is thrown when attempting to access, update or delete
 * a link that does not exist in the database.
 */
class LinkNotFoundException extends EvoLinkManagerException
{
    /**
     * Link not found by ID error code
     */
    public const LINK_NOT_FOUND_BY_ID = 10100;

    /**
     * Link not found by name error code
     */
    public const LINK_NOT_FOUND_BY_NAME = 10101;

    /**
     * Link not found by placement error code
     */
    public const LINK_NOT_FOUND_BY_PLACEMENT = 10102;
}

<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Exception;

/**
 * Base exception class for all module exceptions
 *
 * This exception serves as the parent class for all specific
 * exceptions within the EvoLinkManager module.
 */
class EvoLinkManagerException extends \Exception
{
    /**
     * General error code
     */
    public const GENERAL_ERROR = 10000;

    /**
     * Database error code
     */
    public const DATABASE_ERROR = 10001;

    /**
     * Configuration error code
     */
    public const CONFIGURATION_ERROR = 10002;

    /**
     * Access denied error code
     */
    public const ACCESS_DENIED = 10003;
}

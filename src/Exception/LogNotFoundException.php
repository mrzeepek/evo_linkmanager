<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Exception;

/**
 * Exception thrown when a log is not found
 *
 * This exception is thrown when attempting to access a log
 * that does not exist in the database.
 */
class LogNotFoundException extends EvoLinkManagerException
{
    /**
     * Log not found by ID error code
     */
    public const LOG_NOT_FOUND_BY_ID = 10300;

    /**
     * Log not found by date range error code
     */
    public const LOG_NOT_FOUND_BY_DATE_RANGE = 10301;

    /**
     * Log clearing error code
     */
    public const LOG_CLEARING_ERROR = 10302;
}

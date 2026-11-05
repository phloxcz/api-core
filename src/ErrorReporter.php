<?php
/**
 * This file is part of phloxcz/api-core.
 *
 * (c) Phlox <https://github.com/phloxcz>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phlox\ApiCore;

use Tracy\ILogger;

/**
 * Records unexpected exceptions without ever throwing: a broken logger (e.g. an
 * unwritable log directory) must not stop the client from getting a valid error.
 */
final class ErrorReporter
{
    public function __construct(
        private readonly ?ILogger $logger = null,
    ) {
    }

    public function report(\Throwable $e): void
    {
        try {
            if ($this->logger !== null) {
                $this->logger->log($e, ILogger::EXCEPTION);
                return;
            }
        } catch (\Throwable $loggerFailure) {
            error_log('API error logger failed: ' . $loggerFailure->getMessage());
        }

        error_log((string) $e);
    }
}

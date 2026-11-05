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

namespace Phlox\ApiCore\Binding;

/**
 * Raised when a raw value can't be bound to a parameter of a controller method.
 */
final class InvalidParamsException extends \Exception
{
    /**
     * @param array<string, string> $fields Path such as "input.address.city" => what's wrong with it
     */
    public function __construct(
        string $message,
        public readonly array $fields = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

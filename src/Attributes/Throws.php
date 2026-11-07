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

namespace Phlox\ApiCore\Attributes;

use Attribute;
use Phlox\ApiCore\ApiError;
use Phlox\ApiCore\ApiException;

/**
 * Documents an ApiException a method can end with, in the same order:
 *
 *     #[Throws(404, 'The task was not found.')]
 *     #[Throws(409, 'The project is closed.', errorCode: 1001)]
 *     #[Throws(OrderError::Closed)]
 *
 * On a class, it applies to all of its methods.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Throws
{
    public readonly int $httpCode;
    public readonly string $message;
    public readonly ?int $errorCode;

    /**
     * @throws \InvalidArgumentException For a status that's not an error
     */
    public function __construct(int|ApiError $httpCode, ?string $message = null, ?int $errorCode = null)
    {
        if ($httpCode instanceof ApiError) {
            $case = ErrorCase::of($httpCode);
            $message ??= $case->message;
            $errorCode ??= (int) $httpCode->value;
            $httpCode = $case->httpCode;
        }

        ApiException::checkHttpCode($httpCode, $message);

        $this->httpCode = $httpCode;
        $this->message = $message ?? (new ApiException($httpCode))->getMessage();
        $this->errorCode = $errorCode;
    }
}

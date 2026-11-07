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

/**
 * Errors of an application as an enum: one place for the code of each, its
 * message and its HTTP status, for throwing as well as for documentation.
 *
 *     enum OrderError: int implements ApiError
 *     {
 *         #[ErrorCase(409, 'The order is closed.')]
 *         case Closed = 1001;
 *     }
 *
 *     throw new ApiException(OrderError::Closed);
 *     #[Throws(OrderError::Closed)]
 *
 * The value of a case is the code clients get, so it has to be an int.
 */
interface ApiError extends \BackedEnum
{
}

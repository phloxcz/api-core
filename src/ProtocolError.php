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

use Phlox\ApiCore\Attributes\ErrorCase;

/**
 * Errors of the protocol itself, with the codes JSON-RPC 2.0 gives them. Both
 * transports report them so, as clients of JSON-RPC need these codes.
 */
enum ProtocolError: int implements ApiError
{
    /** The body is not valid JSON. */
    #[ErrorCase(400, 'Parse error.')]
    case ParseError = -32700;

    /** The JSON sent is not a valid request. */
    #[ErrorCase(400, 'Invalid request.')]
    case InvalidRequest = -32600;

    /** The method does not exist or is not available. */
    #[ErrorCase(404, 'Method does not exist.')]
    case MethodNotFound = -32601;

    /** Invalid parameters, with what's wrong in `fields`. */
    #[ErrorCase(422, 'Invalid method parameter(s).')]
    case InvalidParams = -32602;

    #[ErrorCase(500, 'Internal server error.')]
    case InternalError = -32603;
}

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
 * Error reported to the API client:
 *
 *     throw new ApiException(404, 'The task was not found.');
 *     throw new ApiException(409, 'The project is closed.', errorCode: 1001);
 *     throw new ApiException(OrderError::Closed);     // see ApiError
 *
 * Clients get the message and `code`, which is the error code, or the HTTP status
 * without one. The internal message is added only in debug mode. JSON-RPC has no
 * HTTP status, and reports only the code.
 */
class ApiException extends \Exception
{
    private const DefaultMessages = [
        400 => 'Bad request.',
        401 => 'Not logged in.',
        403 => 'Forbidden.',
        404 => 'Not found.',
        405 => 'Method not allowed.',
        409 => 'Conflict.',
        415 => 'Unsupported media type.',
        422 => 'Invalid parameters.',
        500 => 'Internal server error.',
    ];

    private readonly int $httpCode;
    private readonly ?int $errorCode;

    /**
     * @param int|ApiError $httpCode An HTTP status from 400 to 599, or a case of an ApiError enum which says it all
     * @param string|null $message Public message; defaults to the one of the case or of the status
     * @param int|null $errorCode A code of your own, for clients telling errors of one status apart
     * @param string|null $internal Details shown only in debug mode
     * @param array<string, mixed> $data Additional public error data
     * @throws \InvalidArgumentException For a status that's not an error, such as a code of older versions in its place
     */
    public function __construct(
        int|ApiError $httpCode,
        ?string $message = null,
        ?int $errorCode = null,
        private readonly ?string $internal = null,
        ?\Throwable $previous = null,
        private readonly array $data = [],
    ) {
        if ($httpCode instanceof ApiError) {
            $case = ErrorCase::of($httpCode);
            $message ??= $case->message;
            $errorCode ??= (int) $httpCode->value;
            $httpCode = $case->httpCode;
        }

        self::checkHttpCode($httpCode, $message);

        $this->httpCode = $httpCode;
        $this->errorCode = $errorCode;
        parent::__construct($message ?? self::DefaultMessages[$httpCode] ?? 'Error.', $errorCode ?? $httpCode, $previous);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function checkHttpCode(int $httpCode, ?string $message = null): void
    {
        if ($httpCode < 400 || $httpCode > 599) {
            $quoted = $message === null ? '' : ', ' . var_export($message, true);
            throw new \InvalidArgumentException(
                "{$httpCode} is not the HTTP status of an error. The status comes first, and a code of your own goes to errorCode: "
                . "new ApiException(409{$quoted}, errorCode: {$httpCode}).",
            );
        }
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * The code of your own, or of the protocol; null when the HTTP status says it.
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getInternalMessage(): ?string
    {
        return $this->internal;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}

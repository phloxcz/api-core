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
 * Error reported to the API client.
 *
 * The message is always public. The internal message is only added to the
 * response in debug mode. The HTTP status is used by transports that map errors
 * to HTTP (REST); JSON-RPC ignores it.
 */
class ApiException extends \Exception
{
    /** An error occurred on the server while parsing the JSON text. */
    public const E_PARSE_ERROR = -32700;
    public const E_PARSE_ERROR_TEXT = 'Parse error.';

    /** The JSON sent is not a valid request. */
    public const E_INVALID_REQUEST = -32600;
    public const E_INVALID_REQUEST_TEXT = 'Invalid request.';

    /** The method does not exist / is not available. */
    public const E_METHOD_NOT_FOUND = -32601;
    public const E_METHOD_NOT_FOUND_TEXT = 'Method does not exist.';

    /** Invalid method parameter(s). */
    public const E_INVALID_PARAMS = -32602;
    public const E_INVALID_PARAMS_TEXT = 'Invalid method parameter(s).';

    /** Internal error. */
    public const E_INTERNAL_ERROR = -32603;
    public const E_INTERNAL_ERROR_TEXT = 'Internal server error.';

    private const DefaultMessages = [
        self::E_PARSE_ERROR => self::E_PARSE_ERROR_TEXT,
        self::E_INVALID_REQUEST => self::E_INVALID_REQUEST_TEXT,
        self::E_METHOD_NOT_FOUND => self::E_METHOD_NOT_FOUND_TEXT,
        self::E_INVALID_PARAMS => self::E_INVALID_PARAMS_TEXT,
        self::E_INTERNAL_ERROR => self::E_INTERNAL_ERROR_TEXT,
    ];

    private const DefaultHttpCodes = [
        self::E_PARSE_ERROR => 400,
        self::E_INVALID_REQUEST => 400,
        self::E_METHOD_NOT_FOUND => 404,
        self::E_INVALID_PARAMS => 422,
        self::E_INTERNAL_ERROR => 500,
        401 => 401,
        403 => 403,
    ];

    private readonly int $httpCode;

    /**
     * @param string|null $message Public message; defaults to the text of a known code
     * @param string|null $internal Details shown only in debug mode
     * @param array<string, mixed> $data Additional public error data
     * @param int|null $httpCode Defaults according to a known code, otherwise 500
     */
    public function __construct(
        int $code,
        ?string $message = null,
        private readonly ?string $internal = null,
        ?\Throwable $previous = null,
        private readonly array $data = [],
        ?int $httpCode = null,
    ) {
        parent::__construct($message ?? self::DefaultMessages[$code] ?? 'Error.', $code, $previous);
        $this->httpCode = $httpCode ?? self::DefaultHttpCodes[$code] ?? 500;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
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

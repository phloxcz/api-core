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

namespace Phlox\ApiCore\Http;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Sends Access-Control-* headers according to the "accessControl" endpoint config.
 *
 * With no allowedOrigins, no headers are sent, so browsers keep the same-origin
 * policy. A disallowed origin gets no headers either.
 */
final class CorsHandler
{
    public function __construct(
        private readonly IRequest $request,
        private readonly IResponse $response,
    ) {
    }

    /**
     * Schema of the "accessControl" config section.
     *
     * @param list<string> $defaultMethods
     */
    public static function getConfigSchema(array $defaultMethods): Schema
    {
        return Expect::structure([
            'allowedOrigins' => Expect::listOf('string')->default([]),
            'allowCredentials' => Expect::bool(false),
            'allowMethods' => Expect::listOf('string')->default($defaultMethods),
            'allowHeaders' => Expect::listOf('string')->default(['origin', 'content-type', 'accept', 'x-tracy-ajax']),
            'exposeHeaders' => Expect::listOf('string')->default(['origin', 'location', 'content-type', 'accept', 'x-tracy-ajax']),
            'maxAge' => Expect::int(1728000),
        ])->castTo('array');
    }

    /**
     * A wildcard with credentials would let any website act on behalf of a
     * logged-in user, so it's rejected when the container compiles.
     *
     * @param array{allowedOrigins: list<string>, allowCredentials: bool} $config
     * @throws \InvalidArgumentException
     */
    public static function assertValidConfig(array $config, string $endpoint): void
    {
        if ($config['allowCredentials'] && in_array('*', $config['allowedOrigins'], true)) {
            throw new \InvalidArgumentException(
                "Endpoint '{$endpoint}': 'allowCredentials: true' can't be combined with the '*' origin. "
                . 'List the allowed origins explicitly.',
            );
        }
    }

    /**
     * @param array{allowedOrigins: list<string>, allowCredentials: bool, allowMethods: list<string>, allowHeaders: list<string>, exposeHeaders: list<string>, maxAge: int} $config
     */
    public function apply(array $config): void
    {
        $origin = $this->request->getHeader('origin');
        if ($origin === null || $origin === '') {
            return;
        }

        if (in_array('*', $config['allowedOrigins'], true)) {
            $this->response->setHeader('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $config['allowedOrigins'], true)) {
            $this->response->setHeader('Access-Control-Allow-Origin', $origin);
            $this->response->addHeader('Vary', 'Origin');
            if ($config['allowCredentials']) {
                $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
            }
        } else {
            return;
        }

        $this->response->setHeader('Access-Control-Allow-Methods', implode(', ', $config['allowMethods']));
        $this->response->setHeader('Access-Control-Allow-Headers', implode(', ', $config['allowHeaders']));
        $this->response->setHeader('Access-Control-Expose-Headers', implode(', ', $config['exposeHeaders']));
        $this->response->setHeader('Access-Control-Max-Age', (string) $config['maxAge']);
    }
}

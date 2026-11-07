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

use Nette\DI\Container;
use Phlox\ApiCore\Binding\DtoSerializer;
use Phlox\ApiCore\Binding\InvalidParamsException;
use Phlox\ApiCore\Binding\ParameterBinder;
use Phlox\ApiCore\Security\Authorizer;

/**
 * Runs one API call, independently of the transport.
 *
 * Order: inject, startup, authorize, bind parameters, call. Startup runs before
 * authorization because it may authenticate the user. Parameters are bound after
 * it, so an unauthorized client learns nothing about the method's signature.
 */
final class ControllerInvoker
{
    /** @var array<string, true> */
    private array $injected = [];

    public function __construct(
        private readonly Container $container,
        private readonly ParameterBinder $binder,
        private readonly Authorizer $authorizer,
    ) {
    }

    /**
     * @param array<int|string, mixed> $parameters Named and/or positional parameters
     * @param array<int|string, mixed>|object|null $jsonData Exposed to the controller as $jsonData
     * @param array<string, mixed> $prebound Values for specific parameters, taking precedence over $parameters
     * @throws ApiException
     * @throws AbortException
     */
    public function invoke(
        Handler $handler,
        array $parameters = [],
        array|object|null $jsonData = null,
        array $prebound = [],
    ): mixed {
        $controller = $this->container->getService($handler->service);
        if (!$controller instanceof ApiController) {
            throw new \LogicException("Service '{$handler->service}' is not an ApiController.");
        }

        if (!isset($this->injected[$handler->service])) {
            $this->container->callInjects($controller);
            $this->injected[$handler->service] = true;
        }

        $controller->setParameters($handler->class, $handler->service, $handler->method, $parameters, $jsonData);

        if (!$controller->isStartupCalled()) {
            $controller->startup();
            if (!$controller->isStartupCalled()) {
                throw new \LogicException("{$handler->class}::startup() must call parent::startup().");
            }
        }

        $this->authorizer->check($handler->authorize, $handler->roles);

        try {
            $arguments = $this->binder->bind(
                new \ReflectionMethod($controller, $handler->method),
                $parameters,
                $prebound,
            );
        } catch (InvalidParamsException $e) {
            throw new ApiException(
                ProtocolError::InvalidParams,
                $e->getMessage(),
                previous: $e,
                data: ['fields' => $e->fields],
            );
        }

        return DtoSerializer::serialize($controller->{$handler->method}(...$arguments));
    }
}

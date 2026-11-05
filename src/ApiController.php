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

use Nette\Application\LinkGenerator;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Session;
use Nette\Security\User;

/**
 * Base class of API controllers.
 *
 * Only public methods declared directly on the concrete controller class are
 * callable through the API; see DI\ControllerScanner.
 */
abstract class ApiController
{
    /**
     * Here so that a controller's own constructor can call parent::__construct().
     */
    public function __construct()
    {
    }

    protected IRequest $request;
    protected IResponse $response;
    protected User $user;
    protected Session $session;
    protected ?LinkGenerator $linkGenerator = null;

    /** Called controller class */
    protected string $controllerName;

    /** Called controller service name */
    protected string $serviceName;

    /** Called method name */
    protected string $methodName;

    /** @var array<int|string, mixed> Parameters of the current call */
    protected array $parameters = [];

    /** @var array<int|string, mixed>|object|null Decoded JSON body of the current call */
    protected array|object|null $jsonData = null;

    private bool $startupCalled = false;

    /**
     * Called by the DI container.
     */
    public function injectPrimary(
        IRequest $request,
        IResponse $response,
        User $user,
        Session $session,
        ?LinkGenerator $linkGenerator = null,
    ): void {
        $this->request = $request;
        $this->response = $response;
        $this->user = $user;
        $this->session = $session;
        $this->linkGenerator = $linkGenerator;
    }

    /**
     * Called before each API call. A controller is a shared service, so one
     * instance serves all calls of a JSON-RPC batch.
     *
     * @param array<int|string, mixed> $parameters
     * @param array<int|string, mixed>|object|null $jsonData
     */
    public function setParameters(
        string $controllerName,
        string $serviceName,
        string $methodName,
        array $parameters = [],
        array|object|null $jsonData = null,
    ): void {
        $this->controllerName = $controllerName;
        $this->serviceName = $serviceName;
        $this->methodName = $methodName;
        $this->parameters = $parameters;
        $this->jsonData = $jsonData;
    }

    public function isStartupCalled(): bool
    {
        return $this->startupCalled;
    }

    /**
     * Called once per request, before authorization, so that it can
     * authenticate the user (e.g. from a token). Must call parent::startup().
     */
    public function startup(): void
    {
        $this->startupCalled = true;
    }

    /**
     * Ends the call without an API response.
     *
     * @throws AbortException
     */
    public function terminate(): never
    {
        throw new AbortException();
    }
}

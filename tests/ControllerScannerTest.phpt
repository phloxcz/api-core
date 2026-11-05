<?php

declare(strict_types=1);

use Phlox\ApiCore\ApiController;
use Phlox\ApiCore\Attributes\AllowAnonymous;
use Phlox\ApiCore\Attributes\Authorize;
use Phlox\ApiCore\Attributes\NonApi;
use Phlox\ApiCore\DI\ControllerScanner;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

trait HelperTrait
{
    public function fromTrait(): void
    {
    }
}

#[Authorize('base')]
abstract class SecuredBase extends ApiController
{
    public function inherited(): void
    {
    }

    public function overridden(): void
    {
    }
}

#[Authorize('own')]
class SecuredChild extends SecuredBase
{
    use HelperTrait;

    public function plain(): void
    {
    }

    #[Authorize('extra')]
    public function extra(): void
    {
    }

    #[AllowAnonymous]
    public function open(): void
    {
    }

    public function overridden(): void
    {
    }

    #[NonApi]
    public function hidden(): void
    {
    }

    public function startup(): void
    {
        parent::startup();
    }

    public function injectThing(): void
    {
    }

    public function injection(): void
    {
    }

    public static function staticOne(): void
    {
    }

    public function __toString(): string
    {
        return '';
    }

    protected function notPublic(): void
    {
    }
}

class InheritsAuthorize extends SecuredBase
{
    public function plain(): void
    {
    }
}

#[AllowAnonymous]
class AnonymousController extends ApiController
{
    public function plain(): void
    {
    }

    #[Authorize('x')]
    public function secured(): void
    {
    }
}

#[AllowAnonymous]
class AnonymousOnSecuredBase extends SecuredBase
{
    public function plain(): void
    {
    }
}

class NoAttributes extends ApiController
{
    public function plain(): void
    {
    }
}

#[Authorize(['a', 'b'])]
class RoleGroups extends ApiController
{
    public function anyOfClass(): void
    {
    }

    #[Authorize(['c', 'd'])]
    public function narrowed(): void
    {
    }

    #[Authorize(['c', 'd'], all: true)]
    public function allOfMethod(): void
    {
    }

    #[Authorize(['a', 'b'])]
    public function sameAsClass(): void
    {
    }

    #[Authorize]
    public function noExtraRoles(): void
    {
    }
}

// --- Exposed methods

$names = array_map(
    static fn(ReflectionMethod $method): string => $method->getName(),
    ControllerScanner::getApiMethods(new ReflectionClass(SecuredChild::class)),
);
sort($names);
Assert::same(['extra', 'fromTrait', 'injection', 'open', 'overridden', 'plain'], $names);

// --- Authorization

$resolve = static function (string $class, string $method, bool $default = true): array {
    $reflection = new ReflectionClass($class);
    $handler = ControllerScanner::createHandler('svc', $reflection, $reflection->getMethod($method), $default);
    $roles = $handler->roles;
    sort($roles);
    return [$handler->authorize, $roles];
};

Assert::same([true, [['base'], ['own']]], $resolve(SecuredChild::class, 'plain'), 'roles of parents add up');
Assert::same([true, [['base'], ['extra'], ['own']]], $resolve(SecuredChild::class, 'extra'));
Assert::same([false, []], $resolve(SecuredChild::class, 'open'), 'method-level anonymous wins');
Assert::same([true, [['base']]], $resolve(InheritsAuthorize::class, 'plain'), 'attribute from the parent still applies');
Assert::same([false, []], $resolve(AnonymousController::class, 'plain'));
Assert::same([true, [['x']]], $resolve(AnonymousController::class, 'secured'));
Assert::same([true, [['base']]], $resolve(AnonymousOnSecuredBase::class, 'plain'), 'class anonymous can\'t lift a parent\'s roles');
Assert::same([true, []], $resolve(NoAttributes::class, 'plain', true), 'endpoint default on');
Assert::same([false, []], $resolve(NoAttributes::class, 'plain', false), 'endpoint default off');

// Roles within an attribute are alternatives; attributes narrow each other
Assert::same([true, [['a', 'b']]], $resolve(RoleGroups::class, 'anyOfClass'));
Assert::same([true, [['a', 'b'], ['c', 'd']]], $resolve(RoleGroups::class, 'narrowed'));
Assert::same([true, [['c'], ['d'], ['a', 'b']]], $resolve(RoleGroups::class, 'allOfMethod'));
Assert::same([true, [['a', 'b']]], $resolve(RoleGroups::class, 'sameAsClass'), 'duplicate condition is dropped');
Assert::same([true, [['a', 'b']]], $resolve(RoleGroups::class, 'noExtraRoles'));

// --- Namespaces

Assert::true(ControllerScanner::isInNamespaces('App\Api\UserController', ['App\Api']));
Assert::true(ControllerScanner::isInNamespaces('App\Api\V1\UserController', ['App\Api']));
Assert::true(ControllerScanner::isInNamespaces('App\Api\UserController', ['\App\Api\\']));
Assert::true(ControllerScanner::isInNamespaces('Other\UserController', ['App\Api', 'Other']));
Assert::false(ControllerScanner::isInNamespaces('App\ApiAdmin\UserController', ['App\Api']));
Assert::false(ControllerScanner::isInNamespaces('App\UserController', ['App\Api']));

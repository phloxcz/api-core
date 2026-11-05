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

namespace Phlox\ApiCore\DI;

use Nette\DI\ContainerBuilder;
use Phlox\ApiCore\ApiController;
use Phlox\ApiCore\Attributes\AllowAnonymous;
use Phlox\ApiCore\Attributes\Authorize;
use Phlox\ApiCore\Attributes\NonApi;
use Phlox\ApiCore\Handler;
use ReflectionClass;
use ReflectionMethod;

/**
 * Reads API controllers when the DI container compiles, so that nothing has to
 * be reflected or cached at runtime.
 */
final class ControllerScanner
{
    /**
     * Finds registered controllers within the given namespaces (including
     * sub-namespaces) and registers them as container dependencies, so that a
     * changed controller recompiles the container in debug mode.
     *
     * @param list<string> $namespaces
     * @return array<string, ReflectionClass<ApiController>> Service name => class
     */
    public static function findControllers(ContainerBuilder $builder, array $namespaces): array
    {
        $controllers = [];

        foreach ($builder->findByType(ApiController::class) as $name => $definition) {
            $class = $definition->getType();
            if ($class === null || !self::isInNamespaces($class, $namespaces)) {
                continue;
            }

            $builder->addDependency(new ReflectionClass($class));

            /** @var class-string<ApiController> $class findByType() guarantees it */
            $controllers[(string) $name] = new ReflectionClass($class);
        }

        return $controllers;
    }

    /**
     * @param list<string> $namespaces
     */
    public static function isInNamespaces(string $class, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if (str_starts_with($class, trim($namespace, '\\') . '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methods callable through the API: public methods declared directly on the
     * controller (or in its traits). Anything inherited stays internal, so a
     * helper on a shared base controller can't be called by accident; an
     * inherited method is exposed by overriding it.
     *
     * @param ReflectionClass<ApiController> $class
     * @return list<ReflectionMethod>
     */
    public static function getApiMethods(ReflectionClass $class): array
    {
        $methods = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->getDeclaringClass()->getName() === $class->getName()
                && !$method->isStatic()
                && !str_starts_with($method->getName(), '__')
                && !preg_match('/^inject[A-Z]/', $method->getName())
                && $method->getName() !== 'startup'
                && $method->getAttributes(NonApi::class) === []
            ) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * @param ReflectionClass<ApiController> $class
     * @param bool $authorizeByDefault What applies to methods with no attribute deciding it
     */
    public static function createHandler(
        string $service,
        ReflectionClass $class,
        ReflectionMethod $method,
        bool $authorizeByDefault,
    ): Handler {
        [$authorize, $roles] = self::resolveAuthorization($class, $method, $authorizeByDefault);

        return new Handler($service, $class->getName(), $method->getName(), $authorize, $roles);
    }

    /**
     * @param ReflectionClass<ApiController> $class
     * @return array{bool, list<list<string>>}
     */
    private static function resolveAuthorization(ReflectionClass $class, ReflectionMethod $method, bool $default): array
    {
        if ($method->getAttributes(AllowAnonymous::class) !== []) {
            return [false, []];
        }

        // Attributes aren't inherited by PHP, but a subclass silently losing
        // the roles of its base controller would be a security hole.
        $authorizes = $method->getAttributes(Authorize::class);
        for ($current = $class; $current !== false; $current = $current->getParentClass()) {
            array_push($authorizes, ...$current->getAttributes(Authorize::class));
        }

        if ($authorizes !== []) {
            $groups = [];
            foreach ($authorizes as $attribute) {
                array_push($groups, ...$attribute->newInstance()->getRoleGroups());
            }
            return [true, array_values(array_unique($groups, SORT_REGULAR))];
        }

        if ($class->getAttributes(AllowAnonymous::class) !== []) {
            return [false, []];
        }

        return [$default, []];
    }
}

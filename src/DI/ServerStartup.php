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

use Nette\Application\Application;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;

/**
 * Decides when a server takes over a request.
 *
 * With nette/application it's Application::$onStartup, so the bootstrap and all
 * extensions have finished, whatever their order. Without it the container's
 * initialize() is the only moment left, and whatever comes after it doesn't run
 * for a request the API has handled.
 */
final class ServerStartup
{
    private const Hook = "if (PHP_SAPI !== 'cli' && \$this->getService(?)->process()) {\n\texit;\n}";

    /**
     * Call from beforeCompile(), when all services are known.
     *
     * @return bool Whether the server is hooked; otherwise call hookContainer() from afterCompile()
     */
    public static function hookApplication(ContainerBuilder $builder, string $server): bool
    {
        if (!class_exists(Application::class) || $builder->getByType(Application::class) === null) {
            return false;
        }

        $application = $builder->getDefinitionByType(Application::class);
        if (!$application instanceof ServiceDefinition) {
            return false;
        }

        $application->addSetup('$service->onStartup[] = function () { ' . self::Hook . ' }', [$server]);

        return true;
    }

    public static function hookContainer(ClassType $class, string $server): void
    {
        $class->getMethod('initialize')->addBody(self::Hook, [$server]);
    }
}

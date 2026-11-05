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

namespace Phlox\ApiCore\Documentation;

use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Configuration of an endpoint's document. It holds only what can't be read from
 * the code; everything else comes from controllers.
 */
final class DocumentationConfig
{
    /**
     * @param array<string, Schema> $additional Keys specific to a transport
     */
    public static function getSchema(array $additional = []): Schema
    {
        return Expect::structure($additional + [
            'enabled' => Expect::bool(true),
            'expose' => Expect::bool()->nullable(),
            'info' => Expect::array(),
            'servers' => Expect::array(),
            'extra' => Expect::array(),
        ]);
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed> Filled in with what the specifications require
     */
    public static function buildInfo(array $info, string $endpoint): array
    {
        return $info + ['title' => "{$endpoint} API", 'version' => '1.0.0'];
    }

    /**
     * Merges keys from the configuration, such as tags or x-*, into a document.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function merge(array $document, array $extra): array
    {
        foreach ($extra as $key => $value) {
            $document[$key] = is_array($value) && is_array($document[$key] ?? null) && !array_is_list($value)
                ? self::merge($document[$key], $value)
                : $value;
        }

        return $document;
    }

    /**
     * @param list<list<string>> $roles The user needs a role from every group
     */
    public static function describeRoles(array $roles): string
    {
        $groups = array_map(static fn(array $group): string => implode(' or ', $group), $roles);

        return $groups === [] ? 'Requires login.' : 'Requires login and the role ' . implode(', and ', $groups) . '.';
    }
}

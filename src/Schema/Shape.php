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

namespace Phlox\ApiCore\Schema;

use Phlox\ApiCore\Documentation\SchemaBuilder;

/**
 * A JSON object with these fields. A field is always there, unless its name ends with `?`:
 *
 *     new Shape(['id' => 'int', 'owner' => User::class, 'note?' => '?string'])
 *
 * `'note?'` can be left out, and `'?string'` can be null.
 */
final class Shape implements SchemaDefinition
{
    /**
     * @param array<array-key, SchemaDefinition|string> $fields Name => description
     */
    public function __construct(
        public readonly array $fields,
    ) {
    }

    public function toSchema(SchemaBuilder $builder): array
    {
        $properties = [];
        $required = [];

        foreach ($this->fields as $name => $field) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException("new Shape() needs names of its fields, got a list.");
            }

            $optional = str_ends_with($name, '?');
            $name = $optional ? substr($name, 0, -1) : $name;
            $properties[$name] = $builder->buildDefinition($field);
            if (!$optional) {
                $required[] = $name;
            }
        }

        return ['type' => 'object', 'properties' => $properties ?: new \stdClass()]
            + ($required === [] ? [] : ['required' => $required]);
    }
}

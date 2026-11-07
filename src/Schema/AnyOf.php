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
 * A value of one of these kinds:
 *
 *     new AnyOf('int', 'string')
 *     new ListOf(new AnyOf('int', 'string', Task::class))
 */
final class AnyOf implements SchemaDefinition
{
    /** @var list<SchemaDefinition|string> */
    public readonly array $options;

    public function __construct(SchemaDefinition|string ...$options)
    {
        if (count($options) < 2) {
            throw new \InvalidArgumentException('new AnyOf() needs at least two options; for one, name it alone.');
        }

        $this->options = array_values($options);
    }

    public function toSchema(SchemaBuilder $builder): array
    {
        return ['anyOf' => array_map($builder->buildDefinition(...), $this->options)];
    }
}

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
 * A JSON object with any names and values of one kind, such as counts by a status:
 *
 *     new MapOf('int')    // {"open": 3, "done": 5}
 */
final class MapOf implements SchemaDefinition
{
    public function __construct(
        public readonly SchemaDefinition|string $value,
    ) {
    }

    public function toSchema(SchemaBuilder $builder): array
    {
        return ['type' => 'object', 'additionalProperties' => $builder->buildDefinition($this->value)];
    }
}

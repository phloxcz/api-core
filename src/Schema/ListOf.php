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
 * A JSON array of these items:
 *
 *     new ListOf(User::class)
 *     new ListOf('int')
 */
final class ListOf implements SchemaDefinition
{
    public function __construct(
        public readonly SchemaDefinition|string $item,
    ) {
    }

    public function toSchema(SchemaBuilder $builder): array
    {
        return ['type' => 'array', 'items' => $builder->buildDefinition($this->item)];
    }
}

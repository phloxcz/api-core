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
 * JSON Schema as it is, for what the other descriptions can't say:
 *
 *     new JsonSchema(['type' => 'string', 'format' => 'date'])
 */
final class JsonSchema implements SchemaDefinition
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        public readonly array $schema,
    ) {
    }

    public function toSchema(SchemaBuilder $builder): array|\stdClass
    {
        return $this->schema === [] ? new \stdClass() : $this->schema;
    }
}

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
 * Describes a value in documentation, such as `new ListOf(User::class)` in #[Response].
 *
 * Next to these objects, a description can be a class, as `User::class`, or a scalar,
 * as `'int'`, `'float'`, `'string'`, `'bool'` or `'mixed'`, with `?` for null.
 * Implement it to describe a structure of your own, such as an envelope of responses.
 */
interface SchemaDefinition
{
    /**
     * @return array<string, mixed>|\stdClass JSON Schema
     */
    public function toSchema(SchemaBuilder $builder): array|\stdClass;
}

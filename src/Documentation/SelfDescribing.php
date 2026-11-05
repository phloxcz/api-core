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

/**
 * A class of values with a structure that can't be read from its code.
 */
interface SelfDescribing
{
    /**
     * @return array<string, mixed>|\stdClass JSON Schema
     */
    public static function describeSchema(SchemaBuilder $builder): array|\stdClass;
}

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

namespace Phlox\ApiCore\Attributes;

use Attribute;

/**
 * Overrides the documented type of a parameter.
 *
 * @deprecated Use a native type, `@param list<Foo> $name` or #[Schema] on the parameter.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Parameter
{
    /**
     * @param string|array<int|string, mixed>|null $definition See ContentDefinition
     */
    public function __construct(
        public readonly string $name,
        public readonly string|array|null $definition = null,
    ) {
    }
}

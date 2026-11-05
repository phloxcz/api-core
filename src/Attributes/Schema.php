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
 * Adds to the generated JSON Schema what can't be read from the code, such as an
 * example. On a class, it describes values of that class, which is useful for
 * ApiConstructible value objects.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Schema
{
    /**
     * @param array<string, mixed> $schema JSON Schema keywords
     */
    public function __construct(public readonly array $schema)
    {
    }
}

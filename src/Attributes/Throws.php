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
 * Documents an ApiException a method can end with. On a class, it applies to all
 * of its methods.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Throws
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly int $httpCode = 500,
    ) {
    }
}

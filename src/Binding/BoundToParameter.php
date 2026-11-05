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

namespace Phlox\ApiCore\Binding;

/**
 * A parameter's value that takes something from the declaration of the parameter,
 * usually an attribute. It's there even when the client sent nothing, so that
 * what the declaration says can't be avoided by leaving the value out.
 */
interface BoundToParameter
{
    public static function createEmpty(): static;

    public function bindToParameter(\ReflectionParameter $parameter): void;
}

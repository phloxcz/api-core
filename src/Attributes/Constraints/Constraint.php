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

namespace Phlox\ApiCore\Attributes\Constraints;

/**
 * A rule for the value of a method parameter or a DTO field. It's checked when a
 * request comes and appears in the documentation, from one definition.
 *
 * Null isn't checked: whether it's allowed depends on the type.
 */
interface Constraint
{
    /**
     * @return string|null What's wrong with the value, such as "must be at least 1."; null when it's fine
     */
    public function validate(mixed $value): ?string;

    /**
     * @return array<string, mixed> JSON Schema keywords
     */
    public function toSchema(): array;
}

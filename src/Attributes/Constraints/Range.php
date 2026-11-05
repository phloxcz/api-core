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

use Attribute;

/**
 * A number between two values, both inclusive.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Range implements Constraint
{
    public function __construct(
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        if ($this->min !== null && $value < $this->min) {
            return "must be at least {$this->min}.";
        }

        if ($this->max !== null && $value > $this->max) {
            return "must be at most {$this->max}.";
        }

        return null;
    }

    public function toSchema(): array
    {
        return array_filter(['minimum' => $this->min, 'maximum' => $this->max], static fn(mixed $value): bool => $value !== null);
    }
}

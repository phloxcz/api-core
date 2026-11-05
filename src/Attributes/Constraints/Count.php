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
 * The number of items of an array, both limits inclusive.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Count implements Constraint
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        $count = count($value);

        if ($this->min !== null && $count < $this->min) {
            return "must have at least {$this->min} items.";
        }

        if ($this->max !== null && $count > $this->max) {
            return "must have at most {$this->max} items.";
        }

        return null;
    }

    public function toSchema(): array
    {
        return array_filter(['minItems' => $this->min, 'maxItems' => $this->max], static fn(?int $value): bool => $value !== null);
    }
}

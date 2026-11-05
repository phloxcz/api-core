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
 * The number of characters of a string, both limits inclusive.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Length implements Constraint
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
    ) {
    }

    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $length = mb_strlen($value, 'UTF-8');

        if ($this->min !== null && $length < $this->min) {
            return "must have at least {$this->min} characters.";
        }

        if ($this->max !== null && $length > $this->max) {
            return "must have at most {$this->max} characters.";
        }

        return null;
    }

    public function toSchema(): array
    {
        return array_filter(['minLength' => $this->min, 'maxLength' => $this->max], static fn(?int $value): bool => $value !== null);
    }
}

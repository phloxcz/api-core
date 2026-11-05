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
 * A string matching a regular expression, written without delimiters. As in JSON
 * Schema, it isn't anchored: add ^ and $ to match the whole value.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Pattern implements Constraint
{
    private readonly string $regex;

    public function __construct(public readonly string $pattern)
    {
        $this->regex = '#' . str_replace('#', '\#', $pattern) . '#Du';

        if (@preg_match($this->regex, '') === false) {
            throw new \InvalidArgumentException("Invalid regular expression '{$pattern}': " . preg_last_error_msg() . '.');
        }
    }

    public function validate(mixed $value): ?string
    {
        return is_string($value) && preg_match($this->regex, $value) !== 1
            ? 'has an invalid format.'
            : null;
    }

    public function toSchema(): array
    {
        return ['pattern' => $this->pattern];
    }
}

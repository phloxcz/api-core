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
use Nette\Utils\Validators;

/**
 * A string in one of the formats of JSON Schema: email, uri, uuid, date, date-time,
 * ipv4 or ipv6.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Format implements Constraint
{
    private const Formats = ['email', 'uri', 'uuid', 'date', 'date-time', 'ipv4', 'ipv6'];

    public function __construct(public readonly string $format)
    {
        if (!in_array($format, self::Formats, true)) {
            throw new \InvalidArgumentException("Unknown format '{$format}'. Known ones: " . implode(', ', self::Formats) . '.');
        }
    }

    public function validate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $valid = match ($this->format) {
            'email' => Validators::isEmail($value),
            'uri' => Validators::isUri($value),
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value) === 1,
            'date' => self::isDate($value),
            'date-time' => preg_match('/^(\d{4}-\d{2}-\d{2})[Tt]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|[+-]\d{2}:\d{2})$/D', $value, $matches) === 1
                && self::isDate($matches[1]),
            'ipv4' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            default => false,
        };

        return $valid ? null : "must be a valid {$this->format}.";
    }

    public function toSchema(): array
    {
        return ['format' => $this->format];
    }

    private static function isDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $matches) === 1
            && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}

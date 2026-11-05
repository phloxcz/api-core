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

use Phlox\ApiCore\Types\DtoMeta;

/**
 * Prepares what a controller returned for JSON: DTOs, wherever they are, become
 * their public properties, and dates inside them ISO 8601 strings.
 *
 * Other values are left alone, so a DTO changes nothing for a controller that
 * doesn't use it.
 */
final class DtoSerializer
{
    public static function serialize(mixed $value, bool $inDto = false): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_array($item) || is_object($item)) {
                    $value[$key] = self::serialize($item, $inDto);
                }
            }
            return $value;
        }

        if (!is_object($value)) {
            return $value;
        }

        if ($inDto && $value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (DtoMeta::isDto($value::class)) {
            $properties = self::serialize(get_object_vars($value), true);
            return $properties === [] ? new \stdClass() : $properties; // {} rather than []
        }

        return $value;
    }
}

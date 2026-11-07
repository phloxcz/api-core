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
use Phlox\ApiCore\ApiError;

/**
 * The HTTP status and the message of a case of an ApiError enum.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class ErrorCase
{
    public function __construct(
        public readonly int $httpCode,
        public readonly string $message,
    ) {
    }

    /**
     * @throws \LogicException When the case says nothing, or its code isn't an int
     */
    public static function of(ApiError $error): self
    {
        $name = $error::class . '::' . $error->name;
        if (!is_int($error->value)) {
            throw new \LogicException("{$name}: an ApiError has to be backed by int, the code clients get.");
        }

        $attributes = (new \ReflectionEnumBackedCase($error::class, $error->name))->getAttributes(self::class);

        return $attributes === []
            ? throw new \LogicException("{$name} has no #[ErrorCase(httpCode, message)].")
            : $attributes[0]->newInstance();
    }
}

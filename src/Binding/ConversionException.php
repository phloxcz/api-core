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
 * Values that couldn't be converted, all of them, so that a client can fix them at once.
 */
final class ConversionException extends \Exception
{
    /**
     * @param array<string, string> $errors Path such as "address.city" or "" for the value itself => what's wrong
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    public static function at(string $path, string $error): self
    {
        return new self([$path => $error]);
    }
}

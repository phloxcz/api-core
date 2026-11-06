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

namespace Phlox\ApiCore;

/**
 * What a client sent of a DTO, checked by the DTO's rules, for a parameter with #[PatchOf].
 * A field left out and a field sent as null are two different things here.
 */
final readonly class Patch
{
    /**
     * @param array<string, mixed> $values Converted values of the fields that were sent
     */
    public function __construct(private array $values = [])
    {
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->has($field) ? $this->values[$field] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * Sets what was sent on properties of the same name.
     *
     * @template T of object
     * @param T $target
     * @return T
     */
    public function applyTo(object $target): object
    {
        foreach ($this->values as $field => $value) {
            $target->$field = $value;
        }

        return $target;
    }
}

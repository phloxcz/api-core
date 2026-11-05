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

namespace Phlox\ApiCore\Types;

/**
 * What a value of a parameter, property or return value looks like, read from
 * native types and docblocks. Conversion of incoming values and the generated
 * documentation both work from it, so they can't disagree.
 */
final readonly class Type implements \Stringable
{
    public const Mixed = 'mixed';
    public const Int = 'int';
    public const Float = 'float';
    public const String = 'string';
    public const Bool = 'bool';
    public const Array = 'array';
    public const Object = 'object';
    public const Void = 'void';
    public const ClassName = 'class';
    public const List = 'list';
    public const Map = 'map';
    public const Shape = 'shape';
    public const Union = 'union';

    /**
     * @param string $kind One of the constants
     * @param string|null $class For ClassName
     * @param Type|null $items For List and Map
     * @param array<string, array{type: Type, optional: bool}> $shape For Shape
     * @param list<Type> $union For Union, without null
     */
    public function __construct(
        public string $kind,
        public bool $nullable = false,
        public ?string $class = null,
        public ?Type $items = null,
        public array $shape = [],
        public array $union = [],
    ) {
    }

    public function withNullable(bool $nullable = true): self
    {
        return new self($this->kind, $nullable, $this->class, $this->items, $this->shape, $this->union);
    }

    /**
     * Whether a value of this type can hold a DTO, however deep.
     */
    public function containsDto(): bool
    {
        return match ($this->kind) {
            self::ClassName => $this->class !== null && DtoMeta::isDto($this->class),
            self::List, self::Map => $this->items?->containsDto() ?? false,
            self::Shape => array_any($this->shape, static fn(array $field): bool => $field['type']->containsDto()),
            self::Union => array_any($this->union, static fn(Type $type): bool => $type->containsDto()),
            default => false,
        };
    }

    public static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * For error messages, which clients get, so without namespaces.
     */
    public function __toString(): string
    {
        $name = match ($this->kind) {
            self::ClassName => self::shortName((string) $this->class),
            self::List => "list<{$this->items}>",
            self::Map => "array<string, {$this->items}>",
            self::Shape => 'array{' . implode(', ', array_keys($this->shape)) . '}',
            self::Union => implode('|', array_map(strval(...), $this->union)),
            default => $this->kind,
        };

        return $this->nullable && $this->kind !== self::Mixed ? ($this->kind === self::Union ? "{$name}|null" : "?{$name}") : $name;
    }
}

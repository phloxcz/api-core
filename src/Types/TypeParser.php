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

use Nette\Utils\Reflection;

/**
 * Reads a PHPDoc type such as `list<Address>`, `array<string, int>`, `?Foo[]`,
 * `array{id: int, name?: string}` or `int|string|null`.
 *
 * A type it doesn't understand gives null, and the native type applies: a sloppy
 * docblock must not be able to break a running application.
 */
final class TypeParser
{
    private const Scalars = [
        'int' => Type::Int, 'integer' => Type::Int, 'positive-int' => Type::Int, 'negative-int' => Type::Int,
        'non-negative-int' => Type::Int, 'non-positive-int' => Type::Int,
        'float' => Type::Float, 'double' => Type::Float,
        'string' => Type::String, 'non-empty-string' => Type::String, 'numeric-string' => Type::String,
        'class-string' => Type::String, 'lowercase-string' => Type::String,
        'bool' => Type::Bool, 'boolean' => Type::Bool, 'true' => Type::Bool, 'false' => Type::Bool,
        'object' => Type::Object, 'void' => Type::Void, 'never' => Type::Void,
        'mixed' => Type::Mixed, 'scalar' => Type::Mixed, 'array-key' => Type::Mixed,
    ];

    /** @var list<string> */
    private array $tokens = [];

    private int $position = 0;

    /**
     * @param \ReflectionClass<object> $scope Resolves short class names and `self`
     */
    private function __construct(private readonly \ReflectionClass $scope)
    {
    }

    /**
     * @param \ReflectionClass<object> $scope
     */
    public static function parse(string $text, \ReflectionClass $scope): ?Type
    {
        $parser = new self($scope);

        if (preg_match_all('/\$this|\\\\?[A-Za-z_][\w\\\\-]*|\[\]|\'[^\']*\'|"[^"]*"|-?\d+(?:\.\d+)?|\S/', $text, $matches) === false) {
            return null;
        }
        $parser->tokens = $matches[0];

        try {
            $type = $parser->parseUnion();
        } catch (\UnexpectedValueException) {
            return null;
        }

        return $parser->position === count($parser->tokens) ? $type : null;
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function take(string $expected): bool
    {
        if ($this->peek() === $expected) {
            $this->position++;
            return true;
        }

        return false;
    }

    private function expect(string $expected): void
    {
        $this->take($expected) || throw new \UnexpectedValueException("Expected '{$expected}'.");
    }

    private function parseUnion(): Type
    {
        $members = [$this->parsePostfix()];
        while ($this->take('|')) {
            $members[] = $this->parsePostfix();
        }

        if ($this->peek() === '&') {
            throw new \UnexpectedValueException('Intersections are not supported.');
        }

        $nullable = false;
        $flat = [];
        foreach ($members as $member) {
            $nullable = $nullable || $member->nullable;
            if ($member->kind === Type::Union) {
                array_push($flat, ...$member->union);
            } elseif ($member->kind !== Type::Void) {
                $flat[] = $member->withNullable(false);
            }
        }

        return match (count($flat)) {
            0 => new Type(Type::Void, $nullable),
            1 => $flat[0]->withNullable($nullable),
            default => new Type(Type::Union, $nullable, union: $flat),
        };
    }

    private function parsePostfix(): Type
    {
        if ($this->take('?')) {
            return $this->parsePostfix()->withNullable();
        }

        $type = $this->parseAtom();
        while ($this->take('[]')) {
            $type = new Type(Type::List, items: $type);
        }

        return $type;
    }

    private function parseAtom(): Type
    {
        if ($this->take('(')) {
            $type = $this->parseUnion();
            $this->expect(')');
            return $type;
        }

        $token = $this->peek() ?? throw new \UnexpectedValueException('Unexpected end.');
        $this->position++;

        if ($token === 'null') {
            return new Type(Type::Void, nullable: true);
        }
        if ($token[0] === '"' || $token[0] === "'") {
            return new Type(Type::String);
        }
        if (is_numeric($token)) {
            return new Type(str_contains($token, '.') ? Type::Float : Type::Int);
        }
        if (preg_match('/^(?:\$this|\\\\?[A-Za-z_][\w\\\\-]*)$/D', $token) !== 1) {
            throw new \UnexpectedValueException("Unexpected '{$token}'.");
        }

        $lower = strtolower($token);

        if (isset(self::Scalars[$lower])) {
            $this->skipGenerics();
            return new Type(self::Scalars[$lower]);
        }

        return match ($lower) {
            'array', 'iterable', 'list', 'non-empty-list', 'non-empty-array' => $this->parseArray($lower),
            'self', 'static', '$this' => new Type(Type::ClassName, class: $this->scope->getName()),
            default => $this->parseClass($token),
        };
    }

    private function parseArray(string $name): Type
    {
        if ($name === 'array' && $this->take('{')) {
            return $this->parseShape();
        }

        if (!$this->take('<')) {
            return new Type(Type::Array);
        }

        $first = $this->parseUnion();
        $second = $this->take(',') ? $this->parseUnion() : null;
        $this->expect('>');

        if ($second === null) {
            return new Type(Type::List, items: $first);
        }

        return new Type($first->kind === Type::Int ? Type::List : Type::Map, items: $second);
    }

    private function parseShape(): Type
    {
        $fields = [];

        while (!$this->take('}')) {
            $name = $this->peek() ?? throw new \UnexpectedValueException('Unexpected end of a shape.');
            $this->position++;
            $optional = $this->take('?');
            $this->expect(':');
            $fields[trim($name, '\'"')] = ['type' => $this->parseUnion(), 'optional' => $optional];
            $this->take(',');
        }

        return new Type(Type::Shape, shape: $fields);
    }

    private function parseClass(string $name): Type
    {
        $this->skipGenerics();

        try {
            return new Type(Type::ClassName, class: Reflection::expandClassName($name, $this->scope));
        } catch (\Throwable) {
            throw new \UnexpectedValueException("Unknown class '{$name}'.");
        }
    }

    private function skipGenerics(): void
    {
        if (!$this->take('<')) {
            return;
        }

        $depth = 1;
        while ($depth > 0) {
            $token = $this->peek() ?? throw new \UnexpectedValueException('Unexpected end of generics.');
            $this->position++;
            $depth += match ($token) {
                '<' => 1,
                '>' => -1,
                default => 0,
            };
        }
    }
}

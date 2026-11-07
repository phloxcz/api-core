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

namespace Phlox\ApiCore\Documentation;

use Phlox\ApiCore\Attributes\Throws;
use Phlox\ApiCore\Types\DocComment;
use Phlox\ApiCore\Types\DtoMeta;
use Phlox\ApiCore\Types\Type;
use Phlox\ApiCore\Types\TypeReader;

/**
 * What the documentation of both transports needs to know about a controller method.
 *
 * @phpstan-type ParameterInfo array{name: string, position: int, required: bool, type: Type, schema: array<string, mixed>|\stdClass, description: string}
 */
final class MethodReader
{
    private const RemovedParameter = 'Phlox\ApiCore\Attributes\Parameter';

    public readonly DocComment $doc;
    public readonly bool $deprecated;

    /**
     * @param \ReflectionClass<object> $class
     */
    public function __construct(
        private readonly \ReflectionClass $class,
        private readonly \ReflectionMethod $method,
        private readonly SchemaBuilder $builder,
    ) {
        $this->doc = new DocComment($method->getDocComment());
        $this->deprecated = $this->doc->deprecated || $method->getAttributes(\Deprecated::class) !== [];
    }

    /**
     * @return array<string, ParameterInfo>
     */
    public function getParameters(): array
    {
        // Without its class, PHP would skip the attribute, and the documentation would change unnoticed
        foreach ($this->method->getAttributes() as $attribute) {
            if ($attribute->getName() === self::RemovedParameter) {
                throw new \InvalidArgumentException('#[Parameter] is gone. Describe a parameter by its type, `@param list<Foo> $name` where the type says too little, or #[Schema] on the parameter.');
            }
        }

        $overrides = [];

        $result = [];
        foreach ($this->method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = TypeReader::ofParameter($parameter);
            $description = $this->doc->getParam($name)['description'] ?? '';
            foreach ($this->getDescribers($parameter) as $describer) {
                $description = trim($description . ' ' . $describer->describeParameter());
            }

            $additions = DtoMeta::readSchema($parameter);
            if ($parameter->isDefaultValueAvailable() && !isset($additions['default'])) {
                $default = SchemaBuilder::encodeDefault($parameter->getDefaultValue());
                if ($default !== null) {
                    $additions['default'] = $default;
                }
            }

            foreach ($this->getDescribers($parameter) as $describer) {
                $overrides[$name] ??= $describer->describeParameterSchema($this->builder);
            }

            $schema = isset($overrides[$name])
                ? SchemaBuilder::extend($overrides[$name], $additions)
                : $this->builder->buildUsage($type, DtoMeta::readConstraints($parameter), $additions);

            $result[$name] = [
                'name' => $name,
                'position' => $parameter->getPosition(),
                'required' => !$parameter->isDefaultValueAvailable() && !$parameter->allowsNull() && !$parameter->isVariadic(),
                'type' => $type,
                'schema' => $schema,
                'description' => $description,
            ];
        }

        return $result;
    }

    /**
     * The native return type. A docblock isn't read, so a response is described
     * in one place: by an attribute, where the type says too little.
     */
    public function getReturnType(): Type
    {
        return TypeReader::native($this->method->getReturnType(), $this->method->getDeclaringClass());
    }

    /**
     * `Class::method()`, for messages.
     */
    public function getName(): string
    {
        return $this->class->getName() . '::' . $this->method->getName() . '()';
    }

    /**
     * Attributes of the method, refusing the array notation of older versions
     * with a message saying what replaces it.
     *
     * @template T of object
     * @param class-string<T> $name
     * @return list<T>
     * @throws \InvalidArgumentException
     */
    public function getAttributes(string $name): array
    {
        $result = [];
        foreach ($this->method->getAttributes($name) as $attribute) {
            foreach ($attribute->getArguments() as $argument) {
                if (is_array($argument)) {
                    throw new \InvalidArgumentException(self::explainArray($argument));
                }
            }
            $result[] = $attribute->newInstance();
        }

        return $result;
    }

    /**
     * @param array<mixed> $argument
     */
    private static function explainArray(array $argument): string
    {
        $hint = match (array_is_list($argument) && count($argument) === 2 ? $argument[0] : null) {
            'object' => "new Shape(['id' => 'int', 'note?' => '?string'])",
            'array' => 'new ListOf(Foo::class)',
            '@pager' => 'new Paged(Foo::class)',
            default => "new JsonSchema([...]) for plain JSON Schema, or new Shape([...]), new ListOf(...) and new Paged(...)",
        };

        return "The array notation is gone, use {$hint}.";
    }

    /**
     * What an attribute of a parameter says about the response, for a return type
     * which says nothing about it.
     *
     * @return array<string, mixed>|\stdClass|null
     */
    public function getDescribedResponse(): array|\stdClass|null
    {
        foreach ($this->method->getParameters() as $parameter) {
            foreach ($this->getDescribers($parameter) as $describer) {
                $schema = $describer->describeResponse($this->builder);
                if ($schema !== null) {
                    return $schema;
                }
            }
        }

        return null;
    }

    /**
     * @return list<DescribesMethod>
     */
    private function getDescribers(\ReflectionParameter $parameter): array
    {
        return array_map(
            static fn(\ReflectionAttribute $attribute): DescribesMethod => $attribute->newInstance(),
            $parameter->getAttributes(DescribesMethod::class, \ReflectionAttribute::IS_INSTANCEOF),
        );
    }

    /**
     * @return list<Throws> From the method, then the class and its parents
     */
    public function getThrows(): array
    {
        $attributes = $this->method->getAttributes(Throws::class);
        for ($current = $this->class; $current !== false; $current = $current->getParentClass()) {
            array_push($attributes, ...$current->getAttributes(Throws::class));
        }

        return array_map(static fn(\ReflectionAttribute $attribute): Throws => $attribute->newInstance(), $attributes);
    }
}

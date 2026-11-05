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

use Phlox\ApiCore\Attributes\Parameter;
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
        $overrides = [];
        foreach ($this->method->getAttributes(Parameter::class) as $attribute) {
            $parameter = $attribute->newInstance();
            if ($parameter->definition !== null) {
                $overrides[$parameter->name] = ContentDefinition::toSchema($parameter->definition, $this->builder, $this->class);
            }
        }

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

    public function getReturnType(): Type
    {
        return TypeReader::ofReturn($this->method);
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

    public function getReturnDescription(): string
    {
        return $this->doc->getReturn()['description'] ?? '';
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

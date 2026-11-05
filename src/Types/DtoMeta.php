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

use Phlox\ApiCore\Attributes\Constraints\Constraint;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Attributes\Schema;

/**
 * The fields of a class marked #[Dto]: parameters of its constructor, then its
 * other public properties.
 */
final class DtoMeta
{
    /** @var array<string, bool> */
    private static array $isDto = [];

    /** @var array<string, array<string, DtoField>> */
    private static array $fields = [];

    public static function isDto(string $class): bool
    {
        if (isset(self::$isDto[$class])) {
            return self::$isDto[$class];
        }

        $found = false;
        if (class_exists($class)) {
            for ($current = new \ReflectionClass($class); $current !== false; $current = $current->getParentClass()) {
                if ($current->getAttributes(Dto::class, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
                    $found = true;
                    break;
                }
            }
        }

        return self::$isDto[$class] = $found;
    }

    /**
     * @param class-string $class
     * @return array<string, DtoField>
     */
    public static function getFields(string $class): array
    {
        if (isset(self::$fields[$class])) {
            return self::$fields[$class];
        }

        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new \LogicException("DTO {$class} can't be instantiated: it must be a concrete class with a public constructor.");
        }

        $fields = [];

        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $fields[$parameter->getName()] = self::readParameter($reflection, $constructor, $parameter);
            }
        }

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && !isset($fields[$property->getName()])) {
                $fields[$property->getName()] = self::readProperty($property);
            }
        }

        return self::$fields[$class] = $fields;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private static function readParameter(\ReflectionClass $class, \ReflectionMethod $constructor, \ReflectionParameter $parameter): DtoField
    {
        $name = $parameter->getName();
        $hasDefault = $parameter->isDefaultValueAvailable();
        $property = $parameter->isPromoted() && $class->hasProperty($name) ? $class->getProperty($name) : null;

        // A description may sit in @param of the constructor or above the promoted property
        $description = (new DocComment($constructor->getDocComment()))->getParam($name)['description'] ?? '';
        if ($description === '' && $property !== null) {
            $doc = new DocComment($property->getDocComment());
            $description = $doc->summary !== '' ? $doc->summary : ($doc->getVar()['description'] ?? '');
        }

        return new DtoField(
            $name,
            TypeReader::ofParameter($parameter),
            !$hasDefault && !$parameter->allowsNull(),
            $hasDefault,
            $hasDefault ? $parameter->getDefaultValue() : null,
            true,
            self::readConstraints($parameter),
            self::readSchema($parameter),
            $description,
        );
    }

    private static function readProperty(\ReflectionProperty $property): DtoField
    {
        $doc = new DocComment($property->getDocComment());
        $type = TypeReader::ofProperty($property);
        $hasDefault = $property->hasDefaultValue() && ($property->getDefaultValue() !== null || $property->getType() === null || $type->nullable);

        return new DtoField(
            $property->getName(),
            $type,
            !$hasDefault && !$type->nullable,
            $hasDefault,
            $hasDefault ? $property->getDefaultValue() : null,
            false,
            self::readConstraints($property),
            self::readSchema($property),
            $doc->summary !== '' ? $doc->summary : ($doc->getVar()['description'] ?? ''),
        );
    }

    /**
     * @return list<Constraint>
     */
    public static function readConstraints(\ReflectionParameter|\ReflectionProperty $reflection): array
    {
        return array_map(
            static fn(\ReflectionAttribute $attribute): Constraint => $attribute->newInstance(),
            $reflection->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function readSchema(\ReflectionParameter|\ReflectionProperty $reflection): array
    {
        $attributes = $reflection->getAttributes(Schema::class);

        return $attributes === [] ? [] : $attributes[0]->newInstance()->schema;
    }
}

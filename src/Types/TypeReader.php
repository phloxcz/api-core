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

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Gets the Type of a reflected parameter or property, and the native type of anything.
 *
 * A docblock only refines a native type too vague to say anything (`array`,
 * `mixed`, none at all). Where a native type is precise, it wins over a docblock
 * which may have gone stale.
 */
final class TypeReader
{
    /**
     * @param \ReflectionClass<object>|null $scope For `self`
     */
    public static function native(?ReflectionType $type, ?\ReflectionClass $scope = null): Type
    {
        if ($type === null) {
            return new Type(Type::Mixed, nullable: true);
        }

        if ($type instanceof ReflectionUnionType) {
            $members = [];
            foreach ($type->getTypes() as $member) {
                $read = self::native($member, $scope);
                if ($read->kind !== Type::Void) {
                    $members[] = $read;
                }
            }

            return count($members) === 1
                ? $members[0]->withNullable($type->allowsNull())
                : new Type(Type::Union, $type->allowsNull(), union: $members);
        }

        if ($type instanceof ReflectionIntersectionType) {
            return new Type(Type::ClassName, class: (string) $type); // can't be constructed, so it gets refused
        }

        assert($type instanceof ReflectionNamedType);
        $name = $type->getName();

        $kind = match (strtolower($name)) {
            'int' => Type::Int,
            'float' => Type::Float,
            'string' => Type::String,
            'bool', 'true', 'false' => Type::Bool,
            'array', 'iterable' => Type::Array,
            'object' => Type::Object,
            'mixed' => Type::Mixed,
            'void', 'never', 'null' => Type::Void,
            default => null,
        };

        if ($kind !== null) {
            return new Type($kind, $type->allowsNull());
        }

        if (($name === 'self' || $name === 'static') && $scope !== null) {
            $name = $scope->getName();
        }

        return new Type(Type::ClassName, $type->allowsNull(), $name);
    }

    public static function ofParameter(\ReflectionParameter $parameter): Type
    {
        $scope = $parameter->getDeclaringClass();
        $native = self::native($parameter->getType(), $scope);
        if ($scope === null || !self::isVague($native) || $parameter->isVariadic()) {
            return $native;
        }

        $function = $parameter->getDeclaringFunction();
        $doc = (new DocComment($function->getDocComment()))->getParam($parameter->getName());

        // A promoted property may carry the description of the parameter
        if ($doc === null && $parameter->isPromoted() && $scope->hasProperty($parameter->getName())) {
            $doc = (new DocComment($scope->getProperty($parameter->getName())->getDocComment()))->getVar();
        }

        return self::refine($native, $doc['type'] ?? null, $scope);
    }

    public static function ofProperty(\ReflectionProperty $property): Type
    {
        $scope = $property->getDeclaringClass();
        $native = self::native($property->getType(), $scope);

        return self::isVague($native)
            ? self::refine($native, (new DocComment($property->getDocComment()))->getVar()['type'] ?? null, $scope)
            : $native;
    }

    private static function isVague(Type $type): bool
    {
        return in_array($type->kind, [Type::Mixed, Type::Array, Type::Object, Type::Union], true);
    }

    /**
     * @param \ReflectionClass<object> $scope
     */
    private static function refine(Type $native, ?string $doc, \ReflectionClass $scope): Type
    {
        $refined = $doc === null ? null : TypeParser::parse($doc, $scope);
        if ($refined === null) {
            return $native;
        }

        // With no native type, null is up to the docblock; otherwise the signature decides
        return $native->kind === Type::Mixed ? $refined : $refined->withNullable($native->nullable);
    }
}

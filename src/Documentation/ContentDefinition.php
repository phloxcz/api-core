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

use Phlox\ApiCore\Types\Type;
use Phlox\ApiCore\Types\TypeParser;

/**
 * Reads the content of #[Response] and #[Parameter]:
 *
 * - a type as in a docblock: `list<UserDto>`, `UserDto`, `int`
 * - JSON Schema: `['type' => 'string', 'format' => 'binary']`
 * - aliases `@binary` and `@error`
 * - the older array notation `['object', ['id' => 'int']]`, `['array', 'string']`
 *   and `['@pager', 'User']`, which is deprecated
 */
final class ContentDefinition
{
    /**
     * @param string|array<int|string, mixed> $definition
     * @param \ReflectionClass<object> $scope Resolves short class names
     * @return array<string, mixed>|\stdClass
     */
    public static function toSchema(string|array $definition, SchemaBuilder $builder, \ReflectionClass $scope): array|\stdClass
    {
        if (is_string($definition)) {
            return match ($definition) {
                '@binary' => ['type' => 'string', 'format' => 'binary'],
                '@error' => ['$ref' => '#/components/schemas/Error'],
                // Foo::class has no leading slash, and would be read as relative to the namespace of $scope
                default => $builder->build(
                    class_exists($definition) || enum_exists($definition)
                        ? new Type(Type::ClassName, class: $definition)
                        : TypeParser::parse($definition, $scope) ?? new Type(Type::Mixed),
                ),
            };
        }

        if (!array_is_list($definition) || count($definition) !== 2) {
            $schema = [];
            foreach ($definition as $keyword => $value) {
                $schema[(string) $keyword] = $value;
            }
            return $schema;
        }

        $content = $definition[1];

        return match ($definition[0]) {
            'array' => [
                'type' => 'array',
                'items' => self::toSchema(self::toDefinition($content), $builder, $scope),
            ],
            'object' => [
                'type' => 'object',
                'properties' => array_map(
                    static fn(mixed $property): array|\stdClass => self::toSchema(self::toDefinition($property), $builder, $scope),
                    is_array($content) ? $content : [],
                ) ?: new \stdClass(),
            ],
            '@pager' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => self::toSchema(self::toDefinition($content), $builder, $scope)],
                    'total' => ['type' => 'integer'],
                    'page' => ['type' => 'integer'],
                    'pageSize' => ['type' => 'integer'],
                    'from' => ['type' => 'integer'],
                    'to' => ['type' => 'integer'],
                ],
            ],
            default => new \stdClass(),
        };
    }

    /**
     * @return string|array<int|string, mixed>
     */
    private static function toDefinition(mixed $value): string|array
    {
        return is_string($value) || is_array($value) ? $value : 'mixed';
    }
}

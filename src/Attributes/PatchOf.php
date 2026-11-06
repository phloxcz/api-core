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
use Phlox\ApiCore\Documentation\DescribesMethod;
use Phlox\ApiCore\Documentation\SchemaBuilder;
use Phlox\ApiCore\Types\Type;

/**
 * Says which DTO a Patch parameter takes its fields and rules from. No field is
 * required, and a value that is sent has to meet the same rules as in the DTO.
 *
 *     public function patch(int $id, #[FromBody, PatchOf(SaveType::class)] Patch $changes): array
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class PatchOf implements DescribesMethod
{
    /**
     * @param class-string $class A class with #[Dto]
     */
    public function __construct(public readonly string $class)
    {
    }

    public function describeParameter(): string
    {
        return '';
    }

    public function describeParameterSchema(SchemaBuilder $builder): array
    {
        $full = (array) $builder->build(new Type(Type::ClassName, class: $this->class));
        $name = substr(strrchr((string) ($full['$ref'] ?? ''), '/') ?: '/' . Type::shortName($this->class), 1);

        return $builder->define("{$name}_Patch", static function () use ($builder, $name): array {
            $schema = (array) ($builder->getSchemas()[$name] ?? ['type' => 'object']);
            unset($schema['required']);

            return $schema;
        });
    }

    public function describeResponse(SchemaBuilder $builder): null
    {
        return null;
    }
}

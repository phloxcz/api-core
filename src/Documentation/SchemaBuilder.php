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

use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\Attributes\Constraints\Constraint;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Attributes\Schema;
use Phlox\ApiCore\Types\DocComment;
use Phlox\ApiCore\Types\DtoField;
use Phlox\ApiCore\Types\DtoMeta;
use Phlox\ApiCore\Types\Type;

/**
 * Turns Types into JSON Schema. DTOs and enums end up as shared schemas under
 * #/components/schemas, which is where both OpenAPI and OpenRPC keep them.
 */
final class SchemaBuilder
{
    private const Prefix = '#/components/schemas/';

    /** @var array<string, array<string, mixed>|\stdClass> Schema name => schema */
    private array $schemas = [];

    /** @var array<string, string> Class => schema name */
    private array $names = [];

    /** @var array<class-string, true> */
    private array $classes = [];

    /** @var list<callable(string, self): (array<string, mixed>|\stdClass|null)> */
    private static array $classDescribers = [];

    /**
     * Lets a package describe classes this one can't know, such as entities.
     *
     * @param callable(string, self): (array<string, mixed>|\stdClass|null) $describer Gets the class, null means not mine
     */
    public static function addClassDescriber(callable $describer): void
    {
        self::$classDescribers[] = $describer;
    }

    /**
     * @param list<string> $reservedNames Schemas the document defines itself
     */
    public function __construct(private readonly array $reservedNames = [])
    {
    }

    /**
     * @return array<string, mixed>|\stdClass An empty schema must stay an object in JSON
     */
    public function build(Type $type): array|\stdClass
    {
        $schema = match ($type->kind) {
            Type::Int => ['type' => 'integer'],
            Type::Float => ['type' => 'number'],
            Type::String => ['type' => 'string'],
            Type::Bool => ['type' => 'boolean'],
            Type::Object => ['type' => 'object'],
            Type::Array => ['type' => ['array', 'object']],
            Type::Void => ['type' => 'null'],
            Type::List => ['type' => 'array', 'items' => $this->build($type->items ?? new Type(Type::Mixed))],
            Type::Map => ['type' => 'object', 'additionalProperties' => $this->build($type->items ?? new Type(Type::Mixed))],
            Type::Shape => $this->buildShape($type),
            Type::Union => ['anyOf' => array_map($this->build(...), $type->union)],
            Type::ClassName => $this->buildClass((string) $type->class),
            default => new \stdClass(),
        };

        return $type->nullable && $type->kind !== Type::Mixed && $type->kind !== Type::Void
            ? self::makeNullable($schema)
            : $schema;
    }

    /**
     * Adds what's known about one particular place the type is used at.
     *
     * @param list<Constraint> $constraints
     * @param array<string, mixed> $additions From #[Schema]
     * @return array<string, mixed>|\stdClass
     */
    public function buildUsage(Type $type, array $constraints = [], array $additions = [], string $description = ''): array|\stdClass
    {
        $extra = [];
        if ($description !== '') {
            $extra['description'] = $description;
        }
        foreach ($constraints as $constraint) {
            $extra += $constraint->toSchema();
        }
        $extra = $additions + $extra;

        return self::extend($this->build($type), $extra);
    }

    /**
     * @param array<string, mixed>|\stdClass $schema
     * @param array<string, mixed> $extra
     * @return array<string, mixed>|\stdClass
     */
    public static function extend(array|\stdClass $schema, array $extra): array|\stdClass
    {
        if ($extra === []) {
            return $schema;
        }
        if ($schema instanceof \stdClass) {
            return $extra;
        }

        // Keywords next to a $ref are ignored by older JSON Schema drafts, which OpenRPC is based on
        return isset($schema['$ref']) ? ['allOf' => [$schema]] + $extra : $extra + $schema;
    }

    /**
     * @return array<string, array<string, mixed>|\stdClass>
     */
    public function getSchemas(): array
    {
        ksort($this->schemas);

        return $this->schemas;
    }

    /**
     * Keeps a schema under a name, unless it's there already, and refers to it.
     * Referring from within $build is fine, so a schema can hold itself.
     *
     * @param callable(): (array<string, mixed>|\stdClass) $build
     * @return array{'$ref': string}
     */
    public function define(string $name, callable $build): array
    {
        if (!isset($this->schemas[$name])) {
            $this->schemas[$name] = new \stdClass();
            $this->schemas[$name] = $build();
        }

        return ['$ref' => self::Prefix . $name];
    }

    /**
     * Records a class the documentation depends on.
     *
     * @param class-string $class
     */
    public function addClass(string $class): void
    {
        $this->classes[$class] = true;
    }

    /**
     * Classes the documentation depends on, to recompile the container when they change.
     *
     * @return list<class-string>
     */
    public function getClasses(): array
    {
        return array_keys($this->classes);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShape(Type $type): array
    {
        $properties = [];
        $required = [];

        foreach ($type->shape as $name => $field) {
            $properties[$name] = $this->build($field['type']);
            if (!$field['optional']) {
                $required[] = $name;
            }
        }

        return ['type' => 'object', 'properties' => $properties ?: new \stdClass()] + ($required === [] ? [] : ['required' => $required]);
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private function buildClass(string $class): array|\stdClass
    {
        if (is_a($class, \DateTimeInterface::class, true)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        if (!class_exists($class) && !enum_exists($class)) {
            return ['type' => 'object'];
        }

        $reflection = new \ReflectionClass($class);
        $this->classes[$reflection->getName()] = true;

        if (is_a($class, SelfDescribing::class, true)) {
            return $class::describeSchema($this);
        }

        foreach (self::$classDescribers as $describer) {
            $schema = $describer($class, $this);
            if ($schema !== null) {
                return $schema;
            }
        }

        if ($reflection->isEnum()) {
            return $this->reference($reflection, fn(): array => $this->buildEnum($reflection));
        }

        if (DtoMeta::isDto($class)) {
            return $this->reference($reflection, fn(): array => $this->buildDto($reflection));
        }

        $attributes = $reflection->getAttributes(Schema::class);
        if ($attributes !== []) {
            return $attributes[0]->newInstance()->schema;
        }

        // It decides for itself what it's built from, which can be anything
        return is_a($class, ApiConstructible::class, true) ? new \stdClass() : ['type' => 'object'];
    }

    /**
     * Keeps the schema of a class under its name, and refers to it.
     *
     * @param \ReflectionClass<object> $class
     * @param callable(): array<string, mixed> $build
     * @return array{'$ref': string}
     */
    public function reference(\ReflectionClass $class, callable $build): array
    {
        $name = $class->getName();

        if (!isset($this->names[$name])) {
            $this->names[$name] = $this->createName($class);
            // Taken before it's built, because a DTO may refer to itself
            $this->schemas[$this->names[$name]] = new \stdClass();
            $this->schemas[$this->names[$name]] = $build();
        }

        return ['$ref' => self::Prefix . $this->names[$name]];
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function createName(\ReflectionClass $class): string
    {
        $short = $class->getShortName();

        return isset($this->schemas[$short]) || in_array($short, $this->reservedNames, true)
            ? str_replace('\\', '.', $class->getName())
            : $short;
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return array<string, mixed>
     */
    private function buildEnum(\ReflectionClass $class): array
    {
        $name = $class->getName();
        if (!enum_exists($name)) {
            throw new \LogicException("{$name} is not an enum.");
        }

        $enum = new \ReflectionEnum($name);
        $values = array_map(
            static fn(\ReflectionEnumUnitCase $case): mixed => $case instanceof \ReflectionEnumBackedCase ? $case->getBackingValue() : $case->getName(),
            $enum->getCases(),
        );

        return ['type' => (string) $enum->getBackingType() === 'int' ? 'integer' : 'string', 'enum' => $values]
            + self::describe($class);
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return array<string, mixed>
     */
    private function buildDto(\ReflectionClass $class): array
    {
        $properties = [];
        $required = [];

        foreach (DtoMeta::getFields($class->getName()) as $name => $field) {
            $properties[$name] = $this->buildField($field);
            if ($field->required) {
                $required[] = $name;
            }
        }

        $dto = $class->getAttributes(Dto::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        $schema = ['type' => 'object', 'properties' => $properties ?: new \stdClass()];
        if ($required !== [] && !$dto?->newInstance()->isPartial()) {
            $schema['required'] = $required;
        }

        $attributes = $class->getAttributes(Schema::class);

        return ($attributes === [] ? [] : $attributes[0]->newInstance()->schema) + $schema + self::describe($class);
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private function buildField(DtoField $field): array|\stdClass
    {
        $additions = $field->schema;
        if ($field->hasDefault && !isset($additions['default'])) {
            $default = self::encodeDefault($field->default);
            if ($default !== null || $field->default === null) {
                $additions['default'] = $default;
            }
        }

        return $this->buildUsage($field->type, $field->constraints, $additions, $field->description);
    }

    /**
     * A default that has a JSON form; null otherwise.
     */
    public static function encodeDefault(mixed $default): mixed
    {
        if ($default instanceof \BackedEnum) {
            return $default->value;
        }

        return is_scalar($default) || is_array($default) ? $default : null;
    }

    /**
     * @param array<string, mixed>|\stdClass $schema
     * @return array<string, mixed>
     */
    private static function makeNullable(array|\stdClass $schema): array
    {
        if (!$schema instanceof \stdClass && isset($schema['type']) && !isset($schema['enum'])) {
            $types = (array) $schema['type'];
            if (!in_array('null', $types, true)) {
                $types[] = 'null';
            }
            $schema['type'] = $types;
            return $schema;
        }

        return ['anyOf' => [$schema, ['type' => 'null']]];
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return array<string, string>
     */
    private static function describe(\ReflectionClass $class): array
    {
        $doc = new DocComment($class->getDocComment());
        $text = trim($doc->summary . "\n\n" . $doc->description);

        return $text === '' ? [] : ['description' => $text];
    }
}

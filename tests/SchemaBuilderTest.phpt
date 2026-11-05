<?php

/**
 * JSON Schema generated from types, the older notation and the YAML output.
 */

declare(strict_types=1);

use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\Attributes\Constraints\Format;
use Phlox\ApiCore\Attributes\Constraints\Length;
use Phlox\ApiCore\Attributes\Constraints\Range;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Attributes\Parameter;
use Phlox\ApiCore\Attributes\Schema;
use Phlox\ApiCore\Attributes\Throws;
use Phlox\ApiCore\Documentation\ContentDefinition;
use Phlox\ApiCore\Documentation\Documents;
use Phlox\ApiCore\Documentation\DocumentationConfig;
use Phlox\ApiCore\Documentation\MethodReader;
use Phlox\ApiCore\Documentation\SchemaBuilder;
use Phlox\ApiCore\Documentation\Yaml;
use Phlox\ApiCore\Types\Type;
use Phlox\ApiCore\Types\TypeParser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/** Who can do what. */
enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}

enum Level: int
{
    case Low = 1;
}

enum Plain
{
    case One;
}

/**
 * Where someone lives.
 */
#[Dto]
final readonly class Address
{
    public function __construct(public string $city)
    {
    }
}

#[Dto]
#[Schema(['example' => ['name' => 'Ann']])]
final class User
{
    /**
     * @param list<Address> $addresses
     * @param string $note From the constructor
     */
    public function __construct(
        /** The name of the user */
        #[Length(min: 1, max: 50)] public string $name,
        #[Format('email'), Schema(['example' => 'a@b.cz'])] public ?string $email,
        #[Range(min: 18)] public int $age = 18,
        public Role $role = Role::User,
        public ?Address $home = null,
        public array $addresses = [],
        public ?DateTimeImmutable $born = null,
        public string $note = '',
    ) {
    }
}

#[Dto]
final class Node
{
    /** @param list<Node> $children */
    public function __construct(public array $children = [], public ?Node $parent = null)
    {
    }
}

#[Dto]
final class Empty_
{
}

#[Schema(['type' => 'string', 'pattern' => '^[A-Z]{3}$'])]
final class Currency implements ApiConstructible
{
    public static function apiConstruct(mixed $value)
    {
        return new self();
    }
}

final class Money implements ApiConstructible
{
    public static function apiConstruct(mixed $value)
    {
        return new self();
    }
}

final class Service
{
}

#[Throws(500, 'Class-wide')]
final class Controller
{
    /**
     * Summary of it.
     *
     * Longer text.
     *
     * @param int $page Which page
     * @param list<Address> $addresses Where
     * @return list<User> The users
     */
    #[Throws(1001, 'Closed', httpCode: 409)]
    public function documented(#[Range(min: 1)] int $page, array $addresses = [], ?string $note = null, Role $role = Role::User, #[Schema(['example' => 'x'])] string $example = 'x', DateTimeImmutable $when = new DateTimeImmutable()): array
    {
        return [];
    }

    #[Parameter('filter', ['object', ['a' => 'int']])]
    public function overridden(array $filter): void
    {
    }

    /** @deprecated */
    public function deprecatedByDoc(): void
    {
    }

    #[\Deprecated]
    public function deprecatedByAttribute(): void
    {
    }
}

function schemaOf(string $type, ?SchemaBuilder $builder = null): array|stdClass
{
    return ($builder ?? new SchemaBuilder())->build(TypeParser::parse($type, new ReflectionClass(Controller::class)));
}

// --- Plain types

Assert::same(['type' => 'integer'], schemaOf('int'));
Assert::same(['type' => 'number'], schemaOf('float'));
Assert::same(['type' => 'string'], schemaOf('string'));
Assert::same(['type' => 'boolean'], schemaOf('bool'));
Assert::same(['type' => 'object'], schemaOf('object'));
Assert::same(['type' => ['array', 'object']], schemaOf('array'), 'a PHP array can be either');
Assert::same(['type' => 'null'], schemaOf('void'));
Assert::equal(new stdClass(), schemaOf('mixed'), 'anything is an empty schema, which must stay an object');
Assert::same(['type' => ['integer', 'null']], schemaOf('?int'));
Assert::same(['type' => 'array', 'items' => ['type' => 'integer']], schemaOf('list<int>'));
Assert::same(['type' => 'object', 'additionalProperties' => ['type' => 'integer']], schemaOf('array<string, int>'));
Assert::same(['anyOf' => [['type' => 'integer'], ['type' => 'string']]], schemaOf('int|string'));
Assert::same(['anyOf' => [['anyOf' => [['type' => 'integer'], ['type' => 'string']]], ['type' => 'null']]], schemaOf('int|string|null'));
Assert::same(
    ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']], 'required' => ['id']],
    schemaOf('array{id: int, name?: string}'),
);

// --- Classes

Assert::same(['type' => 'string', 'format' => 'date-time'], schemaOf('DateTimeImmutable'));
Assert::same(['type' => 'string', 'format' => 'date-time'], schemaOf('DateTimeInterface'));
Assert::same(['type' => 'object'], schemaOf('Service'), 'an unknown class says little, but breaks nothing');
Assert::same(['type' => 'object'], schemaOf('NoSuchClass'));
Assert::same(['type' => 'string', 'pattern' => '^[A-Z]{3}$'], schemaOf('Currency'), '#[Schema] describes a value object');
Assert::equal(new stdClass(), schemaOf('Money'), 'a value object without it can be built from anything');

// --- Enums

$builder = new SchemaBuilder();
Assert::same(['$ref' => '#/components/schemas/Role'], schemaOf('Role', $builder));
Assert::same(['anyOf' => [['$ref' => '#/components/schemas/Role'], ['type' => 'null']]], schemaOf('?Role', $builder));
schemaOf('Level', $builder);
schemaOf('Plain', $builder);
Assert::same([
    'Level' => ['type' => 'integer', 'enum' => [1]],
    'Plain' => ['type' => 'string', 'enum' => ['One']],
    'Role' => ['type' => 'string', 'enum' => ['admin', 'user'], 'description' => 'Who can do what.'],
], $builder->getSchemas());

// --- DTOs

$builder = new SchemaBuilder();
Assert::same(['$ref' => '#/components/schemas/User'], schemaOf('User', $builder));
$schemas = $builder->getSchemas();
Assert::same(['Address', 'Role', 'User'], array_keys($schemas));
Assert::same('Where someone lives.', $schemas['Address']['description']);

$user = $schemas['User'];
Assert::same(['name'], $user['required'], 'only what has no default and can not be null');
Assert::same(['example' => ['name' => 'Ann']], ['example' => $user['example']]);
Assert::same(['description' => 'The name of the user', 'minLength' => 1, 'maxLength' => 50, 'type' => 'string'], $user['properties']['name']);
Assert::same(['example' => 'a@b.cz', 'format' => 'email', 'type' => ['string', 'null']], $user['properties']['email']);
Assert::same(['default' => 18, 'minimum' => 18, 'type' => 'integer'], $user['properties']['age']);
Assert::same(['allOf' => [['$ref' => '#/components/schemas/Role']], 'default' => 'user'], $user['properties']['role'], 'keywords stay beside a $ref in every draft');
Assert::same(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Address']], array_diff_key($user['properties']['addresses'], ['default' => 1]));
Assert::same([], $user['properties']['addresses']['default']);
Assert::same(['default' => '', 'description' => 'From the constructor', 'type' => 'string'], $user['properties']['note']);

Assert::contains(User::class, $builder->getClasses(), 'documentation depends on it');
Assert::contains(Address::class, $builder->getClasses());
Assert::contains(Role::class, $builder->getClasses());

// A DTO referring to itself ends
$builder = new SchemaBuilder();
schemaOf('Node', $builder);
Assert::same(
    ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Node']],
    array_diff_key($builder->getSchemas()['Node']['properties']['children'], ['default' => 1]),
);

// An empty DTO keeps an object for its properties
Assert::contains('"properties":{}', (function () {
    $builder = new SchemaBuilder();
    schemaOf('Empty_', $builder);
    return json_encode($builder->getSchemas());
})());

// --- Names: the same short name, and a name the document needs itself

$builder = new SchemaBuilder(['Address']);
Assert::same(['$ref' => '#/components/schemas/Address'], schemaOf('Address', $builder), 'a global class has no other name');

eval('namespace Other\Place; #[\Phlox\ApiCore\Attributes\Dto] final class Address {} #[\Phlox\ApiCore\Attributes\Dto] final class Error {}');
$builder = new SchemaBuilder(['Error']);
Assert::same(['$ref' => '#/components/schemas/Address'], schemaOf('Address', $builder));
Assert::same(['$ref' => '#/components/schemas/Other.Place.Address'], $builder->build(new Type(Type::ClassName, class: 'Other\Place\Address')));
Assert::same(['$ref' => '#/components/schemas/Other.Place.Error'], $builder->build(new Type(Type::ClassName, class: 'Other\Place\Error')));

// --- Methods

$builder = new SchemaBuilder();
$class = new ReflectionClass(Controller::class);
$reader = new MethodReader($class, new ReflectionMethod(Controller::class, 'documented'), $builder);

Assert::same('Summary of it.', $reader->doc->summary);
Assert::same('Longer text.', $reader->doc->description);
Assert::same('The users', $reader->getReturnDescription());
Assert::same('list<User>', (string) $reader->getReturnType());
Assert::false($reader->deprecated);

$parameters = $reader->getParameters();
Assert::same(['page', 'addresses', 'note', 'role', 'example', 'when'], array_keys($parameters));
Assert::true($parameters['page']['required']);
Assert::false($parameters['addresses']['required']);
Assert::false($parameters['note']['required'], 'null can stand in for a missing value');
Assert::same('Which page', $parameters['page']['description']);
Assert::same(['minimum' => 1, 'type' => 'integer'], $parameters['page']['schema']);
Assert::same(['allOf' => [['$ref' => '#/components/schemas/Role']], 'default' => 'user'], $parameters['role']['schema']);
Assert::same(['example' => 'x', 'default' => 'x', 'type' => 'string'], $parameters['example']['schema']);
Assert::same(['type' => 'string', 'format' => 'date-time'], $parameters['when']['schema'], 'an object default has no JSON form');

$throws = array_map(static fn(Throws $throw): array => [$throw->code, $throw->message, $throw->httpCode], $reader->getThrows());
Assert::same([[1001, 'Closed', 409], [500, 'Class-wide', 500]], $throws);

$overridden = new MethodReader($class, new ReflectionMethod(Controller::class, 'overridden'), $builder);
Assert::same(['type' => 'object', 'properties' => ['a' => ['type' => 'integer']]], $overridden->getParameters()['filter']['schema']);

Assert::true((new MethodReader($class, new ReflectionMethod(Controller::class, 'deprecatedByDoc'), $builder))->deprecated);
Assert::true((new MethodReader($class, new ReflectionMethod(Controller::class, 'deprecatedByAttribute'), $builder))->deprecated);

// --- Content of #[Response] and #[Parameter]

$content = static fn(string|array $definition): array|stdClass => ContentDefinition::toSchema($definition, new SchemaBuilder(), $class);

Assert::same(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Address']], $content('list<Address>'));
Assert::same(['type' => 'string', 'format' => 'binary'], $content('@binary'));
Assert::same(['$ref' => '#/components/schemas/Error'], $content('@error'));
Assert::same(['type' => 'string', 'minLength' => 1], $content(['type' => 'string', 'minLength' => 1]), 'JSON Schema goes through');
Assert::equal(new stdClass(), $content('%%%'), 'what can not be read is anything');

// The older notation
Assert::same(['type' => 'array', 'items' => ['type' => 'string']], $content(['array', 'string']));
Assert::same(
    ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']]]],
    $content(['object', ['id' => 'int', 'tags' => ['array', 'string']]]),
);
$pager = $content(['@pager', 'Address']);
Assert::same(['data', 'total', 'page', 'pageSize', 'from', 'to'], array_keys($pager['properties']));
Assert::same(['$ref' => '#/components/schemas/Address'], $pager['properties']['data']['items']);

// --- Configuration

Assert::same(['title' => 'api API', 'version' => '1.0.0'], DocumentationConfig::buildInfo([], 'api'));
Assert::same(['title' => 'Mine', 'version' => '2'], DocumentationConfig::buildInfo(['title' => 'Mine', 'version' => '2'], 'api'));
Assert::same(
    ['a' => ['b' => 1, 'c' => 2], 'tags' => ['new'], 'x-d' => true],
    DocumentationConfig::merge(['a' => ['b' => 1], 'tags' => ['old']], ['a' => ['c' => 2], 'tags' => ['new'], 'x-d' => true]),
);
Assert::same('Requires login.', DocumentationConfig::describeRoles([]));
Assert::same('Requires login and the role a or b, and c.', DocumentationConfig::describeRoles([['a', 'b'], ['c']]));

// --- YAML is the same data

$document = json_decode(<<<'JSON'
    {
        "openapi": "3.1.0",
        "empty object": {},
        "empty list": [],
        "paths": {
            "/users/{id}": {
                "get": {
                    "parameters": [{"name": "id", "in": "path", "required": true, "schema": {"type": "integer"}}, {"name": "x"}],
                    "responses": {"200": {"description": "A: b # not a comment"}, "404": {"description": "Line\nand line"}}
                }
            }
        },
        "scalars": ["true", true, "null", null, 1, 1.5, "1", "", "yes", "- dash", "žluť", "a\"b", "$ref", {"$ref": "#/x"}],
        "nested lists": [[1, [2, 3]], [], [{"a": 1, "b": 2}]]
    }
    JSON);

$yaml = Yaml::encode($document);
Assert::same(json_decode(json_encode($document), true), Symfony\Component\Yaml\Yaml::parse($yaml), "It must parse back:\n{$yaml}");
Assert::contains('"200":', $yaml, 'a status is a string key');
Assert::contains('empty object: {}', str_replace('"', '', $yaml));
Assert::contains('openapi: "3.1.0"', $yaml);

// --- Documents

$documents = new class (['api' => '{"a":{},"b":[]}']) extends Documents {
};

Assert::same(['api'], $documents->getEndpoints());
Assert::true($documents->has('api'));
Assert::false($documents->has('other'));
Assert::same('{"a":{},"b":[]}', $documents->toJson('api'));
Assert::same('{"a":{},"b":[]}', json_encode($documents->toObject('api')), 'an empty object survives');
Assert::same("a: {}\nb: []\n", $documents->toYaml('api'));
Assert::exception(static fn() => $documents->toJson('other'), InvalidArgumentException::class, "Endpoint 'other' has no document.");

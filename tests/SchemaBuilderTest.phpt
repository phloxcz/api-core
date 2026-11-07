<?php

/**
 * JSON Schema generated from types and from descriptions of #[Response], and the YAML output.
 */

declare(strict_types=1);

use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\ApiError;
use Phlox\ApiCore\ApiException;
use Phlox\ApiCore\Attributes\Constraints\Format;
use Phlox\ApiCore\Attributes\ErrorCase;
use Phlox\ApiCore\ProtocolError;
use Phlox\ApiCore\Attributes\Constraints\Length;
use Phlox\ApiCore\Attributes\Constraints\Range;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Attributes\Schema;
use Phlox\ApiCore\Attributes\Throws;
use Phlox\ApiCore\Documentation\Documents;
use Phlox\ApiCore\Documentation\DocumentationConfig;
use Phlox\ApiCore\Documentation\MethodReader;
use Phlox\ApiCore\Documentation\SchemaBuilder;
use Phlox\ApiCore\Documentation\Yaml;
use Phlox\ApiCore\Schema\AnyOf;
use Phlox\ApiCore\Schema\Binary;
use Phlox\ApiCore\Schema\JsonSchema;
use Phlox\ApiCore\Schema\ListOf;
use Phlox\ApiCore\Schema\MapOf;
use Phlox\ApiCore\Schema\Paged;
use Phlox\ApiCore\Schema\SchemaDefinition;
use Phlox\ApiCore\Schema\Shape;
use Phlox\ApiCore\Types\Type;
use Phlox\ApiCore\Types\TypeParser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

enum TaskError: int implements ApiError
{
    #[ErrorCase(423, 'The task is locked.')]
    case Locked = 1003;

    case Silent = 1004;
}

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

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Content
{
    public function __construct(public mixed $content)
    {
    }
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
    #[Throws(409, 'Closed', errorCode: 1001)]
    public function documented(#[Range(min: 1)] int $page, array $addresses = [], ?string $note = null, Role $role = Role::User, #[Schema(['example' => 'x'])] string $example = 'x', DateTimeImmutable $when = new DateTimeImmutable()): array
    {
        return [];
    }

    #[Phlox\ApiCore\Attributes\Parameter('filter', ['object', ['a' => 'int']])]
    public function removed(array $filter): void
    {
    }

    #[Content(['object', ['a' => 'int']])]
    #[Content(['@pager', Address::class])]
    public function older(): void
    {
    }

    #[Content(new ListOf('int'))]
    public function newer(): void
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
Assert::same('array', (string) $reader->getReturnType(), 'a response is described by an attribute, not by @return');
Assert::same('Controller::documented()', $reader->getName());
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

$throws = array_map(static fn(Throws $throw): array => [$throw->httpCode, $throw->message, $throw->errorCode], $reader->getThrows());
Assert::same([[409, 'Closed', 1001], [500, 'Class-wide', null]], $throws);

// --- Errors: a status with a message, a code of your own, or an enum saying it all

$e = new ApiException(404, 'The task was not found.');
Assert::same([404, 404, null, 'The task was not found.'], [$e->getHttpCode(), $e->getCode(), $e->getErrorCode(), $e->getMessage()], 'the code clients get is the status');
$e = new ApiException(409, 'Closed.', errorCode: 1001);
Assert::same([409, 1001, 1001], [$e->getHttpCode(), $e->getCode(), $e->getErrorCode()]);
Assert::same('Not found.', (new ApiException(404))->getMessage(), 'a message of the status');
Assert::same('Error.', (new ApiException(418))->getMessage());

$e = new ApiException(TaskError::Locked);
Assert::same([423, 1003, 'The task is locked.'], [$e->getHttpCode(), $e->getCode(), $e->getMessage()]);
Assert::same('Locked by Ann.', (new ApiException(TaskError::Locked, 'Locked by Ann.', data: ['by' => 'Ann']))->getMessage(), 'a message of its own');
$e = new ApiException(ProtocolError::InvalidParams);
Assert::same([422, -32602, 'Invalid method parameter(s).'], [$e->getHttpCode(), $e->getCode(), $e->getMessage()]);

// What older versions passed first fails, saying how to write it
Assert::exception(
    static fn() => new ApiException(1001, 'Order is closed.'),
    InvalidArgumentException::class,
    "1001 is not the HTTP status of an error. The status comes first, and a code of your own goes to errorCode: new ApiException(409, 'Order is closed.', errorCode: 1001).",
);
Assert::exception(static fn() => new ApiException(200), InvalidArgumentException::class, '200 is not the HTTP status of an error.%a%');
Assert::exception(static fn() => new ApiException(TaskError::Silent), LogicException::class, 'TaskError::Silent has no #[ErrorCase(httpCode, message)].');
Assert::exception(static fn() => new Throws(1001, 'Closed'), InvalidArgumentException::class, '1001 is not the HTTP status of an error.%a%');

$fromEnum = new Throws(TaskError::Locked);
Assert::same([423, 'The task is locked.', 1003], [$fromEnum->httpCode, $fromEnum->message, $fromEnum->errorCode]);
Assert::same('Not found.', (new Throws(404))->message);

// Where a status means nothing, as in JSON-RPC, a code alone
$rpcOnly = new ApiException(errorCode: 1001, message: 'Closed.');
Assert::same([500, 1001, 'Closed.'], [$rpcOnly->getHttpCode(), $rpcOnly->getCode(), $rpcOnly->getMessage()]);
$rpcThrows = new Throws(errorCode: 1001, message: 'Closed.');
Assert::same([500, 'Closed.', 1001], [$rpcThrows->httpCode, $rpcThrows->message, $rpcThrows->errorCode]);
Assert::exception(static fn() => new Throws(message: 'x'), InvalidArgumentException::class, '#[Throws] needs an HTTP status, an errorCode, or a case of an ApiError enum.');
Assert::exception(static fn() => new ApiException(1001, 'x'), InvalidArgumentException::class, '1001 is not the HTTP status of an error.%a%'); // the order of older versions still fails

$removed = new MethodReader($class, new ReflectionMethod(Controller::class, 'removed'), $builder);
Assert::exception(static fn() => $removed->getParameters(), InvalidArgumentException::class, '#[Parameter] is gone. %a%');

$older = new MethodReader($class, new ReflectionMethod(Controller::class, 'older'), $builder);
Assert::exception(static fn() => $older->getAttributes(Content::class), InvalidArgumentException::class, "The array notation is gone, use new Shape(['id' => 'int', 'note?' => '?string']).");
$newer = new MethodReader($class, new ReflectionMethod(Controller::class, 'newer'), $builder);
Assert::equal([new Content(new ListOf('int'))], $newer->getAttributes(Content::class));

Assert::true((new MethodReader($class, new ReflectionMethod(Controller::class, 'deprecatedByDoc'), $builder))->deprecated);
Assert::true((new MethodReader($class, new ReflectionMethod(Controller::class, 'deprecatedByAttribute'), $builder))->deprecated);

// --- Descriptions in #[Response]

$content = static fn(SchemaDefinition|string $definition): array|stdClass => (new SchemaBuilder())->buildDefinition($definition);

Assert::same(['type' => 'integer'], $content('int'));
Assert::same(['type' => ['string', 'null']], $content('?string'));
Assert::equal(new stdClass(), $content('mixed'));
Assert::same(['$ref' => '#/components/schemas/Address'], $content(Address::class));
Assert::same(['anyOf' => [['$ref' => '#/components/schemas/Address'], ['type' => 'null']]], $content('?' . Address::class));
Assert::same(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Address']], $content(new ListOf(Address::class)));
Assert::same(['type' => 'object', 'additionalProperties' => ['type' => 'integer']], $content(new MapOf('int')));
Assert::same(['type' => 'string', 'format' => 'binary'], $content(new Binary()));
Assert::same(
    ['type' => 'array', 'items' => ['anyOf' => [['type' => 'integer'], ['type' => ['string', 'null']], ['$ref' => '#/components/schemas/Address']]]],
    $content(new ListOf(new AnyOf('int', '?string', Address::class))),
);
Assert::exception(static fn() => new AnyOf('int'), InvalidArgumentException::class, 'new AnyOf() needs at least two options; for one, name it alone.');
Assert::same(['type' => 'string', 'format' => 'binary'], $content(Nette\Http\FileUpload::class), 'an uploaded file');
Assert::same(['type' => 'string', 'minLength' => 1], $content(new JsonSchema(['type' => 'string', 'minLength' => 1])), 'JSON Schema goes through');
Assert::equal(new stdClass(), $content(new JsonSchema([])));

Assert::same(
    [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'note' => ['type' => ['string', 'null']],
        ],
        'required' => ['id', 'tags'],
    ],
    $content(new Shape(['id' => 'int', 'tags' => new ListOf('string'), 'note?' => '?string'])),
    'a name with ? can be left out, a type with ? can be null',
);
Assert::equal(['type' => 'object', 'properties' => new stdClass()], $content(new Shape([])));
Assert::exception(static fn() => $content(new Shape(['int'])), InvalidArgumentException::class, 'new Shape() needs names of its fields, got a list.');

$page = $content(new Paged(Address::class));
Assert::same(['data', 'total', 'page', 'pageSize', 'from', 'to'], array_keys($page['properties']));
Assert::same($page['required'], array_keys($page['properties']));
Assert::same(['$ref' => '#/components/schemas/Address'], $page['properties']['data']['items']);
Assert::same(['integer', 'null'], $page['properties']['from']['type']);
Assert::same($page, $content(new Shape(['value' => new Paged(Address::class)]))['properties']['value']);

// A description of your own
$own = new class implements SchemaDefinition {
    public function toSchema(SchemaBuilder $builder): array
    {
        return (new Shape(['value' => 'int']))->toSchema($builder);
    }
};
Assert::same(['type' => 'object', 'properties' => ['value' => ['type' => 'integer']], 'required' => ['value']], $content(new ListOf($own))['items']);

// What isn't a type says what to write instead
Assert::exception(static fn() => $content('Nowhere'), InvalidArgumentException::class, "Unknown type 'Nowhere'. Name a class by Foo::class, %a%");
Assert::exception(static fn() => $content('list<Address>'), InvalidArgumentException::class, "Unknown type 'list<Address>'. Types as text are gone; %a%");
Assert::exception(static fn() => $content('@binary'), InvalidArgumentException::class, "Unknown type '@binary'. Use new Binary.");
Assert::exception(static fn() => $content('@error'), InvalidArgumentException::class, "Unknown type '@error'. Leave the content out%a%");
Assert::exception(static fn() => $content('integer'), InvalidArgumentException::class, "Unknown type 'integer'.%a%");
Assert::exception(static fn() => $content(''), InvalidArgumentException::class, "Unknown type ''.%a%");

// --- A page as built, and as described

Assert::same(['data' => ['a', 'b'], 'total' => 12, 'page' => 2, 'pageSize' => 10, 'from' => 10, 'to' => 11], Paged::result(['a', 'b'], 12, 10, 10));
Assert::same(['data' => [], 'total' => 12, 'page' => 3, 'pageSize' => 10, 'from' => null, 'to' => null], Paged::result([], 12, 20, 10), 'past the end');
Assert::same(['data' => [], 'total' => 0, 'page' => 1, 'pageSize' => 5, 'from' => null, 'to' => null], Paged::result([], 0, 0, 5));
Assert::same(array_keys($page['properties']), array_keys(Paged::result([], 0, 0, 1)));
Assert::exception(static fn() => Paged::result([], 0, 0, 0), InvalidArgumentException::class, 'A page needs a size from 1, got 0.');

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

// --- Checking a value against a document, as tests of an API do

$checkedDocument = ['components' => ['schemas' => ['Item' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer'], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']]]]]]];
$item = ['$ref' => '#/components/schemas/Item'];
Assert::same([], Phlox\ApiCore\Testing\SchemaValidator::validate(['id' => 1, 'tags' => ['a']], $item, $checkedDocument));
Assert::same(
    ["\$: 'id' is missing", '$.tags[1]: 2 is not string'],
    Phlox\ApiCore\Testing\SchemaValidator::validate(['tags' => ['a', 2]], $item, $checkedDocument),
);
Assert::same(['$: #/components/schemas/Nothing does not exist'], Phlox\ApiCore\Testing\SchemaValidator::validate(1, ['$ref' => '#/components/schemas/Nothing'], $checkedDocument));
Assert::same([], Phlox\ApiCore\Testing\SchemaValidator::validate(null, ['anyOf' => [['type' => 'integer'], ['type' => 'null']]], $checkedDocument));
Assert::same(['$: "x" matches none of anyOf'], Phlox\ApiCore\Testing\SchemaValidator::validate('x', ['anyOf' => [['type' => 'integer'], ['type' => 'null']]], $checkedDocument));

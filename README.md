# phloxcz/api-core

**English** | [Česky](README.cs.md)

**Shared core of the Phlox API libraries for [Nette](https://nette.org).**

You normally don't install this package directly. It comes with a transport library:

- [`phloxcz/rpc-api`](https://github.com/phloxcz/rpc-api) – JSON-RPC 2.0
- [`phloxcz/rest-api`](https://github.com/phloxcz/rest-api) – REST

[`phloxcz/api-query`](https://github.com/phloxcz/api-query) adds a query language to either of them. [`phlox-api-sample`](https://github.com/phloxcz/api-sample) shows them together, and [`MIGRATION.md`](MIGRATION.md) leads from `abrad450/api-server-nette`.

> ⚠️ This package is under active development. The API is not yet stable.

## Requirements

PHP 8.4+, `nette/di`, `nette/http`, `nette/schema`, `nette/security`, `nette/utils`.

Optional: `tracy/tracy` for logging unexpected exceptions (otherwise `error_log()`), `nette/application` for `$this->linkGenerator`.

## API controllers

A controller is an ordinary DI service extending `ApiController`:

```php
use Phlox\ApiCore\ApiController;
use Phlox\ApiCore\Attributes\AllowAnonymous;
use Phlox\ApiCore\Attributes\Authorize;
use Phlox\ApiCore\Attributes\NonApi;

#[Authorize('admin')]
class UserController extends ApiController
{
    public function startup(): void
    {
        parent::startup(); // required
        // runs before authorization, so it can authenticate the user from a token
    }

    public function read(int $id): array { /* ... */ }

    #[AllowAnonymous]
    public function ping(): string { return 'pong'; }

    #[NonApi]
    public function notCallable(): void { /* ... */ }
}
```

Available to controllers: `$this->request`, `$this->response`, `$this->user`, `$this->session`, `$this->linkGenerator`, `$this->controllerName`, `$this->serviceName`, `$this->methodName`, `$this->parameters`, `$this->jsonData`, and `$this->terminate()` to end a call without an API response.

### What is exposed

Public methods declared directly on the controller class or in its traits. **Not** exposed: inherited methods (override one to expose it), static methods, `startup()`, magic methods, `inject*()` methods and anything marked `#[NonApi]`.

### Authorization

Authorization is on by default: a method nobody annotated requires a logged-in user. The default can be changed per endpoint with `authorize: false`.

| Situation | Result |
|---|---|
| `#[AllowAnonymous]` on the method | public, always |
| `#[Authorize]` on the method, the class or any parent class | login required, and every such attribute must be satisfied |
| `#[AllowAnonymous]` on the class, no `#[Authorize]` applies | public |
| none of the above | the endpoint's default |

One attribute is satisfied by **at least one** of its roles, or by all of them with `all: true`:

```php
#[Authorize(['admin', 'editor'])]               // admin or editor
class ArticleController extends ApiController
{
    public function read(): array { /* admin or editor */ }

    #[Authorize('publisher')]                   // (admin or editor) and publisher
    public function publish(): void { /* ... */ }

    #[Authorize(['legal', 'audit'], all: true)] // (admin or editor) and legal and audit
    public function purge(): void { /* ... */ }
}
```

A method's attribute can only narrow what its controller allows; to open a method up, use `#[AllowAnonymous]`.

An anonymous user gets code `401`, a logged-in user without the roles `403`.

### Order of a call

Inject → `startup()` → authorization → parameter binding → the method. Parameters are bound after authorization, so an unauthorized client learns nothing about a method's signature.

## Parameters

Values are converted according to the method's native parameter types. A value that doesn't fit gives an "invalid params" error, not a `TypeError` from PHP.

| Type | Accepts |
|---|---|
| `int` | integers, whole floats, integer strings (`"1.5"` is refused) |
| `float` | numbers, numeric strings |
| `string` | strings, numbers (`true` is refused) |
| `bool` | `true`, `false`, `1`, `0`, `"1"`, `"0"`, `"true"`, `"false"` |
| `array` | arrays |
| `object` | JSON objects, JSON strings holding an object |
| `\DateTime`, `\DateTimeImmutable`, `\DateTimeInterface` | date strings; an impossible date such as `2024-13-45` is refused |
| backed enums | a valid backing value |
| classes implementing `ApiConstructible` | whatever `apiConstruct()` accepts; throw `\InvalidArgumentException` to refuse a value |
| unions | the type the value matches exactly, otherwise the first one it converts to |

A missing parameter gets its default value, or `null` when nullable; otherwise it's an error.

A docblock such as `@param int[] $ids` doesn't change what's accepted, unless it mentions a DTO.

## DTOs

A class with `#[Dto]` describes data of the API. As a parameter, it's built from the request. Returned from a method, it's turned into JSON. In both cases it appears in the generated documentation.

```php
use Phlox\ApiCore\Attributes\Constraints\Format;
use Phlox\ApiCore\Attributes\Constraints\Length;
use Phlox\ApiCore\Attributes\Constraints\Range;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Attributes\Schema;

#[Dto]
final readonly class CreateUser
{
    /**
     * @param list<AddressInput> $addresses Where the user lives
     */
    public function __construct(
        #[Length(min: 2, max: 100)] public string $name,
        #[Format('email'), Schema(['example' => 'ann@example.com'])] public string $email,
        #[Range(min: 18)] public ?int $age = null,
        public Role $role = Role::User,
        public array $addresses = [],
    ) {
    }
}
```

Fields are the parameters of the constructor, then other public properties. Values are converted as method parameters are, and nested DTOs, enums and dates work. A list of DTOs takes its type from a docblock: `@param list<Foo> $x` on the constructor or `@var list<Foo>` above a property.

Fields the class doesn't declare are ignored, so a client can't set anything else. A constructor can refuse values by throwing `\InvalidArgumentException`.

In a response, public properties are sent, and dates inside a DTO become ISO 8601 strings. Anything that isn't a DTO is sent as before, including a date in a plain array.

### Collections

PHP can only say `array`, so the type of the items comes from a docblock:

```php
#[Dto]
final readonly class ImportTasks
{
    /**
     * @param list<CreateTask> $tasks
     * @param list<string> $labels
     * @param array<string, int> $limits An object with numbers
     */
    public function __construct(
        #[Count(min: 1, max: 20)] public array $tasks,
        public array $labels = [],
        public array $limits = [],
    ) {
    }
}
```

`$import->tasks` then holds instances of `CreateTask`, each checked by its own rules. A problem has a path with the position of the row, such as `tasks.1.title`, and nothing reaches the method until all of it is right. `#[Count]` limits the number of items, and it's a good idea to have it, so that a client can't send a million of them.

Without a DTO in its type, a docblock isn't enforced: `list<string>` on a parameter of a method stays documentation. In a DTO, it's enforced.

### Changing only what was sent

A DTO has to be whole, so it can't tell a field left out from a field sent as `null`. A `Patch` can. It takes its fields and rules from a DTO named by `#[PatchOf]`, so one DTO is enough for both creating and changing:

```php
public function change(int $id, #[FromBody, PatchOf(SaveType::class)] Patch $changes): array
{
    $type = $this->findType($id);
    $changes->applyTo($type);       // sets what was sent on properties of the same name
    $type->saveModified();
    // ...
}
```

| Method | Gives |
|---|---|
| `has('name')` | whether it was sent, also as `null` |
| `get('name', $default)` | its converted value |
| `toArray()` | what was sent, by the names of the DTO |
| `applyTo($object)` | sets what was sent on the object, and returns it |

No field is required, but a value that is sent has to meet the rules: types, constraints, and `null` only where the DTO allows it. Fields the DTO doesn't declare are ignored. A nested DTO is whole, so a change to a list replaces the list.

The DTO itself isn't created, so what its constructor checks by throwing `\InvalidArgumentException` doesn't run, and neither does a rule across several fields. The documentation has it as the schema of the DTO without `required`, named like `SaveType_Patch`.

A class with `#[Resource]` from `phloxcz/api-query` says what goes out and can't take a request, not even through `Patch`. Doing so is an exception for you, because it would quietly leave out what a client sent.

### Constraints

These work on DTO fields and on method parameters alike, and appear in the documentation too. `null` isn't checked; whether it's allowed depends on the type.

| Attribute | Applies to | JSON Schema |
|---|---|---|
| `#[Range(min: 1, max: 9)]` | numbers | `minimum`, `maximum` |
| `#[Length(min: 1, max: 9)]` | strings, in characters | `minLength`, `maxLength` |
| `#[Count(min: 1, max: 9)]` | arrays | `minItems`, `maxItems` |
| `#[Pattern('^[A-Z]+$')]` | strings; not anchored, as in JSON Schema | `pattern` |
| `#[Format('email')]` | strings: `email`, `uri`, `uuid`, `date`, `date-time`, `ipv4`, `ipv6` | `format` |

An invalid expression or an unknown format fails when the container compiles.

### Invalid data

All problems are reported at once. Each of them has a path, and when there's only one, the message says what it is:

```json
{"code": -32602, "message": "Invalid method parameter(s).", "fields": {
    "input.name": "must have at least 2 characters.",
    "input.addresses.1.city": "is required."
}}
```

## Documentation

Both transports generate a document from the same attributes and types they run on, when the container compiles. There's nothing to keep in sync by hand.

| Source | Becomes |
|---|---|
| native types, `@param`, `@var` | schemas of parameters and fields, including `list<Foo>`, `array<string, int>`, `array{id: int}` and unions |
| the native return type | the schema of a response |
| `#[Response]` in REST, `#[Result]` in JSON-RPC | a response the return type can't tell, see [Describing a response](#describing-a-response) |
| first paragraph of a docblock, the rest | summary, description |
| text of `@param` | descriptions |
| `#[Dto]`, backed enums | shared schemas |
| constraints | keywords of the schema |
| `#[Authorize]`, `#[AllowAnonymous]` | `x-roles`, the description, errors `401` and `403` |
| `@deprecated`, `#[\Deprecated]` | `deprecated` |
| `#[Throws(409, 'Order is closed.')]`, `#[Throws(OrderError::Closed)]` | documented errors, as `ApiException` takes them; on a class, for all of its methods |
| `#[Schema([...])]` | JSON Schema keywords added to a parameter or a field; on an `ApiConstructible` class, the schema of its values |

For a parameter or a field, a docblock only refines a type the signature leaves open, such as `array` or `mixed`, and one that can't be read is ignored. A class that isn't a DTO is documented as a plain object.

### Describing a response

A response is described in one place. That's the native return type, or an attribute where the type says too little, such as `array`. `@return` isn't read, so it stays for PHPStan.

```php
use Phlox\ApiCore\Schema\{ListOf, Paged, Shape};

#[Response(200, new Shape(['value' => new Paged(User::class)]))]
public function list(?Query $query = null): array

#[Result(new ListOf(User::class), description: 'The users')]   // JSON-RPC
public function users(): array
```

| Description | JSON |
|---|---|
| `User::class` | the class: a DTO, an enum, an entity or a resource |
| `'int'`, `'float'`, `'string'`, `'bool'`, `'mixed'` | a scalar; `'?string'` can be null |
| `new ListOf(User::class)` | `[...]` |
| `new AnyOf('int', 'string', User::class)` | a value of any of these |
| `new MapOf('int')` | `{"open": 3, "done": 5}` |
| `new Shape(['id' => 'int', 'note?' => '?string'])` | an object; a field is always there unless its name ends with `?` |
| `new Paged(User::class)` | a page: `{data, total, page, pageSize, from, to}` |
| `new Binary` | bytes of a file |
| `new JsonSchema(['type' => 'string', 'format' => 'date'])` | JSON Schema as it is |

They nest, as in `new Shape(['ids' => new ListOf('int')])`. A class is named by `::class`, so an IDE imports, completes and renames it. Text such as `'User'` or `'list<User>'` fails when the container compiles, and so does the array notation of older versions, with what to write instead.

`?` means two different things. `'note?' => 'string'` can be missing, and `'note' => '?string'` is always there but can be null.

A page is built by `Paged::result($rows, $total, $offset, $pageSize)`. The query processor builds it so, and it's how your own paging returns one, so a page and its description can't differ.

A structure every response of a project has, such as an envelope, can be described once by implementing `SchemaDefinition`:

```php
final class Value implements SchemaDefinition
{
    public function __construct(private SchemaDefinition|string $content) {}

    public function toSchema(SchemaBuilder $builder): array
    {
        return (new Shape(['value' => $this->content]))->toSchema($builder);
    }
}

#[Response(200, new Value(new Paged(User::class)))]
```

Keep in the configuration only what can't be read from the code:

```neon
documentation:
    enabled: true           # whether to generate the document at all
    expose: true            # whether to publish it; by default only in debug mode
    info:
        title: Orders API   # defaults to "<endpoint> API"
        version: 2.1.0      # defaults to 1.0.0
    servers:
        - url: https://api.example.com/api
    extra:                  # merged into the document, such as tags or x-*
        x-owner: team-a
```

Documents can also be read from the application, which is useful for writing them to a file in CI:

```php
$container->getByType(Phlox\RestApi\OpenApi::class)->toYaml('api');
$container->getByType(Phlox\RpcApi\OpenRpc::class)->toJson('api');
```

To publish a document only to some users, leave `expose` off and return it from a controller of yours, with `#[Authorize]` on it.

## Errors

Throw `ApiException` with the HTTP status, and a message for the client:

```php
throw new ApiException(404, 'The task was not found.');
throw new ApiException(409, 'The order is closed.', errorCode: 1001, internal: "Closed on {$date}", data: ['orderId' => $id]);
```

The client gets `{"error": {"code": ..., "message": ...}}`. The `code` is `errorCode`, or the status where it's enough, so `errorCode` is only for telling apart errors of one status. The message and data are public, and the internal message is added only in debug mode. Any other exception is logged and reaches the client as a generic internal error.

The status comes first, from `400` to `599`. Anything else fails, such as a code in its place as in older versions, and the message says how to write it.

Errors an application reports in many places can be an enum, which holds the code, the message and the status in one place, for throwing and for documentation alike:

```php
enum OrderError: int implements ApiError
{
    #[ErrorCase(409, 'The order is closed.')]
    case Closed = 1001;

    #[ErrorCase(404, 'The order was not found.')]
    case NotFound = 1002;
}

throw new ApiException(OrderError::Closed);
throw new ApiException(OrderError::NotFound, "Order {$id} was not found.");   // a message of its own

#[Throws(OrderError::Closed)]
public function close(int $id): void { /* ... */ }
```

Errors of the protocol itself are `ProtocolError`, with the codes of JSON-RPC 2.0, such as `ProtocolError::InvalidParams` for `-32602`. JSON-RPC reports these codes, and REST the HTTP status instead.

Where a status means nothing, as in a method only JSON-RPC calls, name the code alone: `new ApiException(errorCode: 1001, message: 'The order is closed.')`, and `#[Throws(errorCode: 1001, message: '...')]`. REST would answer such an error with `500`.

## When the API runs

With `nette/application`, a server takes over in `Application::$onStartup`: the bootstrap and all extensions have finished, whatever their order, and a request the API handles ends there. So `index.php` has to call `Application::run()`, as it usually does. Without `nette/application` it happens in the container's `initialize()`, and whatever comes after `createContainer()` doesn't run for such a request.

## License

[MIT](LICENSE)

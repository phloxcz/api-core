# phloxcz/api-core

**Shared core of the Phlox API libraries for [Nette](https://nette.org).**

You normally don't install this package directly. It comes with a transport library:

- [`phloxcz/rpc-api`](../phlox-rpc-api) – JSON-RPC 2.0
- [`phloxcz/rest-api`](../phlox-rest-api) – REST

[`phloxcz/api-query`](../phlox-api-query) adds a query language to either of them. [`phlox-api-sample`](../phlox-api-sample) shows them together, and [`MIGRATION.md`](MIGRATION.md) leads from `abrad450/api-server-nette`.

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
| native types, `@param`, `@return`, `@var` | schemas, including `list<Foo>`, `array<string, int>`, `array{id: int}` and unions |
| first paragraph of a docblock, the rest | summary, description |
| text of `@param` and `@return` | descriptions |
| `#[Dto]`, backed enums | shared schemas |
| constraints | keywords of the schema |
| `#[Authorize]`, `#[AllowAnonymous]` | `x-roles`, the description, errors `401` and `403` |
| `@deprecated`, `#[\Deprecated]` | `deprecated` |
| `#[Throws(1001, 'Order is closed.', httpCode: 409)]` | documented errors; on a class, for all of its methods |
| `#[Schema([...])]` | JSON Schema keywords added to a parameter or a field; on an `ApiConstructible` class, the schema of its values |

A docblock only refines a type the signature leaves open, such as `array` or `mixed`, and one that can't be read is ignored. A class that isn't a DTO is documented as a plain object.

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

Throw `ApiException` to report an error to the client:

```php
throw new ApiException(1001, 'Order is closed.', internal: "Order {$id} closed on {$date}", data: ['orderId' => $id]);
```

The message and data are public. The internal message is added only in debug mode. Any other exception is logged and reaches the client as a generic internal error.

## When the API runs

With `nette/application`, a server takes over in `Application::$onStartup`: the bootstrap and all extensions have finished, whatever their order, and a request the API handles ends there. So `index.php` has to call `Application::run()`, as it usually does. Without `nette/application` it happens in the container's `initialize()`, and whatever comes after `createContainer()` doesn't run for such a request.

## License

[MIT](LICENSE)

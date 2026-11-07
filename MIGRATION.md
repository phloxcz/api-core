# Migrating from abrad450/api-server-nette

**English** | [Česky](MIGRATION.cs.md)

This guide takes a project from `abrad450/api-server-nette` and `abrad450/mapped-database-nette` to the Phlox libraries. It's written for whoever does the migration. Details of each library are in its README, and [`phlox-api-sample`](https://github.com/phloxcz/api-sample) shows the result.

Controllers mostly stay as they are. Most of the work is in the configuration, in moving entities, and in checking the behaviour that got stricter.

## 1. Packages

```bash
composer remove abrad450/api-server-nette abrad450/mapped-database-nette
composer require phloxcz/rest-api phloxcz/api-query phloxcz/entity-database
composer require phloxcz/rpc-api             # with JSON-RPC
composer require phloxcz/firebird-database   # with Firebird
```

`index.php` has to call `Application::run()`, which is where the API takes over, after the bootstrap and all extensions have finished. **Code after `createContainer()` now also runs for API requests,** including anything like a redirect or a maintenance mode.

## 2. Configuration

```neon
extensions:
    entityDatabase: Phlox\EntityDatabase\DI\Extension
    apiQuery: Phlox\ApiQuery\DI\Extension
    restApi: Phlox\RestApi\DI\Extension

apiQuery:
    maxPageSize: 500
    resources: App\ApiV1Module\Resources   # see step 5

restApi:
    endpoints:
        'api/v1':
            namespace: App\ApiV1Module
            authorize: false        # only if the API really has public methods without #[AllowAnonymous]
            accessControl:
                allowedOrigins: ['https://app.example.com']
                allowCredentials: true
                allowHeaders: [authorization, content-type, accept]
            documentation:
                expose: true
                info: {title: My API, version: 1.0.0}
                servers: [{url: 'https://api.example.com'}]
                securitySchemes:
                    bearerAuth: {type: http, scheme: bearer}
                extra:
                    security:
                        - bearerAuth: []
```

| Old | New |
|---|---|
| `apiServer: query:` | `apiQuery:` |
| `apiServer: rest: <name>` | `restApi: endpoints: <name>` |
| `apiServer: rpc: <name>` | `rpcApi: endpoints: <name>` |
| `openapi: content: info`, `servers` | `documentation: info`, `servers` |
| `openapi: content: components: securitySchemes` | `documentation: securitySchemes` |
| `openapi: content:` anything else | `documentation: extra` |
| `openapi: outputPath`, and a command generating YAML | `$container->getByType(OpenApi::class)->toYaml('api/v1')`, or publish it with `expose: true` and drop the controller serving the file |
| `query: pagination: true` | always so, nothing to set |

**`allowedOrigins: ['*']` with `allowCredentials: true` no longer compiles.** Browsers refuse that combination, and the old library got around it by sending back the origin of any website, which let it read responses with the cookies of a logged-in user. A client authenticating by an `Authorization` header needs no credentials here, so `allowedOrigins: ['*']` with `allowCredentials: false` is fine for it. For cookies, list the origins.

**Authorization is on by default.** A method with neither `#[Authorize]` nor `#[AllowAnonymous]` now requires login. Set `authorize: false` on an endpoint which is protected elsewhere, such as behind a gateway.

## 3. Namespaces

| Old | New |
|---|---|
| `AbraD450\ApiServer\ApiController` | `Phlox\ApiCore\ApiController` |
| `AbraD450\ApiServer\Common\Attributes\{Authorize,AllowAnonymous,NonApi}` | `Phlox\ApiCore\Attributes\*` |
| `AbraD450\ApiServer\Common\Attributes\Parameter` | gone: the type of the parameter, `@param list<Foo> $x`, or `#[Schema]`, see step 7 |
| `AbraD450\ApiServer\Common\Attributes\FromBody` | `Phlox\RestApi\Attributes\FromBody` |
| `AbraD450\ApiServer\REST\Attributes\*` | `Phlox\RestApi\Attributes\*` |
| `AbraD450\ApiServer\RPC\Attributes\Path` | `Phlox\RpcApi\Attributes\Path` |
| `AbraD450\ApiServer\REST\ApiException`, `RPC\ApiException` | `Phlox\ApiCore\ApiException` |
| `AbraD450\ApiServer\ApiConstructible` | `Phlox\ApiCore\ApiConstructible` |
| `AbraD450\ApiServer\Query\Query`, `Processor` | `Phlox\ApiQuery\Query`, `Processor` |
| `AbraD450\ApiServer\Query\SqlQueryProcessor` | `Phlox\ApiQuery\SqlProcessor` |
| `AbraD450\ApiServer\Query\Sort`, `Criteria`, `Filter`, `FilterGroup` | `Phlox\ApiQuery\*` |
| `AbraD450\ApiServer\Query\ProcessedEntityEventData`, `ProcessSelectionEventData` | `Phlox\ApiQuery\Events\*` |
| `AbraD450\MappedDatabase\Table\Selection`, `GroupedSelection` | `Phlox\EntityDatabase\Database\Table\*` |
| `AbraD450\MappedDatabase\Entity\Entity` | `Phlox\EntityDatabase\Entity\Entity` |

## 4. Controllers

`$this->processor` comes from a trait. Put it on the shared base controller:

```php
use Phlox\ApiCore\ApiController;
use Phlox\ApiQuery\UsesQueryProcessor;

class BaseController extends ApiController
{
    use UsesQueryProcessor;
}
```

Listeners of the processor leave `startup()`. Access control goes to `restrict()` of a resource and computed fields to its methods, see step 5. A listener which stays becomes a service implementing `QueryListener`, registered once, see [Events](https://github.com/phloxcz/api-query#events): the processor is shared, so a listener added in `startup()` is added again on every call.

The rest stays: `process($selection, $query)`, `process($entity, $query)`, `skipLimitOffset: true`, `#[FromBody] ?Query $query = null`, `Query::create([...])`, routes, `$this->response`, `$this->terminate()`, `readfile()` and `print`, and events with `set`, `unset` and `shouldSkip`.

To check in each controller:

| What | Why |
|---|---|
| `#[Authorize(['a', 'b'])]` | means a **or** b now, where it meant both. For both, write `#[Authorize(['a', 'b'], all: true)]` |
| A method with `#[Route]` and no `#[HttpGet]` or another verb | doesn't compile. With neither, it isn't an endpoint and gives 404 |
| A public method inherited from a base controller | is no longer an endpoint; override it |
| `new ApiException(1001, 'x')` | the HTTP status comes first: `new ApiException(409, 'x', errorCode: 1001)`, or just `new ApiException(404, 'x')`, where the status is the code. A code outside `400`–`599` fails, with the right call in the message. Name `internal:`, as the third argument is `errorCode` |
| `#[Throws(1001, 'x', httpCode: 409)]` | `#[Throws(409, 'x', errorCode: 1001)]`, in the order of `ApiException` |
| `ApiException::E_INVALID_PARAMS` and the other constants | `ProtocolError::InvalidParams` and the other cases; the code is `->value` |
| `Authorizer::E_UNAUTHORIZED`, `Authorizer::E_FORBIDDEN` | `401`, `403` |
| `#[Consumes('application/pdf')]` | is enforced: another type gets 415, where it used to pass. Check what clients really send. Without `#[FromBody]`, the body stays unread for the method to stream |
| `$sortList->count()`, `new SortArrayList()` | sorts are a plain array now: `count()`, `[]`. `setSort()`, `$sort->field` and `$sort->dir` stay |
| `$query->getCriteriaByPath()` returning null | stays so; a missing query reaches your code as an empty `Query` only through a parameter, so `?Query` can still be null in a method you call yourself |

A status can change where errors used to be 500: a wrong verb gets 405 with `Allow`, malformed JSON 400, invalid parameters 422, and a method returning nothing 204 unless it wrote the response itself.

## 5. Entities and what a query can reach

Generate entities with `phloxcz/entity-database`, from the database and an `entities.neon`. **Mark every secret `access: w` there:** a password, PIN or token masked only by `onProcessedEntity` can still be filtered by, so a logged-in client can guess it character by character. That holds in the old library as well. After that, drop the masking, because reading a write-only property throws.

What a query can reach of an entity is its resource, a class found in `apiQuery.resources`. It stands for the entity wherever a query reaches it, so controllers need no change. An entity without one, typically a code list, shows all its readable columns and no relations, and needs nothing.

An entity your clients query with relations, or one with access rights or computed fields, gets a resource:

```php
use Nette\Security\User;
use Phlox\ApiQuery\Attributes\Computed;
use Phlox\ApiQuery\Attributes\Resource;
use Phlox\EntityDatabase\Database\Table\Selection;

#[Resource(Entity\Project::class, fields: '*')]
final readonly class ProjectResource
{
    /** @param list<Entity\Drawing> $drawings */
    public function __construct(
        public ?Entity\User $createdBy = null,
        public array $drawings = [],
    ) {
    }

    // What onSelection did, now also in filters and sorts through relations
    public static function restrict(Selection $projects, User $user): void
    {
        $projects->where('company_id', $user->getIdentity()->companyId);
    }
}

#[Resource(Entity\Entry::class, fields: '*')]
final readonly class EntryResource
{
    public function __construct(
        public ?Entity\User $assignedUser = null,
        #[Computed] public ?bool $canManage = null,
    ) {
    }

    // What onProcessedEntity added with `set`, now only when it's selected
    public static function canManage(Entity\Entry $entry, User $user): bool
    {
        return $entry->assignedUserId === $user->getId();
    }
}
```

A relation is typed by the entity it leads to, which is then shown as its own resource, or with its columns if it has none. Services come to `restrict()` and computed fields by type, after the selection or the entity.

To get the relations, look at what clients have in `select`. **A name in `select`, `filter` or `sort` that isn't known now gets 400,** where it used to be skipped silently, also in a filter, which then returned more rows than asked for. So a client with a stale field name or a column name such as `created_at` fails visibly, and has to be fixed. `#[Computed]` is what makes a field which isn't a column known.

Sorting by something that isn't a field, such as an expression, stays with the application: take it out of the query and order the selection yourself, as before.

To show less of an entity than it has, such as to hide a column the application itself reads, name the fields:

```php
#[Resource(Entity\File::class, fields: ['id', 'name', 'fileName', 'fileSize'])]
final class FileResource
{
    /** @param list<VersionResource> $versions */
    public function __construct(public array $versions = []) {}
}
```

A condition in `entities.neon` is SQL which Nette quotes on its way: write keywords in capitals, as in `deleted = FALSE`, because a lowercase `false` is taken for a column.

## 6. Queries

The format of queries stays, including `criteria`, `take`, `skip` and the operators of Kendo UI. The shape of responses stays the one of `pagination: true`: `{data, total, page, pageSize, from, to}` at every level, and a list with `skipLimitOffset`.

Other differences:

- `maxRows` (10000 by default) limits rows of collections in one response, and a query wanting more gets 400. `skipLimitOffset` turns it off.
- `maxDepth` (3 by default) limits how deep `select` and filter paths go. Raise it if clients go deeper.
- A filter through a collection is "has a record matching this", and a record comes once. A record with no related row doesn't match `role.name isnull`.
- A limit of `restrict()` holds in filters and sorts through relations too, which `onSelection` never reached.
- `isempty` means null or an empty text. `isweek` with a number means that week of the current year.
- Values are checked: text for a number gets 400, and so does `contains` on anything but text.
- Search looks for every word in one column. `ignoreDiacritics: true` replaces the old automatic `unaccent` on PostgreSQL, whose extension has to be created in advance.

## 7. Documentation

Responses are described by objects of `Phlox\ApiCore\Schema`, see [Describing a response](https://github.com/phloxcz/api-core#describing-a-response). The older notation fails when the container compiles, naming the method and what replaces it:

| Old | New |
|---|---|
| `['object', ['id' => 'int']]` | `new Shape(['id' => 'int'])`, with `'id?'` for a field that can be missing |
| `['array', 'string']` | `new ListOf('string')` |
| `['@pager', Foo::class]` | `new Paged(Foo::class)` |
| `['object', ['value' => ['@pager', Foo::class]]]` | `new Shape(['value' => new Paged(Foo::class)])` |
| `'@binary'` | `new Binary` |
| `#[Response(404, '@error')]` | `#[Response(404)]`, as a code from 400 gets the schema of errors |
| `'list<Foo>'`, `'Foo'` | `new ListOf(Foo::class)`, `Foo::class` |
| `#[Parameter('x', ...)]` | the type of the parameter, `@param list<Foo> $x`, or `#[Schema]` on it |

`@return` isn't read for documentation. Where it described a response, move that to `#[Response]`, or `#[Result]` in JSON-RPC:

```php
#[Response(200, new Shape(['value' => UserResource::class]))]
public function detail(int $id, #[QueryOf(UserResource::class, single: true)] ?Query $query = null): array
```

Without `#[Response]`, `#[QueryOf]` gives the shape that `$this->processor->process()` returns, a page or one record.

Delete a controller which only served a generated file.

## 8. Firebird

Add `options: {driverClass: Phlox\FirebirdDatabase\FirebirdDriver}` to the connection. Read its [README](https://github.com/phloxcz/firebird-database/blob/master/README.md), especially about integers written to `NUMERIC` columns, which may have left wrong values in your data.

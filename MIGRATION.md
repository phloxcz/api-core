# Migrating from abrad450/api-server-nette

This guide takes a project from `abrad450/api-server-nette` and `abrad450/mapped-database-nette` to the Phlox libraries. It's written for whoever does the migration. Details of each library are in its README, and [`phlox-api-sample`](../phlox-api-sample) shows the result.

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
    entities:                       # see step 5
        App\Entity\Project:
            relations: [createdBy, drawings]

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
| `AbraD450\ApiServer\Common\Attributes\Parameter` | `Phlox\ApiCore\Attributes\Parameter` |
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

    public function startup(): void
    {
        parent::startup();
        $this->processor->onProcessedEntity[] = [$this, 'onProcessedEntity'];
    }
}
```

The rest stays: `process($selection, $query)`, `process($entity, $query)`, `skipLimitOffset: true`, `#[FromBody] ?Query $query = null`, `Query::create([...])`, routes, `$this->response`, `$this->terminate()`, `readfile()` and `print`, and events with `set`, `unset` and `shouldSkip`.

To check in each controller:

| What | Why |
|---|---|
| `#[Authorize(['a', 'b'])]` | means a **or** b now, where it meant both. For both, write `#[Authorize(['a', 'b'], all: true)]` |
| A method with `#[Route]` and no `#[HttpGet]` or another verb | doesn't compile. With neither, it isn't an endpoint and gives 404 |
| A public method inherited from a base controller | is no longer an endpoint; override it |
| `new ApiException(400, 'x', null, 400)` | the fourth argument is `$previous` now, use `httpCode: 400` |
| A code you throw without `httpCode:` | gives 500, as before |
| `#[Consumes('application/pdf')]` | is enforced: another type gets 415, where it used to pass. Check what clients really send |
| `$sortList->count()`, `new SortArrayList()` | sorts are a plain array now: `count()`, `[]`. `setSort()`, `$sort->field` and `$sort->dir` stay |
| `$query->getCriteriaByPath()` returning null | stays so; a missing query reaches your code as an empty `Query` only through a parameter, so `?Query` can still be null in a method you call yourself |

A status can change where errors used to be 500: a wrong verb gets 405 with `Allow`, malformed JSON 400, invalid parameters 422, and a method returning nothing 204 unless it wrote the response itself.

## 5. Entities and what a query can reach

Generate entities with `phloxcz/entity-database`, from the database and an `entities.neon`. **Mark every secret `access: w` there:** a password, PIN or token masked only by `onProcessedEntity` can still be filtered by, so a logged-in client can guess it character by character. That holds in the old library as well. After that, drop the masking, because reading a write-only property throws.

Relations have to be allowed, and `apiQuery.entities` does it for the whole application, so controllers need no change:

```neon
apiQuery:
    entities:
        App\Entity\Project:
            relations: [createdBy, drawings, subjects, users, entries]
        App\Entity\Entry:
            relations: [assignedUser, createdBy, status, type, files]
            computed:                # what onProcessedEntity adds with `set`
                canManage: bool
                nodePath: string
```

To get the list, look at what clients have in `select`. **A name in `select`, `filter` or `sort` that isn't known now gets 400,** where it used to be skipped silently, also in a filter, which then returned more rows than asked for. So a client with a stale field name or a column name such as `created_at` fails visibly, and has to be fixed. `computed` is what makes a field from an event known.

Sorting by something that isn't a field, such as an expression, stays with the application: take it out of the query and order the selection yourself, as before.

To show less of an entity than it has, such as to hide a column the application itself reads, use a resource. Most entities need one line, and `resource:` in the configuration makes it hold everywhere, so controllers need no change here either:

```php
#[Resource(Entity\Type::class, fields: '*')]
final class TypeResource {}

#[Resource(Entity\File::class, fields: ['id', 'name', 'fileName', 'fileSize'])]
final class FileResource
{
    /** @param list<VersionResource> $versions */
    public function __construct(public array $versions = []) {}
}
```

```neon
apiQuery:
    entities:
        App\Entity\File:
            resource: App\ApiV1Module\Resources\FileResource
```

A condition in `entities.neon` is SQL which Nette quotes on its way: write keywords in capitals, as in `deleted = FALSE`, because a lowercase `false` is taken for a column.

## 6. Queries

The format of queries stays, including `criteria`, `take`, `skip` and the operators of Kendo UI. The shape of responses stays the one of `pagination: true`: `{data, total, page, pageSize, from, to}` at every level, and a list with `skipLimitOffset`.

Other differences:

- `maxRows` (10000 by default) limits rows of collections in one response, and a query wanting more gets 400. `skipLimitOffset` turns it off.
- `maxDepth` (3 by default) limits how deep `select` and filter paths go. Raise it if clients go deeper.
- A filter through a collection is "has a record matching this", and a record comes once. A record with no related row doesn't match `role.name isnull`.
- A filter through a relation doesn't go through `onSelection`, so state a limit which must hold there as `condition:` of the relation.
- `isempty` means null or an empty text. `isweek` with a number means that week of the current year.
- Values are checked: text for a number gets 400, and so does `contains` on anything but text.
- Search looks for every word in one column. `ignoreDiacritics: true` replaces the old automatic `unaccent` on PostgreSQL, whose extension has to be created in advance.

## 7. Documentation

`#[Response]` and `#[Parameter]` keep working, including `['object', [...]]`, `['@pager', Foo::class]`, `@binary` and `@error`. Both, and the array notation, can go gradually in favour of native types, docblocks and DTOs, which validate as well as document:

```php
/** @return array{value: UserResource} */
public function detail(int $id, #[QueryOf(UserResource::class, single: true)] ?Query $query = null): array
```

Delete a controller which only served a generated file.

## 8. Firebird

Add `options: {driverClass: Phlox\FirebirdDatabase\FirebirdDriver}` to the connection. Read its [README](../phlox-firebird-database/README.md), especially about integers written to `NUMERIC` columns, which may have left wrong values in your data.

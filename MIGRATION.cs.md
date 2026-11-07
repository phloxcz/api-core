# Přechod z abrad450/api-server-nette

[English](MIGRATION.md) | **Česky**

Tento návod převede projekt z `abrad450/api-server-nette` a `abrad450/mapped-database-nette` na knihovny Phlox. Je určený tomu, kdo přechod provádí. Podrobnosti o každé knihovně jsou v jejím README a výsledek ukazuje [`phlox-api-sample`](https://github.com/phloxcz/api-sample/blob/master/README.cs.md).

Kontrolery zůstávají z velké části stejné. Nejvíc práce je s konfigurací, přesunem entit a kontrolou chování, které se zpřísnilo.

## 1. Balíčky

```bash
composer remove abrad450/api-server-nette abrad450/mapped-database-nette
composer require phloxcz/rest-api phloxcz/api-query phloxcz/entity-database
composer require phloxcz/rpc-api             # s JSON-RPC
composer require phloxcz/firebird-database   # s Firebirdem
```

`index.php` musí volat `Application::run()`. Tam API převezme řízení, až doběhne bootstrap a všechna rozšíření. **Kód za `createContainer()` se teď spouští i pro požadavky na API,** včetně věcí jako přesměrování nebo režim údržby.

## 2. Konfigurace

```neon
extensions:
    entityDatabase: Phlox\EntityDatabase\DI\Extension
    apiQuery: Phlox\ApiQuery\DI\Extension
    restApi: Phlox\RestApi\DI\Extension

apiQuery:
    maxPageSize: 500
    entities:                       # viz krok 5
        App\Entity\Project:
            relations: [createdBy, drawings]

restApi:
    endpoints:
        'api/v1':
            namespace: App\ApiV1Module
            authorize: false        # jen pokud API opravdu má veřejné metody bez #[AllowAnonymous]
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

| Staré | Nové |
|---|---|
| `apiServer: query:` | `apiQuery:` |
| `apiServer: rest: <name>` | `restApi: endpoints: <name>` |
| `apiServer: rpc: <name>` | `rpcApi: endpoints: <name>` |
| `openapi: content: info`, `servers` | `documentation: info`, `servers` |
| `openapi: content: components: securitySchemes` | `documentation: securitySchemes` |
| `openapi: content:` cokoli dalšího | `documentation: extra` |
| `openapi: outputPath` a příkaz generující YAML | `$container->getByType(OpenApi::class)->toYaml('api/v1')`, nebo zveřejnění přes `expose: true` a odstranění kontroleru, který soubor servíroval |
| `query: pagination: true` | platí vždy, není co nastavovat |

**`allowedOrigins: ['*']` s `allowCredentials: true` se už nezkompiluje.** Prohlížeče tuhle kombinaci odmítají a stará knihovna to obcházela tím, že posílala zpět origin libovolného webu. Ten pak mohl číst odpovědi s cookies přihlášeného uživatele. Klient, který se přihlašuje hlavičkou `Authorization`, tu žádné credentials nepotřebuje, takže mu stačí `allowedOrigins: ['*']` s `allowCredentials: false`. Pro cookies vyjmenujte originy.

**Autorizace je ve výchozím stavu zapnutá.** Metoda bez `#[Authorize]` i `#[AllowAnonymous]` teď vyžaduje přihlášení. U endpointu chráněného jinde, třeba za bránou, nastavte `authorize: false`.

## 3. Jmenné prostory

| Staré | Nové |
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

## 4. Kontrolery

`$this->processor` pochází z traitu. Dejte ho do společného základního kontroleru:

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

Zbytek zůstává: `process($selection, $query)`, `process($entity, $query)`, `skipLimitOffset: true`, `#[FromBody] ?Query $query = null`, `Query::create([...])`, routy, `$this->response`, `$this->terminate()`, `readfile()` a `print` a události se `set`, `unset` a `shouldSkip`.

V každém kontroleru zkontrolujte:

| Co | Proč |
|---|---|
| `#[Authorize(['a', 'b'])]` | teď znamená a **nebo** b, dřív obě. Pro obě napište `#[Authorize(['a', 'b'], all: true)]` |
| Metoda s `#[Route]` bez `#[HttpGet]` nebo jiného slovesa | nezkompiluje se. Bez obojího není endpoint a vrací 404 |
| Veřejná metoda zděděná ze základního kontroleru | už není endpoint; přepište ji |
| `new ApiException(1001, 'x')` | HTTP stav je první: `new ApiException(409, 'x', errorCode: 1001)`, nebo jen `new ApiException(404, 'x')`, kde je kódem stav. Kód mimo `400`–`599` skončí chybou se správným zápisem ve zprávě. `internal:` pojmenujte, protože třetí argument je `errorCode` |
| `#[Throws(1001, 'x', httpCode: 409)]` | `#[Throws(409, 'x', errorCode: 1001)]`, ve stejném pořadí jako `ApiException` |
| `ApiException::E_INVALID_PARAMS` a ostatní konstanty | `ProtocolError::InvalidParams` a ostatní případy; kód je `->value` |
| `Authorizer::E_UNAUTHORIZED`, `Authorizer::E_FORBIDDEN` | `401`, `403` |
| `#[Consumes('application/pdf')]` | vynucuje se: jiný typ dostane 415, dřív prošel. Ověřte, co klienti skutečně posílají. Bez `#[FromBody]` zůstane tělo nenačtené, aby ho metoda mohla streamovat |
| `$sortList->count()`, `new SortArrayList()` | řazení je teď obyčejné pole: `count()`, `[]`. `setSort()`, `$sort->field` a `$sort->dir` zůstávají |
| `$query->getCriteriaByPath()` vracející null | zůstává; chybějící dotaz je prázdný `Query` jen přes parametr, takže `?Query` může být null v metodě, kterou voláte sami |

Stav se může změnit tam, kde dřív byla chyba 500: špatné sloveso dostane 405 s `Allow`, chybný JSON 400, neplatné parametry 422 a metoda, která nic nevrací, 204, pokud odpověď nezapsala sama.

## 5. Entity a na co se dotaz dostane

Entity vygenerujte pomocí `phloxcz/entity-database` z databáze a `entities.neon`. **Každé tajemství tam označte `access: w`:** podle hesla, PINu nebo tokenu maskovaného jen v `onProcessedEntity` se pořád dá filtrovat, takže ho přihlášený klient může uhodnout znak po znaku. Platí to i ve staré knihovně. Maskování pak odstraňte, protože čtení vlastnosti jen pro zápis vyhodí výjimku.

Relace musí být povolené a `apiQuery.entities` to zařídí pro celou aplikaci, takže kontrolery není potřeba měnit:

```neon
apiQuery:
    entities:
        App\Entity\Project:
            relations: [createdBy, drawings, subjects, users, entries]
        App\Entity\Entry:
            relations: [assignedUser, createdBy, status, type, files]
            computed:                # co přidává onProcessedEntity přes `set`
                canManage: bool
                nodePath: string
```

Seznam zjistíte podle toho, co mají klienti v `select`. **Název v `select`, `filter` nebo `sort`, který není známý, teď dostane 400.** Dřív se tiše přeskočil, i ve filtru, který pak vrátil víc řádků, než měl. Klient se zastaralým názvem pole nebo názvem sloupce jako `created_at` tedy viditelně selže a je potřeba ho opravit. Pole z události udělá známým právě `computed`.

Řazení podle něčeho, co není pole, třeba podle výrazu, zůstává na aplikaci: vyjměte ho z dotazu a seřaďte selection sami jako dřív.

Chcete-li ukázat z entity méně, než má, třeba skrýt sloupec, který aplikace sama čte, použijte Resource. Většině entit stačí jeden řádek a `resource:` v konfiguraci zajistí, že platí všude, takže ani tady není potřeba měnit kontrolery:

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

Podmínka v `entities.neon` je SQL, které Nette cestou obaluje: klíčová slova pište velkými písmeny, například `deleted = FALSE`, protože malé `false` se vezme jako sloupec.

## 6. Dotazy

Formát dotazů zůstává, včetně `criteria`, `take`, `skip` a operátorů Kendo UI. Tvar odpovědí zůstává jako u `pagination: true`: `{data, total, page, pageSize, from, to}` na každé úrovni a seznam se `skipLimitOffset`.

Další rozdíly:

- `maxRows` (výchozí 10000) omezuje řádky kolekcí v jedné odpovědi a dotaz, který chce víc, dostane 400. `skipLimitOffset` ho vypíná.
- `maxDepth` (výchozí 3) omezuje hloubku `select` a cest ve filtrech. Zvyšte ho, pokud klienti jdou hlouběji.
- Filtr přes kolekci znamená „má záznam odpovídající podmínce" a každý záznam přijde jednou. Záznam bez navázaného řádku neodpovídá `role.name isnull`.
- Filtr přes relaci neprochází přes `onSelection`, takže omezení, které tam musí platit, uveďte jako `condition:` relace.
- `isempty` znamená null nebo prázdný text. `isweek` s číslem znamená daný týden aktuálního roku.
- Hodnoty se kontrolují: text místo čísla dostane 400, stejně jako `contains` na čemkoli jiném než textu.
- Vyhledávání hledá každé slovo v jednom sloupci. `ignoreDiacritics: true` nahrazuje staré automatické `unaccent` na PostgreSQL, jehož rozšíření musí být vytvořené předem.

## 7. Dokumentace

Odpovědi se popisují objekty z `Phlox\ApiCore\Schema`, viz [Popis odpovědi](https://github.com/phloxcz/api-core/blob/master/README.cs.md#popis-odpovědi). Starší zápis při kompilaci kontejneru skončí chybou, která uvede metodu a čím zápis nahradit:

| Staré | Nové |
|---|---|
| `['object', ['id' => 'int']]` | `new Shape(['id' => 'int'])`, s `'id?'` pro pole, které může chybět |
| `['array', 'string']` | `new ListOf('string')` |
| `['@pager', Foo::class]` | `new Paged(Foo::class)` |
| `['object', ['value' => ['@pager', Foo::class]]]` | `new Shape(['value' => new Paged(Foo::class)])` |
| `'@binary'` | `new Binary` |
| `#[Response(404, '@error')]` | `#[Response(404)]`, protože kód od 400 dostane schéma chyb |
| `'list<Foo>'`, `'Foo'` | `new ListOf(Foo::class)`, `Foo::class` |
| `#[Parameter('x', ...)]` | typ parametru, `@param list<Foo> $x`, nebo `#[Schema]` u něj |

`@return` se pro dokumentaci nečte. Kde popisoval odpověď, přesuňte to do `#[Response]`, v JSON-RPC do `#[Result]`:

```php
#[Response(200, new Shape(['value' => UserResource::class]))]
public function detail(int $id, #[QueryOf(UserResource::class, single: true)] ?Query $query = null): array
```

Bez `#[Response]` dodá `#[QueryOf]` tvar, který vrací `$this->processor->process()`, tedy stránku nebo jeden záznam.

Kontroler, který jen servíroval vygenerovaný soubor, smažte.

## 8. Firebird

Do připojení přidejte `options: {driverClass: Phlox\FirebirdDatabase\FirebirdDriver}`. Přečtěte si jeho [README](https://github.com/phloxcz/firebird-database/blob/master/README.cs.md), hlavně část o celých číslech zapsaných do sloupců `NUMERIC`, kvůli kterým můžete mít v datech špatné hodnoty.

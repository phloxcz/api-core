# phloxcz/api-core

[English](README.md) | **Česky**

**Společné jádro API knihoven Phlox pro [Nette](https://nette.org).**

Tento balíček se obvykle neinstaluje přímo. Přijde spolu s knihovnou pro konkrétní protokol:

- [`phloxcz/rpc-api`](https://github.com/phloxcz/rpc-api/blob/master/README.cs.md) – JSON-RPC 2.0
- [`phloxcz/rest-api`](https://github.com/phloxcz/rest-api/blob/master/README.cs.md) – REST

[`phloxcz/api-query`](https://github.com/phloxcz/api-query/blob/master/README.cs.md) k oběma přidává dotazovací jazyk. [`phlox-api-sample`](https://github.com/phloxcz/api-sample/blob/master/README.cs.md) ukazuje všechno dohromady a [`MIGRATION.cs.md`](MIGRATION.cs.md) popisuje přechod z `abrad450/api-server-nette`.

> ⚠️ Balíček se aktivně vyvíjí. API zatím není stabilní.

## Požadavky

PHP 8.4+, `nette/di`, `nette/http`, `nette/schema`, `nette/security`, `nette/utils`.

Volitelně: `tracy/tracy` pro logování neočekávaných výjimek (jinak se použije `error_log()`), `nette/application` pro `$this->linkGenerator`.

## API kontrolery

Kontroler je běžná služba v DI kontejneru, která dědí z `ApiController`:

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
        parent::startup(); // povinné
        // běží před autorizací, takže tu lze přihlásit uživatele podle tokenu
    }

    public function read(int $id): array { /* ... */ }

    #[AllowAnonymous]
    public function ping(): string { return 'pong'; }

    #[NonApi]
    public function notCallable(): void { /* ... */ }
}
```

Kontroler má k dispozici `$this->request`, `$this->response`, `$this->user`, `$this->session`, `$this->linkGenerator`, `$this->controllerName`, `$this->serviceName`, `$this->methodName`, `$this->parameters`, `$this->jsonData` a `$this->terminate()`, které ukončí volání bez odpovědi API.

### Co je vystavené

Veřejné metody deklarované přímo ve třídě kontroleru nebo v jejích traitech. Vystavené **nejsou** zděděné metody (aby byla vystavená, je potřeba ji přepsat), statické metody, `startup()`, magické metody, metody `inject*()` a cokoli s `#[NonApi]`.

### Autorizace

Autorizace je ve výchozím stavu zapnutá: metoda bez atributu vyžaduje přihlášeného uživatele. Výchozí chování lze pro endpoint změnit pomocí `authorize: false`.

| Situace | Výsledek |
|---|---|
| `#[AllowAnonymous]` na metodě | vždy veřejná |
| `#[Authorize]` na metodě, třídě nebo některém předkovi | vyžaduje přihlášení a musí platit každý takový atribut |
| `#[AllowAnonymous]` na třídě a neplatí žádné `#[Authorize]` | veřejná |
| nic z výše uvedeného | výchozí nastavení endpointu |

Atribut je splněný, když má uživatel **alespoň jednu** z jeho rolí, nebo všechny, pokud je uvedeno `all: true`:

```php
#[Authorize(['admin', 'editor'])]               // admin nebo editor
class ArticleController extends ApiController
{
    public function read(): array { /* admin or editor */ }

    #[Authorize('publisher')]                   // (admin nebo editor) a publisher
    public function publish(): void { /* ... */ }

    #[Authorize(['legal', 'audit'], all: true)] // (admin nebo editor) a legal a audit
    public function purge(): void { /* ... */ }
}
```

Atribut na metodě může to, co dovoluje kontroler, jen zúžit. Aby byla metoda otevřená všem, použijte `#[AllowAnonymous]`.

Nepřihlášený uživatel dostane kód `401`, přihlášený bez potřebné role `403`.

### Pořadí volání

Inject → `startup()` → autorizace → vázání parametrů → metoda. Parametry se vážou až po autorizaci, takže neoprávněný klient se o signatuře metody nic nedozví.

## Parametry

Hodnoty se převádějí podle nativních typů parametrů metody. Hodnota, která nesedí, skončí chybou „invalid params", ne `TypeError` z PHP.

| Typ | Přijímá |
|---|---|
| `int` | celá čísla, necelá čísla bez desetinné části, celá čísla v textu (`"1.5"` se odmítne) |
| `float` | čísla, čísla v textu |
| `string` | texty, čísla (`true` se odmítne) |
| `bool` | `true`, `false`, `1`, `0`, `"1"`, `"0"`, `"true"`, `"false"` |
| `array` | pole |
| `object` | objekty JSON, text obsahující objekt JSON |
| `\DateTime`, `\DateTimeImmutable`, `\DateTimeInterface` | datum v textu; nemožné datum jako `2024-13-45` se odmítne |
| backed enumy | platnou hodnotu enumu |
| třídy implementující `ApiConstructible` | cokoli, co přijme `apiConstruct()`; hodnotu odmítnete vyhozením `\InvalidArgumentException` |
| sjednocené typy | typ, kterému hodnota přesně odpovídá, jinak první, na který ji lze převést |

Chybějící parametr dostane výchozí hodnotu, nebo `null`, pokud to typ dovoluje. Jinak je to chyba.

Docblock jako `@param int[] $ids` nemění, co se přijme, pokud v něm není DTO.

## DTO

Třída s `#[Dto]` popisuje data API. Jako parametr se sestaví z požadavku. Když ji metoda vrátí, převede se na JSON. V obou případech se objeví v generované dokumentaci.

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

Pole jsou parametry konstruktoru a pak ostatní veřejné vlastnosti. Hodnoty se převádějí stejně jako parametry metod a fungují i vnořená DTO, enumy a data. Seznam DTO bere typ z docblocku: `@param list<Foo> $x` u konstruktoru nebo `@var list<Foo>` nad vlastností.

Pole, která třída nedeklaruje, se ignorují, takže klient nemůže nastavit nic jiného. Konstruktor může hodnoty odmítnout vyhozením `\InvalidArgumentException`.

V odpovědi se posílají veřejné vlastnosti a data uvnitř DTO se převedou na text ve formátu ISO 8601. Cokoli, co není DTO, se posílá beze změny, včetně data v obyčejném poli.

### Kolekce

PHP umí napsat jen `array`, typ položek se proto bere z docblocku:

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

V `$import->tasks` pak jsou instance `CreateTask`, každá zkontrolovaná podle vlastních pravidel. Chyba má cestu s pozicí řádku, třeba `tasks.1.title`, a metoda se nezavolá, dokud není všechno v pořádku. `#[Count]` omezuje počet položek a je dobré ho uvádět, aby klient nemohl poslat milion řádků.

Bez DTO v typu se docblock nevynucuje: `list<string>` u parametru metody zůstává jen dokumentací. V DTO se vynucuje.

### Změna jen toho, co přišlo

DTO musí být celé, takže nerozliší pole, které nepřišlo, od pole poslaného jako `null`. `Patch` to umí. Pole a pravidla bere z DTO uvedeného v `#[PatchOf]`, takže jedno DTO stačí pro založení i změnu:

```php
public function change(int $id, #[FromBody, PatchOf(SaveType::class)] Patch $changes): array
{
    $type = $this->findType($id);
    $changes->applyTo($type);       // nastaví, co přišlo, do vlastností stejného jména
    $type->saveModified();
    // ...
}
```

| Metoda | Vrací |
|---|---|
| `has('name')` | zda pole přišlo, i jako `null` |
| `get('name', $default)` | jeho převedenou hodnotu |
| `toArray()` | co přišlo, pod názvy z DTO |
| `applyTo($object)` | nastaví, co přišlo, do objektu a vrátí ho |

Žádné pole není povinné, ale hodnota, která přijde, musí splnit pravidla: typy, omezení a `null` jen tam, kde ho DTO dovoluje. Pole, která DTO nedeklaruje, se ignorují. Vnořené DTO musí být celé, takže změna seznamu nahradí celý seznam.

Samotné DTO nevzniká, takže se nespustí kontroly, které jeho konstruktor provádí vyhozením `\InvalidArgumentException`, ani pravidla napříč více poli. V dokumentaci je uvedené jako schéma DTO bez `required`, s názvem jako `SaveType_Patch`.

Třída s `#[Resource]` z `phloxcz/api-query` říká, co jde ven, a požadavek přijmout nemůže, ani přes `Patch`. Pokus o to vyhodí výjimku pro vývojáře, protože by se jinak tiše zahodilo, co klient poslal.

### Omezení

Fungují stejně na polích DTO i na parametrech metod a objeví se i v dokumentaci. `null` se nekontroluje, jestli je povolený, určuje typ.

| Atribut | Platí pro | JSON Schema |
|---|---|---|
| `#[Range(min: 1, max: 9)]` | čísla | `minimum`, `maximum` |
| `#[Length(min: 1, max: 9)]` | texty, ve znacích | `minLength`, `maxLength` |
| `#[Count(min: 1, max: 9)]` | pole | `minItems`, `maxItems` |
| `#[Pattern('^[A-Z]+$')]` | texty; bez ukotvení, jako v JSON Schema | `pattern` |
| `#[Format('email')]` | texty: `email`, `uri`, `uuid`, `date`, `date-time`, `ipv4`, `ipv6` | `format` |

Neplatný výraz nebo neznámý formát selže při kompilaci kontejneru.

### Neplatná data

Všechny problémy se hlásí najednou. Každý má svou cestu, a když je jen jeden, zpráva rovnou říká, co se stalo:

```json
{"code": -32602, "message": "Invalid method parameter(s).", "fields": {
    "input.name": "must have at least 2 characters.",
    "input.addresses.1.city": "is required."
}}
```

## Dokumentace

Oba protokoly generují dokument při kompilaci kontejneru ze stejných atributů a typů, podle kterých běží. Není potřeba nic ručně udržovat.

| Zdroj | Výsledek |
|---|---|
| nativní typy, `@param`, `@var` | schémata parametrů a polí, včetně `list<Foo>`, `array<string, int>`, `array{id: int}` a sjednocených typů |
| nativní návratový typ | schéma odpovědi |
| `#[Response]` v REST, `#[Result]` v JSON-RPC | odpověď, kterou návratový typ neřekne, viz [Popis odpovědi](#popis-odpovědi) |
| první odstavec docblocku, zbytek | shrnutí, popis |
| text u `@param` | popisy |
| `#[Dto]`, backed enumy | sdílená schémata |
| omezení | klíčová slova schématu |
| `#[Authorize]`, `#[AllowAnonymous]` | `x-roles`, popis, chyby `401` a `403` |
| `@deprecated`, `#[\Deprecated]` | `deprecated` |
| `#[Throws(409, 'Order is closed.')]`, `#[Throws(OrderError::Closed)]` | dokumentované chyby ve stejném tvaru jako u `ApiException`; na třídě platí pro všechny její metody |
| `#[Schema([...])]` | klíčová slova JSON Schema přidaná k parametru nebo poli; na třídě `ApiConstructible` schéma jejích hodnot |

U parametru nebo pole docblock jen zpřesňuje typ, který signatura nechává otevřený, jako `array` nebo `mixed`. Takový, který nejde přečíst, se ignoruje. Třída, která není DTO, se dokumentuje jako obyčejný objekt.

### Popis odpovědi

Odpověď se popisuje na jednom místě. Je to nativní návratový typ, nebo atribut tam, kde typ říká málo, třeba `array`. `@return` se nečte, takže zůstává pro PHPStan.

```php
use Phlox\ApiCore\Schema\{ListOf, Paged, Shape};

#[Response(200, new Shape(['value' => new Paged(User::class)]))]
public function list(?Query $query = null): array

#[Result(new ListOf(User::class), description: 'The users')]   // JSON-RPC
public function users(): array
```

| Popis | JSON |
|---|---|
| `User::class` | ta třída: DTO, enum, entita nebo resource |
| `'int'`, `'float'`, `'string'`, `'bool'`, `'mixed'` | skalár; `'?string'` může být null |
| `new ListOf(User::class)` | `[...]` |
| `new AnyOf('int', 'string', User::class)` | hodnota kteréhokoli z nich |
| `new MapOf('int')` | `{"open": 3, "done": 5}` |
| `new Shape(['id' => 'int', 'note?' => '?string'])` | objekt; pole v něm je vždy, pokud jeho název nekončí `?` |
| `new Paged(User::class)` | stránka: `{data, total, page, pageSize, from, to}` |
| `new Binary` | bajty souboru |
| `new JsonSchema(['type' => 'string', 'format' => 'date'])` | JSON Schema tak, jak je |

Dají se do sebe vnořovat, třeba `new Shape(['ids' => new ListOf('int')])`. Třída se uvádí přes `::class`, takže ji IDE naimportuje, napoví a přejmenuje. Text jako `'User'` nebo `'list<User>'` při kompilaci kontejneru skončí chybou, stejně jako zápis polem ze starších verzí, a chyba řekne, co napsat místo toho.

Otazník znamená dvě různé věci. `'note?' => 'string'` může chybět, kdežto `'note' => '?string'` je vždy, ale může být null.

Stránku skládá `Paged::result($rows, $total, $offset, $pageSize)`. Takhle ji skládá procesor dotazů a takhle ji vrátí i vaše vlastní stránkování, takže stránka a její popis se nemůžou rozejít.

Strukturu, kterou mají všechny odpovědi projektu, třeba obálku, stačí popsat jednou, implementací `SchemaDefinition`:

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

V konfiguraci stačí nechat jen to, co nejde vyčíst z kódu:

```neon
documentation:
    enabled: true           # zda dokument vůbec generovat
    expose: true            # zda ho zveřejnit; ve výchozím stavu jen v debug režimu
    info:
        title: Orders API   # výchozí je "<endpoint> API"
        version: 2.1.0      # výchozí je 1.0.0
    servers:
        - url: https://api.example.com/api
    extra:                  # sloučí se do dokumentu, třeba tagy nebo x-*
        x-owner: team-a
```

Dokumenty lze číst i z aplikace, což se hodí pro zápis do souboru v CI:

```php
$container->getByType(Phlox\RestApi\OpenApi::class)->toYaml('api');
$container->getByType(Phlox\RpcApi\OpenRpc::class)->toJson('api');
```

Pokud má dokument vidět jen někdo, nechte `expose` vypnuté a vracejte ho z vlastního kontroleru s `#[Authorize]`.

## Chyby

Chybu pro klienta nahlásíte vyhozením `ApiException` s HTTP stavem a zprávou:

```php
throw new ApiException(404, 'The task was not found.');
throw new ApiException(409, 'The order is closed.', errorCode: 1001, internal: "Closed on {$date}", data: ['orderId' => $id]);
```

Klient dostane `{"error": {"code": ..., "message": ...}}`. `code` je `errorCode`, nebo stav tam, kde stačí, takže `errorCode` slouží jen k rozlišení chyb se stejným stavem. Zpráva a data jsou veřejné, interní zpráva se přidává jen v debug režimu. Jakákoli jiná výjimka se zaloguje a klient dostane obecnou interní chybu.

Stav je první a musí být od `400` do `599`. Cokoli jiného skončí chybou, třeba kód na jeho místě jako ve starších verzích, a hláška řekne, jak to napsat.

Chyby, které aplikace hlásí na více místech, můžou být enum. Ten drží kód, zprávu i stav na jednom místě, pro vyhození i pro dokumentaci:

```php
enum OrderError: int implements ApiError
{
    #[ErrorCase(409, 'The order is closed.')]
    case Closed = 1001;

    #[ErrorCase(404, 'The order was not found.')]
    case NotFound = 1002;
}

throw new ApiException(OrderError::Closed);
throw new ApiException(OrderError::NotFound, "Order {$id} was not found.");   // vlastní zpráva

#[Throws(OrderError::Closed)]
public function close(int $id): void { /* ... */ }
```

Chyby samotného protokolu jsou `ProtocolError` s kódy JSON-RPC 2.0, třeba `ProtocolError::InvalidParams` pro `-32602`. JSON-RPC hlásí tyto kódy, REST místo nich HTTP stav.

Kde stav nic neznamená, třeba v metodě, kterou volá jen JSON-RPC, uveďte samotný kód: `new ApiException(errorCode: 1001, message: 'The order is closed.')` a `#[Throws(errorCode: 1001, message: '...')]`. REST by na takovou chybu odpověděl `500`.

## Kdy se API spouští

S `nette/application` převezme server řízení v `Application::$onStartup`: bootstrap i všechna rozšíření už doběhly, ať je jejich pořadí jakékoli, a požadavek, který API obslouží, tím končí. `index.php` proto musí volat `Application::run()`, jak to obvykle dělá. Bez `nette/application` se to děje v `initialize()` kontejneru a kód za `createContainer()` se pro takový požadavek nespustí.

## Licence

[MIT](LICENSE)

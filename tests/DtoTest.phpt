<?php

/**
 * DTOs and constraints: building them from requests and turning them into JSON.
 */

declare(strict_types=1);

use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\Attributes\Constraints\Count;
use Phlox\ApiCore\Attributes\Constraints\Format;
use Phlox\ApiCore\Attributes\Constraints\Length;
use Phlox\ApiCore\Attributes\Constraints\Pattern;
use Phlox\ApiCore\Attributes\Constraints\Range;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Binding\DtoSerializer;
use Phlox\ApiCore\Binding\InvalidParamsException;
use Phlox\ApiCore\Binding\ParameterBinder;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}

#[Dto]
final readonly class Address
{
    public function __construct(
        #[Length(min: 1)] public string $city,
        public ?string $zip = null,
    ) {
    }
}

#[Dto]
final readonly class UserInput
{
    /**
     * @param list<Address> $addresses
     * @param array<string, Address> $byName
     */
    public function __construct(
        #[Length(min: 2, max: 5)] public string $name,
        #[Format('email')] public string $email,
        #[Range(min: 18)] public ?int $age = null,
        public Role $role = Role::User,
        public ?DateTimeImmutable $born = null,
        public ?Address $home = null,
        #[Count(max: 2)] public array $addresses = [],
        public array $byName = [],
    ) {
    }
}

#[Dto]
final class WithProperties
{
    public string $required;

    public int $optional = 5;

    public ?string $nullable = null;

    private string $secret = 'hidden';

    public static string $static = 'static';

    public function getSecret(): string
    {
        return $this->secret;
    }
}

#[Dto]
final readonly class Refusing
{
    public function __construct(public int $from, public int $to)
    {
        if ($from > $to) {
            throw new InvalidArgumentException('from must not be after to.');
        }
    }
}

#[Dto]
final class Tree
{
    /**
     * @param list<Tree> $children
     */
    public function __construct(public string $name, public array $children = [])
    {
    }
}

#[Dto]
final class Empty_
{
}

#[Dto]
abstract class AbstractDto
{
}

final class Money implements ApiConstructible
{
    public static function apiConstruct(mixed $value)
    {
        return new self();
    }
}

final class Target
{
    public function user(UserInput $input): void {}

    /** @param list<Address> $list */
    public function list(array $list): void {}

    /** @param int[] $ids */
    public function sloppy(array $ids): void {}

    public function constrained(#[Range(1, 10)] int $page = 1, #[Pattern('^[a-z]+$')] ?string $slug = null): void {}

    public function two(int $a, UserInput $input, int $b): void {}

    public function properties(WithProperties $v): void {}

    public function refusing(Refusing $v): void {}

    public function tree(Tree $v): void {}

    public function abstract(AbstractDto $v): void {}
}

$binder = new ParameterBinder();
$bind = static fn(string $method, array $values, array $prebound = []): array =>
    $binder->bind(new ReflectionMethod(Target::class, $method), $values, $prebound);

$errors = static function (string $method, array $values, array $prebound = []) use ($bind): array {
    $e = Assert::exception(static fn() => $bind($method, $values, $prebound), InvalidParamsException::class);
    return $e->fields;
};

// --- Building

$user = $bind('user', ['input' => [
    'name' => 'Ann',
    'email' => 'ann@example.com',
    'age' => '30',
    'role' => 'admin',
    'born' => '2000-01-02',
    'home' => ['city' => 'Brno'],
    'addresses' => [['city' => 'Praha', 'zip' => '11000']],
    'byName' => ['work' => ['city' => 'Ostrava']],
    'unknown' => 'is left out',
    'name2' => ['also' => 'left out'],
]])[0];

Assert::type(UserInput::class, $user);
Assert::same('Ann', $user->name);
Assert::same(30, $user->age, 'values are converted as parameters are');
Assert::same(Role::Admin, $user->role);
Assert::same('2000-01-02', $user->born->format('Y-m-d'));
Assert::type(Address::class, $user->home);
Assert::same('Brno', $user->home->city);
Assert::type(Address::class, $user->addresses[0]);
Assert::same('11000', $user->addresses[0]->zip);
Assert::type(Address::class, $user->byName['work']);

$minimal = $bind('user', ['input' => ['name' => 'Ann', 'email' => 'a@b.cz']])[0];
Assert::null($minimal->age);
Assert::same(Role::User, $minimal->role, 'a default stays');
Assert::same([], $minimal->addresses);

$existing = new Address('Brno');
Assert::same($existing, $bind('user', ['input' => ['name' => 'Ann', 'email' => 'a@b.cz', 'home' => $existing]])[0]->home, 'an instance passes');

$object = json_decode('{"name":"Ann","email":"a@b.cz","home":{"city":"Brno"}}');
Assert::same('Brno', $bind('user', ['input' => $object])[0]->home->city, 'objects work too');

$list = $bind('list', ['list' => [['city' => 'A'], ['city' => 'B']]])[0];
Assert::type(Address::class, $list[1]);

$tree = $bind('tree', ['v' => ['name' => 'root', 'children' => [['name' => 'leaf', 'children' => [['name' => 'deep']]]]]])[0];
Assert::same('deep', $tree->children[0]->children[0]->name, 'a DTO may hold itself');

// --- All errors at once, with their paths

Assert::same([
    'input.name' => 'must have at least 2 characters.',
    'input.email' => 'must be a valid email.',
    'input.age' => 'must be at least 18.',
    'input.role' => 'must be one of: admin, user.',
    'input.born' => "must be a valid date and time. Value 'x' is not valid.",
    'input.home.city' => 'is required.',
    'input.addresses.1.city' => 'must have at least 1 characters.',
    'input.byName.work' => 'must be an object.',
], $errors('user', ['input' => [
    'name' => 'A',
    'email' => 'nope',
    'age' => 17,
    'role' => 'nobody',
    'born' => 'x',
    'home' => [],
    'addresses' => [['city' => 'ok'], ['city' => '']],
    'byName' => ['work' => 'x'],
]]));

Assert::same(['input.name' => 'is required.', 'input.email' => 'is required.'], $errors('user', ['input' => []]));
Assert::same(['input.addresses' => 'must have at most 2 items.'], $errors('user', ['input' => [
    'name' => 'Ann', 'email' => 'a@b.cz', 'addresses' => [['city' => 'a'], ['city' => 'b'], ['city' => 'c']],
]]));
Assert::same(['input' => 'must be an object.'], $errors('user', ['input' => 'text']));
Assert::same(['input' => 'must be an object.'], $errors('user', ['input' => [1, 2]]), 'a JSON array is not an object');
Assert::same(['input.name' => "must be 'string' but 'bool' given.", 'input.email' => 'is required.'], $errors('user', ['input' => ['name' => true]]));
Assert::same(['input.name' => "must be 'string' but 'null' given.", 'input.email' => 'is required.'], $errors('user', ['input' => ['name' => null]]));

// Several parameters are reported together
Assert::same(['a' => 'is required.', 'input' => 'is required.', 'b' => "must be 'int' but 'string' given."], $errors('two', ['b' => 'x']));

// A whole body has no parameter's name in its paths
Assert::same(['name' => 'is required.', 'email' => 'is required.'], $errors('user', [], ['input' => []]));
Assert::same(['input' => 'must be an object.'], $errors('user', [], ['input' => 'text']), 'the body itself is named after the parameter');
Assert::same(['0.city' => 'is required.'], $errors('list', [], ['list' => [[]]]));

// The message says what's wrong when there is one problem
Assert::exception(static fn() => $bind('constrained', ['page' => 0]), InvalidParamsException::class, "Parameter 'page' must be at least 1.");
Assert::exception(static fn() => $bind('user', ['input' => ['name' => 'Ann']]), InvalidParamsException::class, "Missing required parameter 'input.email'.");
Assert::exception(static fn() => $bind('two', []), InvalidParamsException::class, 'Invalid method parameter(s).');

// --- A docblock changes nothing without a DTO

Assert::same(['a', 'b'], $bind('sloppy', ['ids' => ['a', 'b']])[0], '@param int[] does not refuse strings');

// --- Constraints on ordinary parameters

Assert::same([1, null], $bind('constrained', []), 'defaults are not checked');
Assert::same([10, 'abc'], $bind('constrained', ['page' => 10, 'slug' => 'abc']));
Assert::same(['page' => 'must be at most 10.'], $errors('constrained', ['page' => 11]));
Assert::same(['slug' => 'has an invalid format.'], $errors('constrained', ['slug' => 'A1']));
Assert::same([1, null], $bind('constrained', ['slug' => null]), 'null is up to the type');

// --- Other public properties

$properties = $bind('properties', ['v' => ['required' => 'r', 'optional' => '7', 'secret' => 'attack', 'static' => 'attack']])[0];
Assert::same('r', $properties->required);
Assert::same(7, $properties->optional);
Assert::same('hidden', $properties->getSecret(), 'a private property can not be reached');
Assert::same('static', WithProperties::$static);
Assert::same(['v.required' => 'is required.'], $errors('properties', ['v' => ['optional' => 1]]));

// --- A constructor's own validation

Assert::same(5, $bind('refusing', ['v' => ['from' => 1, 'to' => 5]])[0]->to);
Assert::same(['v' => 'is not valid: from must not be after to.'], $errors('refusing', ['v' => ['from' => 9, 'to' => 5]]));

// --- A class which can't be built is a programming error, not the client's

Assert::exception(static fn() => $bind('abstract', ['v' => []]), LogicException::class, '%A%can\'t be instantiated%A%');

// --- Constraints themselves

$cases = [
    [new Range(1, 5), 1, 5, 0, 6],
    [new Range(min: 1.5), 1.5, 99, 1.4, null],
    [new Length(2, 3), 'žš', 'abc', 'a', 'abcd'],
    [new Count(1, 2), [1], [1, 2], [], [1, 2, 3]],
    [new Pattern('^a#b$'), 'a#b', 'a#b', 'ab', 'xa#b'],
    [new Format('email'), 'a@b.cz', 'a@b.cz', 'a', 'a@'],
    [new Format('uri'), 'https://a.cz/x', 'mailto:a@b.cz', 'nope', ''],
    [new Format('uuid'), '01234567-89ab-cdef-0123-456789abcdef', '01234567-89AB-CDEF-0123-456789ABCDEF', '123', 'x'],
    [new Format('date'), '2024-02-29', '2023-12-31', '2023-02-29', '2024-13-01'],
    [new Format('date-time'), '2024-02-03T04:05:06Z', '2024-02-03T04:05:06.5+02:00', '2024-02-03', '2023-02-29T00:00:00Z'],
    [new Format('ipv4'), '1.2.3.4', '127.0.0.1', '::1', '999.1.1.1'],
    [new Format('ipv6'), '::1', 'fe80::1', '1.2.3.4', 'x'],
];
foreach ($cases as [$constraint, $good1, $good2, $bad1, $bad2]) {
    Assert::null($constraint->validate($good1), $constraint::class . ' accepts');
    Assert::null($constraint->validate($good2), $constraint::class . ' accepts');
    Assert::type('string', $constraint->validate($bad1), $constraint::class . ' refuses');
    if ($bad2 !== null) {
        Assert::type('string', $constraint->validate($bad2), $constraint::class . ' refuses');
    }
}

// A constraint for another kind of value doesn't apply
Assert::null((new Range(1))->validate('text'));
Assert::null((new Length(5))->validate(1));
Assert::null((new Count(5))->validate('text'));
Assert::null((new Pattern('^a$'))->validate(5));
Assert::null((new Format('email'))->validate(5));

Assert::exception(static fn() => new Pattern('(unclosed'), InvalidArgumentException::class);
Assert::exception(static fn() => new Format('nonsense'), InvalidArgumentException::class, "Unknown format 'nonsense'.%A%");

// --- Serialization

$date = new DateTimeImmutable('2024-02-03T04:05:06+02:00');
$serialized = DtoSerializer::serialize(new UserInput(
    name: 'Ann',
    email: 'a@b.cz',
    role: Role::Admin,
    born: $date,
    home: new Address('Brno'),
    addresses: [new Address('Praha', '11000')],
));

Assert::same('Ann', $serialized['name']);
Assert::same('2024-02-03T04:05:06+02:00', $serialized['born'], 'a date in a DTO has a useful form');
Assert::same(['city' => 'Brno', 'zip' => null], $serialized['home']);
Assert::same([['city' => 'Praha', 'zip' => '11000']], $serialized['addresses']);
Assert::same(
    '{"name":"Ann","email":"a@b.cz","age":null,"role":"admin","born":"2024-02-03T04:05:06+02:00","home":{"city":"Brno","zip":null},"addresses":[{"city":"Praha","zip":"11000"}],"byName":[]}',
    json_encode($serialized),
);

$with = new WithProperties();
$with->required = 'r';
Assert::same(['required' => 'r', 'optional' => 5, 'nullable' => null], DtoSerializer::serialize($with), 'private and static stay out');

Assert::same('{}', json_encode(DtoSerializer::serialize(new Empty_())), 'an empty DTO is an object');
Assert::same(
    [['city' => 'A', 'zip' => null], ['nested' => ['city' => 'B', 'zip' => null]]],
    DtoSerializer::serialize([new Address('A'), ['nested' => new Address('B')]]),
    'DTOs are found in arrays',
);

// What isn't a DTO stays as it was
$plain = new stdClass();
Assert::same($plain, DtoSerializer::serialize($plain));
Assert::same($date, DtoSerializer::serialize($date), 'a date outside a DTO is left alone');
Assert::same(['date' => $date, 'n' => 1], DtoSerializer::serialize(['date' => $date, 'n' => 1]));
Assert::same('x', DtoSerializer::serialize('x'));
Assert::null(DtoSerializer::serialize(null));

<?php

declare(strict_types=1);

use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\Binding\InvalidParamsException;
use Phlox\ApiCore\Binding\ParameterBinder;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

enum Status: string
{
    case Active = 'active';
}

enum Level: int
{
    case High = 3;
}

final class Money implements ApiConstructible
{
    public function __construct(public readonly int $amount)
    {
    }

    public static function apiConstruct(mixed $value)
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException('Amount must be an integer.');
        }
        return new self($value);
    }
}

final class NotConstructible
{
}

/**
 * The parameters under test, one method per type.
 */
final class Target
{
    public function int(int $v) {}
    public function float(float $v) {}
    public function string(string $v) {}
    public function bool(bool $v) {}
    public function array(array $v) {}
    public function object(object $v) {}
    public function mixed(mixed $v) {}
    public function untyped($v) {}
    public function nullable(?int $v) {}
    public function withDefault(int $v = 5) {}
    public function dateTimeDefault(DateTimeImmutable $v = new DateTimeImmutable('2000-01-01')) {}
    public function union(int|string $v) {}
    public function unionFloat(int|bool $v) {}
    public function unionObject(array|Money $v) {}
    public function dateTime(DateTime $v) {}
    public function dateTimeImmutable(DateTimeImmutable $v) {}
    public function dateTimeInterface(DateTimeInterface $v) {}
    public function enum(Status $v) {}
    public function intEnum(Level $v) {}
    public function constructible(Money $v) {}
    public function notConstructible(NotConstructible $v) {}
    public function intersection(Countable&Iterator $v) {}
    public function two(int $a, string $b = 'x') {}
    public function variadic(int ...$rest) {}
}

$binder = new ParameterBinder();

$bind = static fn(string $method, mixed ...$value): mixed =>
    $binder->bind(new ReflectionMethod(Target::class, $method), $value ? ['v' => $value[0]] : [])[0] ?? null;

$fails = static function (string $method, mixed $value) use ($bind): void {
    Assert::exception(static fn() => $bind($method, $value), InvalidParamsException::class);
};

// int
Assert::same(5, $bind('int', 5));
Assert::same(5, $bind('int', '5'));
Assert::same(-5, $bind('int', '-5'));
Assert::same(5, $bind('int', 5.0));
foreach (['1.5', 1.5, 'abc', '', true, [], null, '99999999999999999999999'] as $bad) {
    $fails('int', $bad);
}

// float
Assert::same(1.5, $bind('float', 1.5));
Assert::same(1.5, $bind('float', '1.5'));
Assert::same(2.0, $bind('float', 2));
foreach (['abc', true, [], null] as $bad) {
    $fails('float', $bad);
}

// string: strict_types in the caller would refuse an int, so it must really be converted
Assert::same('a', $bind('string', 'a'));
Assert::same('5', $bind('string', 5));
foreach ([true, [], null] as $bad) {
    $fails('string', $bad);
}

// bool
foreach ([true, 1, '1', 'true'] as $good) {
    Assert::true($bind('bool', $good));
}
foreach ([false, 0, '0', 'false'] as $good) {
    Assert::false($bind('bool', $good));
}
foreach (['yes', 2, [], null] as $bad) {
    $fails('bool', $bad);
}

// array
Assert::same([1, 2], $bind('array', [1, 2]));
foreach (['a', 1, null] as $bad) {
    $fails('array', $bad);
}

// object, including nested arrays
$object = $bind('object', ['a' => ['b' => 1]]);
Assert::type(stdClass::class, $object);
Assert::same(1, $object->a->b);
Assert::same(1, $bind('object', '{"a":1}')->a);
foreach (['not json', '5', 1, null] as $bad) {
    $fails('object', $bad);
}

// mixed and untyped pass anything through
Assert::same('a', $bind('mixed', 'a'));
Assert::same(['a'], $bind('untyped', ['a']));
Assert::null($bind('mixed', null));

// missing values
Assert::null($bind('nullable'));
Assert::null($bind('nullable', null));
Assert::null($bind('untyped'));
Assert::same(5, $bind('withDefault'));
Assert::same('2000-01-01', $bind('dateTimeDefault')->format('Y-m-d'), 'a default isn\'t coerced');
Assert::exception(static fn() => $bind('int'), InvalidParamsException::class, "Missing required parameter 'v'.");
$fails('withDefault', null);

// union: the exact type wins, whichever order PHP reports them in
Assert::same(5, $bind('union', 5));
Assert::same('5', $bind('union', '5'));
Assert::same('a', $bind('union', 'a'));
Assert::same(1, $bind('unionFloat', '1'));
Assert::same([1], $bind('unionObject', [1]));
Assert::same(7, $bind('unionObject', 7)->amount);
$fails('union', []);
$fails('unionObject', 'text');

// date and time
Assert::same('2024-02-03 04:05:06', $bind('dateTime', '2024-02-03 04:05:06')->format('Y-m-d H:i:s'));
Assert::type(DateTime::class, $bind('dateTime', '2024-02-03'));
Assert::type(DateTimeImmutable::class, $bind('dateTimeImmutable', '2024-02-03'));
Assert::type(DateTime::class, $bind('dateTimeInterface', '2024-02-03'));
Assert::same('00:00:00', $bind('dateTime', '2024-02-03')->format('H:i:s'), 'a date has no time of day');
Assert::same('2024-02-03T04:05:06+02:00', $bind('dateTime', '2024-02-03T04:05:06+02:00')->format(DATE_ATOM));
Assert::type(DateTime::class, $bind('dateTime', 'tomorrow'), 'relative formats are still accepted');
Assert::type(DateTime::class, $bind('dateTime', 'now'));
foreach (['not a date', '', '   ', '2024-13-45', 'x', 'utc', 'cet', 5, [], null] as $bad) {
    $fails('dateTime', $bad);
}

// enums
Assert::same(Status::Active, $bind('enum', 'active'));
Assert::same(Level::High, $bind('intEnum', 3));
Assert::same(Level::High, $bind('intEnum', '3'));
$fails('enum', 'other');
$fails('intEnum', 9);

// ApiConstructible
Assert::same(7, $bind('constructible', 7)->amount);
Assert::exception(static fn() => $bind('constructible', 'x'), InvalidParamsException::class, "%A%Amount must be an integer.");
$fails('notConstructible', ['a' => 1]);
$fails('intersection', []);

// named, positional, prebound
$two = new ReflectionMethod(Target::class, 'two');
Assert::same([1, 'x'], $binder->bind($two, ['a' => 1]));
Assert::same([1, 'y'], $binder->bind($two, [1, 'y']));
Assert::same([1, 'y'], $binder->bind($two, ['b' => 'y', 'a' => 1]));
Assert::same([1, 'y'], $binder->bind($two, ['a' => 1, 'unknown' => 2, 'b' => 'y']), 'unknown names are ignored');
Assert::same([2, 'x'], $binder->bind($two, ['a' => 1], ['a' => 2]), 'prebound wins');

// a missing variadic is simply empty
Assert::same([], $binder->bind(new ReflectionMethod(Target::class, 'variadic'), []));

// custom formats replace the default ones
$custom = new ParameterBinder(['!d.m.Y']);
$method = new ReflectionMethod(Target::class, 'dateTime');
Assert::same('2024-02-03', $custom->bind($method, ['v' => '03.02.2024'])[0]->format('Y-m-d'));

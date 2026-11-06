<?php

/**
 * Changing only what a client sent, by the rules of a DTO.
 */

declare(strict_types=1);

use Phlox\ApiCore\Attributes\Constraints\Count;
use Phlox\ApiCore\Attributes\Constraints\Length;
use Phlox\ApiCore\Attributes\Constraints\Range;
use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Attributes\PatchOf;
use Phlox\ApiCore\Binding\InvalidParamsException;
use Phlox\ApiCore\Binding\ParameterBinder;
use Phlox\ApiCore\Documentation\SchemaBuilder;
use Phlox\ApiCore\Patch;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

#[Dto]
final readonly class LineInput
{
    public function __construct(#[Range(min: 1)] public int $quantity, public ?string $note = null)
    {
    }
}

/** A thing to save. */
#[Dto]
final readonly class SaveThing
{
    /**
     * @param string|null $name What it's called
     * @param list<LineInput> $lines
     */
    public function __construct(
        #[Length(min: 2, max: 5)] public string $code,
        public ?string $name = null,
        #[Range(min: 1)] public int $priority = 1,
        public ?\DateTime $due = null,
        #[Count(max: 2)] public array $lines = [],
    ) {
    }
}

#[Dto]
final class WithProperty
{
    public string $title;
}

final class NotADto
{
}

final class Handlers
{
    public function patch(#[PatchOf(SaveThing::class)] Patch $changes): Patch
    {
        return $changes;
    }

    public function optional(#[PatchOf(SaveThing::class)] ?Patch $changes = null): ?Patch
    {
        return $changes;
    }

    public function property(#[PatchOf(WithProperty::class)] Patch $changes): Patch
    {
        return $changes;
    }

    public function noAttribute(Patch $changes): Patch
    {
        return $changes;
    }

    public function notDto(#[PatchOf(NotADto::class)] Patch $changes): Patch
    {
        return $changes;
    }
}

$binder = new ParameterBinder();
$bind = static fn(string $method, array $parameters): array => $binder->bind(new ReflectionMethod(Handlers::class, $method), $parameters);
$patch = static fn(array $body): Patch => $binder->bind(new ReflectionMethod(Handlers::class, 'patch'), [], ['changes' => $body])[0];
$errorsOf = static fn(callable $call): array => Assert::exception($call, InvalidParamsException::class)->fields;
$messageOf = static fn(array $body): array => $errorsOf(static fn() => $patch($body));

// --- Only what was sent

$changes = $patch(['name' => 'Ann']);
Assert::same(['name' => 'Ann'], $changes->toArray());
Assert::true($changes->has('name'));
Assert::false($changes->has('code'), 'what is required in the DTO need not come');
Assert::same('Ann', $changes->get('name'));
Assert::same('fallback', $changes->get('code', 'fallback'));

$cleared = $patch(['name' => null]);
Assert::true($cleared->has('name'), 'null is not the same as nothing');
Assert::null($cleared->get('name', 'fallback'));

Assert::same([], $patch([])->toArray(), 'nothing is fine');
Assert::same(['name' => 'x'], $patch(['name' => 'x', 'id' => 1, 'secret' => 2])->toArray(), 'nothing the DTO does not declare');

// --- By the rules of the DTO

Assert::same(5, $patch(['priority' => '5'])->get('priority'), 'converted');
Assert::type(DateTime::class, $patch(['due' => '2024-02-03'])->get('due'));

$lines = $patch(['lines' => [['quantity' => 2]]])->get('lines');
Assert::type(LineInput::class, $lines[0], 'a collection of DTOs');
Assert::same(2, $lines[0]->quantity);

Assert::same(['code' => 'must have at least 2 characters.'], $messageOf(['code' => 'x']), 'constraints');
Assert::same(['priority' => 'must be at least 1.'], $messageOf(['priority' => 0]));
Assert::contains('must be', $messageOf(['code' => null])['code'], 'null only where the DTO allows it');
Assert::contains('must be', $messageOf(['name' => ['a']])['name'], 'a wrong type is an error, not a TypeError');
Assert::same(['lines.0.quantity' => 'must be at least 1.'], $messageOf(['lines' => [['quantity' => 0]]]), 'nested rules, with a path');
Assert::same(['lines.0.quantity' => 'is required.'], $messageOf(['lines' => [[]]]), 'a nested DTO is whole');
Assert::same(['lines' => 'must have at most 2 items.'], $messageOf(['lines' => [['quantity' => 1], ['quantity' => 1], ['quantity' => 1]]]));
Assert::same(['code', 'priority'], array_keys($messageOf(['code' => 'x', 'priority' => 0])), 'all at once');

Assert::same(['changes' => 'must be an object.'], $errorsOf(static fn() => $patch([1, 2])));

// --- As a named parameter, as JSON-RPC has it

$named = $bind('patch', ['changes' => ['name' => 'Bob']])[0];
Assert::same(['name' => 'Bob'], $named->toArray());
Assert::same(
    ['changes.code' => 'must have at least 2 characters.'],
    $errorsOf(static fn() => $bind('patch', ['changes' => ['code' => 'x']])),
    'a path with the name of the parameter',
);

Assert::null($bind('optional', [])[0], 'nothing for an optional parameter');
Assert::null($bind('optional', ['changes' => null])[0]);
Assert::same(['title' => 'x'], $bind('property', ['changes' => ['title' => 'x']])[0]->toArray(), 'a public property is a field too');

// --- Mistakes are the developer's

Assert::exception(static fn() => $bind('noAttribute', ['changes' => []]), LogicException::class, '%A%needs #[PatchOf]%A%');
Assert::exception(static fn() => $bind('notDto', ['changes' => []]), LogicException::class, '%A%needs a class with #[Dto]%A%');

// --- Setting it on an object

$target = new stdClass();
$target->name = 'old';
$target->code = 'keep';
Assert::same($target, $patch(['name' => null, 'priority' => 3])->applyTo($target));
Assert::null($target->name);
Assert::same(3, $target->priority);
Assert::same('keep', $target->code, 'what did not come stays');

// --- Documentation: the DTO without what is required

$builder = new SchemaBuilder();
$attribute = new PatchOf(SaveThing::class);
Assert::same(['$ref' => '#/components/schemas/SaveThing_Patch'], $attribute->describeParameterSchema($builder));
Assert::same('', $attribute->describeParameter());
Assert::null($attribute->describeResponse($builder));

$schemas = $builder->getSchemas();
Assert::same(['code'], $schemas['SaveThing']['required'], 'the DTO itself stays as it is');
Assert::false(isset($schemas['SaveThing_Patch']['required']));
Assert::same(array_keys($schemas['SaveThing']['properties']), array_keys($schemas['SaveThing_Patch']['properties']));
Assert::same(5, $schemas['SaveThing_Patch']['properties']['code']['maxLength'], 'with its rules');
Assert::same('What it\'s called', $schemas['SaveThing_Patch']['properties']['name']['description']);
Assert::same('A thing to save.', $schemas['SaveThing_Patch']['description']);
Assert::same(['quantity'], $schemas['LineInput']['required'], 'a nested DTO is whole');

Assert::same(['$ref' => '#/components/schemas/SaveThing_Patch'], $attribute->describeParameterSchema($builder), 'the same one again');

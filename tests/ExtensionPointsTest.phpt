<?php

/**
 * What other packages build on: values bound to their parameter, classes which
 * describe themselves, and attributes which describe a method.
 */

declare(strict_types=1);

use Phlox\ApiCore\Attributes\Dto;
use Phlox\ApiCore\Binding\BoundToParameter;
use Phlox\ApiCore\Binding\ParameterBinder;
use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\Documentation\DescribesMethod;
use Phlox\ApiCore\Documentation\MethodReader;
use Phlox\ApiCore\Documentation\SchemaBuilder;
use Phlox\ApiCore\Documentation\SelfDescribing;
use Phlox\ApiCore\Types\DtoMeta;
use Phlox\ApiCore\Types\Type;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

#[Attribute(Attribute::TARGET_PARAMETER)]
final class About implements DescribesMethod
{
    public function __construct(public readonly string $what)
    {
    }

    public function describeParameter(): string
    {
        return "About {$this->what}.";
    }

    public function describeParameterSchema(SchemaBuilder $builder): array|stdClass|null
    {
        return $this->what === 'silence' ? null : ['type' => 'object', 'title' => $this->what];
    }

    public function describeResponse(SchemaBuilder $builder): array|stdClass|null
    {
        return $this->what === 'silence' ? null : ['type' => 'string', 'description' => $this->what];
    }
}

final class Bound implements ApiConstructible, BoundToParameter, SelfDescribing
{
    public ?string $boundTo = null;

    public function __construct(public readonly mixed $value = null)
    {
    }

    public static function apiConstruct(mixed $value): static
    {
        return new self($value);
    }

    public static function createEmpty(): static
    {
        return new self('empty');
    }

    public function bindToParameter(ReflectionParameter $parameter): void
    {
        $this->boundTo = $parameter->getName();
    }

    public static function describeSchema(SchemaBuilder $builder): array|stdClass
    {
        return $builder->define('Bound', static fn(): array => [
            'type' => 'object',
            'properties' => ['next' => ['$ref' => '#/components/schemas/Bound']],
        ]);
    }
}

#[Attribute(Attribute::TARGET_CLASS)]
final class SpecialDto extends Dto
{
}

#[SpecialDto]
final readonly class Marked
{
    public function __construct(public int $id)
    {
    }
}

final class Target
{
    public function required(Bound $query): void {}

    public function nullable(?Bound $query = null): void {}

    /**
     * @param Bound $query What to do
     */
    public function described(#[About('it')] Bound $query): void {}

    public function silent(#[About('silence')] string $query): void {}
}

$binder = new ParameterBinder();

// --- Bound to the parameter, whether anything came or not

foreach (['required', 'nullable'] as $method) {
    $reflection = new ReflectionMethod(Target::class, $method);

    $missing = $binder->bind($reflection, [])[0];
    Assert::same('empty', $missing->value, "{$method}: what's missing is created");
    Assert::same('query', $missing->boundTo);

    $given = $binder->bind($reflection, ['query' => 5])[0];
    Assert::same(5, $given->value);
    Assert::same('query', $given->boundTo);
}

$null = $binder->bind(new ReflectionMethod(Target::class, 'nullable'), ['query' => null])[0];
Assert::same('empty', $null->value, 'an explicit null avoids nothing either');
Assert::same('query', $null->boundTo);

// --- A class describing itself, even in a circle

$builder = new SchemaBuilder();
Assert::same(['$ref' => '#/components/schemas/Bound'], $builder->build(new Type(Type::ClassName, class: Bound::class)));
Assert::same(['Bound'], array_keys($builder->getSchemas()));
Assert::contains(Bound::class, $builder->getClasses());

$builder->addClass(Marked::class);
Assert::contains(Marked::class, $builder->getClasses());

Assert::same(['$ref' => '#/components/schemas/Once'], $builder->define('Once', static fn(): array => ['type' => 'object']));
Assert::same(['$ref' => '#/components/schemas/Once'], $builder->define('Once', static fn(): array => ['type' => 'string']));
Assert::same(['type' => 'object'], $builder->getSchemas()['Once'], 'the first one stays');

// --- An attribute describing a method

$class = new ReflectionClass(Target::class);
$described = new MethodReader($class, new ReflectionMethod(Target::class, 'described'), new SchemaBuilder());
Assert::same('What to do About it.', $described->getParameters()['query']['description']);
Assert::same(['type' => 'string', 'description' => 'it'], $described->getDescribedResponse());
Assert::same(['type' => 'object', 'title' => 'it'], $described->getParameters()['query']['schema']);

$silent = new MethodReader($class, new ReflectionMethod(Target::class, 'silent'), new SchemaBuilder());
Assert::null($silent->getDescribedResponse());

$plain = new MethodReader($class, new ReflectionMethod(Target::class, 'required'), new SchemaBuilder());
Assert::null($plain->getDescribedResponse());

// --- An attribute extending #[Dto] makes a DTO

Assert::true(DtoMeta::isDto(Marked::class));

// --- A class only another package knows

SchemaBuilder::addClassDescriber(static fn(string $class): ?array => $class === Target::class ? ['title' => 'described'] : null);
Assert::same(['title' => 'described'], (new SchemaBuilder())->build(new Type(Type::ClassName, class: Target::class)));
Assert::same(['type' => 'array', 'items' => ['title' => 'described']], (new SchemaBuilder())->buildDefinition(new Phlox\ApiCore\Schema\ListOf(Target::class)));

// --- A constructor of a controller can call its parent's

Assert::noError(static fn() => (new ReflectionClass(Phlox\ApiCore\ApiController::class))->getConstructor()->isPublic() ?: throw new LogicException());

// --- A partial DTO has no required fields

#[Attribute(Attribute::TARGET_CLASS)]
final class PartialDto extends Dto
{
    public function isPartial(): bool
    {
        return true;
    }
}

#[PartialDto]
final readonly class Partial
{
    public function __construct(public int $id)
    {
    }
}

$partial = new SchemaBuilder();
$partial->build(new Type(Type::ClassName, class: Partial::class));
Assert::false(isset($partial->getSchemas()['Partial']['required']));

$whole = new SchemaBuilder();
$whole->build(new Type(Type::ClassName, class: Marked::class));
Assert::same(['id'], $whole->getSchemas()['Marked']['required']);

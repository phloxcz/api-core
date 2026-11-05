<?php

/**
 * Reading types from signatures and docblocks.
 */

declare(strict_types=1);

use Phlox\ApiCore\Types\DocComment;
use Phlox\ApiCore\Types\Type;
use Phlox\ApiCore\Types\TypeParser;
use Phlox\ApiCore\Types\TypeReader;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

final class Other
{
}

final class Scope
{
    /** @param list<Other> $vague  @return array<string, int> */
    public function docOnVague(array $vague, int $precise): array
    {
        return [];
    }

    /**
     * @param string $precise Stale, the signature is an int
     * @return string Stale as well
     */
    public function stale(int $precise): int
    {
        return 1;
    }

    /**
     * @param list<Other> $nullable
     */
    public function nullable(?array $nullable): void
    {
    }

    /**
     * @param Nonsense<<< $broken
     */
    public function broken(array $broken): void
    {
    }

    /**
     * @param list<Other> $untyped
     * @return Other|null
     */
    public function untyped($untyped)
    {
    }

    public function self(self $a): static
    {
        return $this;
    }

    public function union(int|string|null $a): void
    {
    }
}

$scope = new ReflectionClass(Scope::class);
$parse = static fn(string $text): ?Type => TypeParser::parse($text, $scope);

// --- Parser

foreach (['int', 'integer', 'positive-int'] as $name) {
    Assert::same(Type::Int, $parse($name)->kind);
}
Assert::same(Type::Float, $parse('double')->kind);
Assert::same(Type::String, $parse('non-empty-string')->kind);
Assert::same(Type::Bool, $parse('true')->kind);
Assert::same(Type::Void, $parse('void')->kind);
Assert::same(Type::Array, $parse('array')->kind);

Assert::same('list<int>', (string) $parse('list<int>'));
Assert::same('list<int>', (string) $parse('int[]'));
Assert::same('list<int>', (string) $parse('array<int>'));
Assert::same('list<int>', (string) $parse('array<int, int>'));
Assert::same('array<string, int>', (string) $parse('array<string, int>'));
Assert::same('list<list<int>>', (string) $parse('int[][]'));
Assert::same('?int', (string) $parse('?int'));
Assert::same('?int', (string) $parse('int|null'));
Assert::same('?int', (string) $parse('null|int'));
Assert::same('int|string', (string) $parse('int|string'));
Assert::same('int|string|null', (string) $parse('int|string|null'));
Assert::same('list<int|string>', (string) $parse('(int|string)[]'));
Assert::same('App\Model\User', (new Type(Type::ClassName, class: 'App\Model\User'))->class);
Assert::same('?User', (string) new Type(Type::ClassName, true, 'App\Model\User'), 'clients get no namespaces');
Assert::same(Other::class, $parse('Other')->class, 'a short name is resolved by the scope');
Assert::same(Other::class, $parse('\Other')->class);
Assert::same(Scope::class, $parse('self')->class);
Assert::same(Scope::class, $parse('static')->class);
Assert::same(Scope::class, $parse('$this')->class);
Assert::same(Other::class, $parse('Other<int, array<string>>')->class, 'generics of a class are skipped');
Assert::same(Type::String, $parse("'a'|'b'")->union[0]->kind);

$shape = $parse('array{id: int, name?: string, "quoted key": ?bool}');
Assert::same(Type::Shape, $shape->kind);
Assert::same(['id', 'name', 'quoted key'], array_keys($shape->shape));
Assert::false($shape->shape['id']['optional']);
Assert::true($shape->shape['name']['optional']);
Assert::true($shape->shape['quoted key']['type']->nullable);

foreach (['', 'list<', 'array{a', 'int|', 'A&B', '<>', 'int int', '%'] as $bad) {
    Assert::null($parse($bad), "'{$bad}' is not understood");
}

// --- Reader: a docblock only refines what the signature leaves open

$method = static fn(string $name): ReflectionMethod => new ReflectionMethod(Scope::class, $name);
$parameter = static fn(string $name, int $position = 0): ReflectionParameter => $method($name)->getParameters()[$position];

Assert::same('list<Other>', (string) TypeReader::ofParameter($parameter('docOnVague')));
Assert::same('int', (string) TypeReader::ofParameter($parameter('docOnVague', 1)));
Assert::same('int', (string) TypeReader::ofParameter($parameter('stale')), 'a stale docblock loses');
Assert::same('int', (string) TypeReader::ofReturn($method('stale')));
Assert::same('?list<Other>', (string) TypeReader::ofParameter($parameter('nullable')), 'the signature decides on null');
Assert::same('array', (string) TypeReader::ofParameter($parameter('broken')), 'a broken docblock breaks nothing');
Assert::same('list<Other>', (string) TypeReader::ofParameter($parameter('untyped')));
Assert::same('?Other', (string) TypeReader::ofReturn($method('untyped')));
Assert::same(Scope::class, TypeReader::ofParameter($parameter('self'))->class);
Assert::same(Scope::class, TypeReader::ofReturn($method('self'))->class);
Assert::same(Type::Void, TypeReader::ofReturn($method('nullable'))->kind);

$union = TypeReader::ofParameter($parameter('union'));
Assert::same(Type::Union, $union->kind);
Assert::true($union->nullable);
Assert::count(2, $union->union, 'null is a flag, not a member');

Assert::same(Type::Mixed, TypeReader::native(null)->kind);

// --- Doc comments

$doc = new DocComment(<<<'DOC'
    /**
     * The summary
     * in two lines.
     *
     * First paragraph.
     *
     * Second paragraph.
     *
     * @param array<string, int> $map The map
     *        continues here.
     * @param int | string $spaced
     * @param list<int> ...$rest
     * @return array{a: int, b: string} What comes back.
     * @deprecated Use something else.
     */
    DOC);

Assert::same('The summary in two lines.', $doc->summary);
Assert::same("First paragraph.\n\nSecond paragraph.", $doc->description);
Assert::true($doc->deprecated);
Assert::same(['type' => 'array<string, int>', 'description' => 'The map continues here.'], $doc->getParam('map'));
Assert::same('int | string', $doc->getParam('spaced')['type']);
Assert::same('list<int>', $doc->getParam('rest')['type']);
Assert::same(['type' => 'array{a: int, b: string}', 'description' => 'What comes back.'], $doc->getReturn());
Assert::null($doc->getParam('unknown'));

$var = new DocComment('/** @var list<int> $items The items */');
Assert::same(['type' => 'list<int>', 'description' => 'The items'], $var->getVar());

$empty = new DocComment(false);
Assert::same('', $empty->summary);
Assert::false($empty->deprecated);

<?php
/**
 * This file is part of phloxcz/api-core.
 *
 * (c) Phlox <https://github.com/phloxcz>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phlox\ApiCore\Types;

/**
 * The parts of a docblock the API cares about.
 */
final class DocComment
{
    public readonly string $summary;
    public readonly string $description;
    public readonly bool $deprecated;

    /** @var array<string, array{type: string, description: string}> */
    private array $params = [];

    /** @var array{type: string, description: string}|null */
    private ?array $return = null;

    /** @var array{type: string, description: string}|null */
    private ?array $var = null;

    public function __construct(string|false|null $comment)
    {
        $text = [];
        $deprecated = false;
        $tag = null;
        $tags = [];

        foreach (self::cleanLines((string) $comment) as $line) {
            if (str_starts_with($line, '@')) {
                $tag = count($tags);
                $tags[$tag] = $line;
            } elseif ($tag === null) {
                $text[] = $line;
            } elseif ($line !== '') {
                $tags[$tag] .= ' ' . $line;
            }
        }

        foreach ($tags as $line) {
            if (preg_match('/^@(\w+)(?:\s+(.*))?$/s', $line, $matches) !== 1) {
                continue;
            }

            $rest = trim($matches[2] ?? '');
            switch ($matches[1]) {
                case 'deprecated':
                    $deprecated = true;
                    break;
                case 'param':
                    [$type, $after] = self::splitType($rest);
                    if (preg_match('/^(?:\.\.\.)?\$(\w+)\s*(.*)$/s', $after, $param) === 1) {
                        $this->params[$param[1]] = ['type' => $type, 'description' => trim($param[2])];
                    }
                    break;
                case 'return':
                    [$type, $after] = self::splitType($rest);
                    $this->return = ['type' => $type, 'description' => trim($after)];
                    break;
                case 'var':
                    [$type, $after] = self::splitType($rest);
                    $this->var = ['type' => $type, 'description' => trim(preg_replace('/^\$\w+\s*/', '', $after) ?? '')];
                    break;
            }
        }

        $paragraphs = preg_split('/\n\s*\n/', trim(implode("\n", $text))) ?: [''];
        $this->summary = trim(preg_replace('/\s*\n\s*/', ' ', (string) array_shift($paragraphs)) ?? '');
        $this->description = trim(implode("\n\n", $paragraphs));
        $this->deprecated = $deprecated;
    }

    /**
     * @return array{type: string, description: string}|null
     */
    public function getParam(string $name): ?array
    {
        return $this->params[$name] ?? null;
    }

    /**
     * @return array{type: string, description: string}|null
     */
    public function getReturn(): ?array
    {
        return $this->return;
    }

    /**
     * @return array{type: string, description: string}|null
     */
    public function getVar(): ?array
    {
        return $this->var;
    }

    /**
     * @return list<string>
     */
    private static function cleanLines(string $comment): array
    {
        $comment = preg_replace('~^\s*/\*\*|\*/\s*$~', '', $comment) ?? '';

        return array_map(
            static fn(string $line): string => trim(preg_replace('/^\s*\*\s?/', '', $line) ?? ''),
            preg_split('/\R/', $comment) ?: [],
        );
    }

    /**
     * Separates a type, which can contain spaces like "array<string, int>", from what follows.
     *
     * @return array{string, string}
     */
    private static function splitType(string $text): array
    {
        $depth = 0;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if (str_contains('<{(', $char)) {
                $depth++;
            } elseif (str_contains('>})', $char)) {
                $depth--;
            } elseif ($depth === 0 && ctype_space($char)) {
                // "int | string" is still one type
                $next = ltrim(substr($text, $i));
                if (!str_starts_with($next, '|') && !str_ends_with(rtrim(substr($text, 0, $i)), '|')) {
                    return [substr($text, 0, $i), ltrim($next)];
                }
            }
        }

        return [$text, ''];
    }
}

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

namespace Phlox\ApiCore\Documentation;

/**
 * Writes a decoded JSON document as YAML. Only an output format for those who
 * prefer it: objects are maps and arrays are sequences, as in JSON.
 */
final class Yaml
{
    private const Indent = '  ';

    public static function encode(mixed $value): string
    {
        return self::write($value, '') . "\n";
    }

    private static function write(mixed $value, string $indent): string
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
            if ($value === []) {
                return '{}';
            }

            $lines = [];
            foreach ($value as $key => $item) {
                $lines[] = $indent . self::encodeScalar((string) $key) . ':' . self::writeNested($item, $indent);
            }
            return implode("\n", $lines);
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $lines = [];
            foreach ($value as $item) {
                $nested = self::write($item, $indent . self::Indent);
                $lines[] = is_object($item) && get_object_vars($item) !== [] || is_array($item) && $item !== []
                    ? $indent . '- ' . ltrim($nested)
                    : $indent . '- ' . $nested;
            }
            return implode("\n", $lines);
        }

        return self::encodeScalar($value);
    }

    private static function writeNested(mixed $value, string $indent): string
    {
        $isCollection = is_object($value) ? get_object_vars($value) !== [] : (is_array($value) && $value !== []);

        return $isCollection
            ? "\n" . self::write($value, $indent . self::Indent)
            : ' ' . self::write($value, $indent . self::Indent);
    }

    private static function encodeScalar(mixed $value): string
    {
        if (is_string($value)) {
            // A JSON string is a valid double-quoted YAML string, whatever it holds
            return preg_match('/^[A-Za-z_][A-Za-z0-9_.\/-]*$/D', $value) === 1
                && !in_array(strtolower($value), ['true', 'false', 'null', 'yes', 'no', 'on', 'off', 'y', 'n'], true)
                ? $value
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) json_encode($value);
    }
}

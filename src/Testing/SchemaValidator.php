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

namespace Phlox\ApiCore\Testing;

/**
 * Checks a value against JSON Schema of a generated document, for tests which
 * make sure an API returns what its documentation says.
 *
 * It knows the keywords the generators write: $ref, allOf, anyOf, type, enum,
 * properties, required, items and additionalProperties. Formats aren't checked.
 */
final class SchemaValidator
{
    /**
     * @param array<string, mixed> $document The document, for $ref
     * @param string $where Where the value is, for messages
     * @return list<string> What's wrong; empty when nothing is
     */
    public static function validate(mixed $value, mixed $schema, array $document, string $where = '$'): array
    {
        if (!is_array($schema) || $schema === []) {
            return []; // anything
        }

        if (isset($schema['$ref'])) {
            $target = $document;
            foreach (explode('/', substr((string) $schema['$ref'], 2)) as $part) {
                $target = is_array($target) ? ($target[$part] ?? null) : null;
            }
            return $target === null
                ? ["{$where}: {$schema['$ref']} does not exist"]
                : self::validate($value, $target, $document, $where);
        }

        $errors = [];

        foreach ($schema['allOf'] ?? [] as $part) {
            array_push($errors, ...self::validate($value, $part, $document, $where));
        }

        if (isset($schema['anyOf']) && !array_any($schema['anyOf'], static fn(mixed $option): bool => self::validate($value, $option, $document, $where) === [])) {
            $errors[] = "{$where}: " . json_encode($value) . ' matches none of anyOf';
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = "{$where}: " . json_encode($value) . ' is not one of ' . json_encode($schema['enum']);
        }

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            if (array_intersect($types, self::typesOf($value)) === []) {
                return [...$errors, "{$where}: " . json_encode($value) . ' is not ' . implode('|', $types)];
            }
        }

        if (is_array($value) && (!array_is_list($value) || $value === [])) {
            foreach ($schema['required'] ?? [] as $name) {
                if (!array_key_exists($name, $value)) {
                    $errors[] = "{$where}: '{$name}' is missing";
                }
            }
            foreach ($value as $name => $item) {
                $property = $schema['properties'][$name] ?? $schema['additionalProperties'] ?? null;
                if ($property !== null && $property !== true) {
                    array_push($errors, ...self::validate($item, $property, $document, "{$where}.{$name}"));
                }
            }
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
            foreach ($value as $index => $item) {
                array_push($errors, ...self::validate($item, $schema['items'], $document, "{$where}[{$index}]"));
            }
        }

        return $errors;
    }

    /**
     * @return list<string> JSON types the value can be; an empty array is both a list and an object
     */
    private static function typesOf(mixed $value): array
    {
        return match (true) {
            $value === null => ['null'],
            is_bool($value) => ['boolean'],
            is_int($value) => ['integer', 'number'],
            is_float($value) => ['number'],
            is_string($value) => ['string'],
            $value === [] => ['array', 'object'],
            is_array($value) && array_is_list($value) => ['array'],
            default => ['object'],
        };
    }
}

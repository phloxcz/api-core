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

namespace Phlox\ApiCore\Binding;

use Phlox\ApiCore\ApiConstructible;
use Phlox\ApiCore\Patch;
use Phlox\ApiCore\Types\DtoField;
use Phlox\ApiCore\Types\DtoMeta;
use Phlox\ApiCore\Types\Type;

/**
 * Turns a raw API value into a value of a Type, including DTOs and what they hold.
 */
final class ValueConverter
{
    /**
     * @param list<string> $dateTimeFormats
     */
    public function __construct(private readonly array $dateTimeFormats)
    {
    }

    /**
     * @param string $path Where the value is, for error messages
     * @throws ConversionException
     */
    public function convert(Type $type, mixed $value, string $path = ''): mixed
    {
        if ($value === null) {
            return $type->nullable || $type->kind === Type::Mixed ? null : throw $this->typeError($type, $value, $path);
        }

        return match ($type->kind) {
            Type::Mixed => $value,
            Type::Int => $this->toInt($value) ?? throw $this->typeError($type, $value, $path),
            Type::Float => $this->toFloat($value) ?? throw $this->typeError($type, $value, $path),
            Type::String => is_string($value) || is_int($value) || is_float($value) ? (string) $value : throw $this->typeError($type, $value, $path),
            Type::Bool => $this->toBool($value) ?? throw $this->typeError($type, $value, $path),
            Type::Array => is_array($value) ? $value : throw $this->typeError($type, $value, $path),
            Type::Object => $this->toObject($value) ?? throw $this->typeError($type, $value, $path),
            Type::List => $this->toList($type, $value, $path),
            Type::Map => $this->toMap($type, $value, $path),
            Type::Shape => $this->toShape($type, $value, $path),
            Type::Union => $this->toUnion($type, $value, $path),
            Type::ClassName => $this->toClass((string) $type->class, $value, $path),
            default => throw $this->typeError($type, $value, $path),
        };
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }
        if (is_string($value)) {
            $int = filter_var($value, FILTER_VALIDATE_INT);
            return $int === false ? null : $int;
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        return is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)) ? (float) $value : null;
    }

    private function toBool(mixed $value): ?bool
    {
        return match (true) {
            in_array($value, [true, 1, '1', 'true'], true) => true,
            in_array($value, [false, 0, '0', 'false'], true) => false,
            default => null,
        };
    }

    private function toObject(mixed $value): ?object
    {
        if (is_object($value)) {
            return $value;
        }
        if (is_array($value)) {
            // Nested arrays must become objects as well, like a plain json_decode()
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        }
        if (is_string($value)) {
            $decoded = json_decode($value);
            return is_object($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * @return list<mixed>
     * @throws ConversionException
     */
    private function toList(Type $type, mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw $this->typeError($type, $value, $path);
        }

        return array_values($this->convertItems($type, $value, $path));
    }

    /**
     * @return array<int|string, mixed>
     * @throws ConversionException
     */
    private function toMap(Type $type, mixed $value, string $path): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            throw $this->typeError($type, $value, $path);
        }

        return $this->convertItems($type, $value, $path);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     * @throws ConversionException
     */
    private function convertItems(Type $type, array $values, string $path): array
    {
        $items = $type->items ?? new Type(Type::Mixed, nullable: true);
        $errors = [];
        $result = [];

        foreach ($values as $key => $item) {
            try {
                $result[$key] = $this->convert($items, $item, self::join($path, (string) $key));
            } catch (ConversionException $e) {
                $errors += $e->errors;
            }
        }

        return $errors === [] ? $result : throw new ConversionException($errors);
    }

    /**
     * @return array<string, mixed>
     * @throws ConversionException
     */
    private function toShape(Type $type, mixed $value, string $path): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            throw $this->typeError($type, $value, $path);
        }

        $errors = [];
        foreach ($type->shape as $name => $field) {
            $fieldPath = self::join($path, $name);
            if (!array_key_exists($name, $value)) {
                if (!$field['optional']) {
                    $errors[$fieldPath] = 'is required.';
                }
                continue;
            }
            try {
                $value[$name] = $this->convert($field['type'], $value[$name], $fieldPath);
            } catch (ConversionException $e) {
                $errors += $e->errors;
            }
        }

        return $errors === [] ? $value : throw new ConversionException($errors);
    }

    /**
     * A value matching one of the types exactly is kept as is ("int|string" must
     * not turn 5 into "5"); only then the first one it converts to is taken.
     *
     * @throws ConversionException
     */
    private function toUnion(Type $union, mixed $value, string $path): mixed
    {
        foreach ($union->union as $type) {
            if ($this->isExactMatch($type, $value)) {
                return $value;
            }
        }

        foreach ($union->union as $type) {
            try {
                return $this->convert($type, $value, $path);
            } catch (ConversionException) {
                continue;
            }
        }

        throw $this->typeError($union, $value, $path);
    }

    private function isExactMatch(Type $type, mixed $value): bool
    {
        return match ($type->kind) {
            Type::Int => is_int($value),
            Type::Float => is_float($value),
            Type::String => is_string($value),
            Type::Bool => is_bool($value),
            Type::Array => is_array($value),
            Type::Object => is_object($value),
            Type::Mixed => true,
            Type::ClassName => $type->class !== null && $value instanceof $type->class,
            default => false,
        };
    }

    /**
     * @throws ConversionException
     */
    private function toClass(string $class, mixed $value, string $path): mixed
    {
        if (is_object($value) && $value instanceof $class) {
            return $value;
        }

        if (is_a($class, \DateTimeInterface::class, true)) {
            return $this->toDateTime($class, $value, $path);
        }

        if (is_a($class, \BackedEnum::class, true)) {
            return $this->toEnum($class, $value, $path);
        }

        if (is_a($class, ApiConstructible::class, true)) {
            try {
                return $class::apiConstruct($value);
            } catch (\InvalidArgumentException $e) {
                throw ConversionException::at($path, "is not valid: {$e->getMessage()}");
            }
        }

        if (class_exists($class) && DtoMeta::isDto($class)) {
            return $this->toDto($class, $value, $path);
        }

        // Clients get this message, and namespaces are none of their business
        throw ConversionException::at($path, "of type '" . Type::shortName($class) . "' can't be constructed.");
    }

    /**
     * @param class-string<\DateTimeInterface> $class
     * @throws ConversionException
     */
    private function toDateTime(string $class, mixed $value, string $path): \DateTimeInterface
    {
        // \DateTimeInterface itself can't be instantiated
        $concrete = is_a($class, \DateTime::class, true) || is_a($class, \DateTimeImmutable::class, true)
            ? $class
            : \DateTime::class;

        if (is_string($value) && trim($value) !== '') {
            foreach ($this->dateTimeFormats as $format) {
                $result = $concrete::createFromFormat($format, $value);
                $errors = $concrete::getLastErrors();
                // createFromFormat() silently rolls 2024-13-45 over, reporting it only as a warning
                if ($result !== false && ($errors === false || $errors['warning_count'] === 0)) {
                    return $result;
                }
            }

            if (self::holdsDateOrTime($value)) {
                try {
                    return new $concrete($value);
                } catch (\Exception) {
                    // reported below
                }
            }
        }

        $shown = is_scalar($value) ? "Value '{$value}'" : 'The given value';
        throw ConversionException::at($path, "must be a valid date and time. {$shown} is not valid.");
    }

    /**
     * PHP reads "x" or "utc" as a time zone alone, which would quietly mean "now".
     */
    private static function holdsDateOrTime(string $value): bool
    {
        $parsed = date_parse($value);

        return $parsed['year'] !== false
            || $parsed['month'] !== false
            || $parsed['day'] !== false
            || $parsed['hour'] !== false
            || isset($parsed['relative'])
            || !isset($parsed['zone_type'])
            || $parsed['zone_type'] === 0;
    }

    /**
     * @param class-string<\BackedEnum> $class
     * @throws ConversionException
     */
    private function toEnum(string $class, mixed $value, string $path): \BackedEnum
    {
        $backing = (string) (new \ReflectionEnum($class))->getBackingType() === 'int'
            ? $this->toInt($value)
            : (is_string($value) || is_int($value) ? (string) $value : null);

        $case = $backing === null ? null : $class::tryFrom($backing);
        if ($case !== null) {
            return $case;
        }

        $values = array_map(static fn(\BackedEnum $case): string => (string) $case->value, $class::cases());
        throw ConversionException::at($path, 'must be one of: ' . implode(', ', $values) . '.');
    }

    /**
     * Unknown fields are left out, so a client can't reach anything undeclared.
     *
     * @param class-string $class
     * @throws ConversionException
     */
    private function toDto(string $class, mixed $value, string $path): object
    {
        self::requireInput($class);

        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw ConversionException::at($path, 'must be an object.');
        }

        $arguments = [];
        $properties = [];
        $errors = [];

        foreach (DtoMeta::getFields($class) as $name => $field) {
            $fieldPath = self::join($path, $name);

            if (!array_key_exists($name, $value)) {
                if ($field->required) {
                    $errors[$fieldPath] = 'is required.';
                } elseif ($field->inConstructor) {
                    $arguments[$name] = $field->hasDefault ? $field->default : null;
                }
                continue;
            }

            $converted = null;
            if (!$this->convertField($field, $value[$name], $fieldPath, $converted, $errors)) {
                continue;
            }

            if ($field->inConstructor) {
                $arguments[$name] = $converted;
            } else {
                $properties[$name] = $converted;
            }
        }

        if ($errors !== []) {
            throw new ConversionException($errors);
        }

        try {
            $dto = new $class(...$arguments);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::at($path, "is not valid: {$e->getMessage()}");
        }

        foreach ($properties as $name => $property) {
            $dto->$name = $property;
        }

        return $dto;
    }

    /**
     * Only the fields that were sent, by the rules of the DTO, which itself isn't created:
     * it couldn't be without its required fields.
     *
     * @param class-string $class
     * @throws ConversionException
     */
    public function toPatch(string $class, mixed $value, string $path): Patch
    {
        if (!DtoMeta::isDto($class)) {
            throw new \LogicException("#[PatchOf] needs a class with #[Dto], which {$class} isn't.");
        }

        self::requireInput($class);

        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw ConversionException::at($path, 'must be an object.');
        }

        $values = [];
        $errors = [];

        foreach (DtoMeta::getFields($class) as $name => $field) {
            if (!array_key_exists($name, $value)) {
                continue;
            }

            $converted = null;
            if ($this->convertField($field, $value[$name], self::join($path, $name), $converted, $errors)) {
                $values[$name] = $converted;
            }
        }

        if ($errors !== []) {
            throw new ConversionException($errors);
        }

        return new Patch($values);
    }

    /**
     * What only describes output would quietly leave out what a client sent.
     *
     * @param class-string $class
     */
    private static function requireInput(string $class): void
    {
        if (!DtoMeta::isInput($class)) {
            throw new \LogicException("{$class} describes what goes out, so it can't take a request. Use a class with #[Dto] for that.");
        }
    }

    /**
     * @param array<string, string> $errors (output)
     */
    private function convertField(DtoField $field, mixed $value, string $path, mixed &$converted, array &$errors): bool
    {
        try {
            $converted = $this->convert($field->type, $value, $path);
        } catch (ConversionException $e) {
            $errors += $e->errors;
            return false;
        }

        if ($converted !== null) {
            foreach ($field->constraints as $constraint) {
                $error = $constraint->validate($converted);
                if ($error !== null) {
                    $errors[$path] = $error;
                    return false;
                }
            }
        }

        return true;
    }

    private function typeError(Type $type, mixed $value, string $path): ConversionException
    {
        return ConversionException::at($path, "must be '{$type}' but '" . get_debug_type($value) . "' given.");
    }

    private static function join(string $path, string $name): string
    {
        return $path === '' ? $name : "{$path}.{$name}";
    }
}

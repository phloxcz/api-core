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

use Phlox\ApiCore\ApiException;
use Phlox\ApiCore\Attributes\PatchOf;
use Phlox\ApiCore\Patch;
use Phlox\ApiCore\Types\DtoMeta;
use Phlox\ApiCore\Types\Type;
use Phlox\ApiCore\Types\TypeReader;
use ReflectionParameter;

/**
 * Turns raw API values into arguments of a controller method, according to its
 * parameter types. Everything wrong is reported at once.
 */
final class ParameterBinder
{
    /** Tried in order; "!" resets the fields a format doesn't contain instead of using the current time. */
    public const DefaultDateTimeFormats = [
        \DateTimeInterface::ATOM,
        \DateTimeInterface::RFC3339_EXTENDED,
        \DateTimeInterface::ISO8601,
        \DateTimeInterface::COOKIE,
        \DateTimeInterface::RFC1036,
        \DateTimeInterface::RFC1123,
        \DateTimeInterface::RFC850,
        \DateTimeInterface::RSS,
        '!Y-m-d',
        '!Y-m-d H:i:s',
        '!Y-m-d H:i',
        '!Y-m-d\TH:i:s',
    ];

    private readonly ValueConverter $converter;

    /**
     * @param list<string> $dateTimeFormats
     */
    public function __construct(array $dateTimeFormats = self::DefaultDateTimeFormats)
    {
        $this->converter = new ValueConverter($dateTimeFormats);
    }

    /**
     * @param array<int|string, mixed> $parameters Named and/or positional parameters
     * @param array<string, mixed> $prebound Values taking precedence over $parameters; a whole
     *        request body, so the errors inside it are reported without the parameter's name
     * @return list<mixed> Positional arguments
     * @throws InvalidParamsException
     */
    public function bind(\ReflectionMethod $method, array $parameters, array $prebound = []): array
    {
        $arguments = [];
        $errors = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $position = $parameter->getPosition();
            $isPrebound = array_key_exists($name, $prebound);

            if ($isPrebound) {
                $value = $prebound[$name];
            } elseif (array_key_exists($name, $parameters)) {
                $value = $parameters[$name];
            } elseif (array_key_exists($position, $parameters)) {
                $value = $parameters[$position];
            } elseif ($parameter->isVariadic()) {
                break;
            } elseif (self::isBound($parameter)) {
                $arguments[] = self::bindToParameter($parameter, null);
                continue;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            } elseif ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            } else {
                $errors[$name] = 'is required.';
                continue;
            }

            try {
                $patchOf = self::getPatchOf($parameter);
                $converted = $patchOf !== null
                    ? ($value === null && $parameter->allowsNull() ? null : $this->converter->toPatch($patchOf, $value, $isPrebound ? '' : $name))
                    : $this->converter->convert(self::getType($parameter), $value, $isPrebound ? '' : $name);
                if (self::isBound($parameter)) {
                    $converted = self::bindToParameter($parameter, $converted);
                }
                $arguments[] = $converted;
                $error = self::validate($parameter, $converted);
                if ($error !== null) {
                    $errors[$name] = $error;
                }
            } catch (ConversionException $e) {
                foreach ($e->errors as $path => $error) {
                    // An error of a whole body has no path of its own
                    $errors[$path === '' ? $name : $path] = $error;
                }
            }
        }

        if ($errors !== []) {
            throw new InvalidParamsException(self::buildMessage($errors), $errors);
        }

        return $arguments;
    }

    /**
     * @return class-string|null The DTO a Patch parameter follows; null for any other parameter
     */
    private static function getPatchOf(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if (!$type instanceof \ReflectionNamedType || $type->getName() !== Patch::class) {
            return null;
        }

        $attribute = $parameter->getAttributes(PatchOf::class)[0] ?? null;
        if ($attribute === null) {
            $method = $parameter->getDeclaringFunction()->getName();
            throw new \LogicException("Parameter \${$parameter->getName()} of {$method}() is a Patch, so it needs #[PatchOf] to say which DTO it follows.");
        }

        return $attribute->newInstance()->class;
    }

    private static function isBound(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType
            && !$type->isBuiltin()
            && is_a($type->getName(), BoundToParameter::class, true);
    }

    private static function bindToParameter(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();
        assert($type instanceof \ReflectionNamedType);

        /** @var class-string<BoundToParameter> $class isBound() guarantees it */
        $class = $type->getName();
        $bound = $value instanceof BoundToParameter ? $value : $class::createEmpty();
        $bound->bindToParameter($parameter);

        return $bound;
    }

    /**
     * A docblock only makes a difference to values holding a DTO. Elsewhere it stays
     * documentation, so an inaccurate `@param int[] $ids` can't start refusing requests.
     */
    private static function getType(ReflectionParameter $parameter): Type
    {
        $type = TypeReader::ofParameter($parameter);

        return $type->containsDto()
            ? $type
            : TypeReader::native($parameter->getType(), $parameter->getDeclaringClass());
    }

    private static function validate(ReflectionParameter $parameter, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (DtoMeta::readConstraints($parameter) as $constraint) {
            $error = $constraint->validate($value);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function buildMessage(array $errors): string
    {
        if (count($errors) !== 1) {
            return ApiException::E_INVALID_PARAMS_TEXT;
        }

        $path = array_key_first($errors);
        $error = $errors[$path];

        return $error === 'is required.'
            ? "Missing required parameter '{$path}'."
            : "Parameter '{$path}' {$error}";
    }
}

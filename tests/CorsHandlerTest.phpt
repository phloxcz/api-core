<?php

declare(strict_types=1);

use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Schema\Processor;
use Phlox\ApiCore\Http\CorsHandler;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

final class RecordingResponse implements IResponse
{
    /** @var array<string, string> */
    public array $sent = [];

    public function setHeader(string $name, string $value): static
    {
        $this->sent[$name] = $value;
        return $this;
    }

    public function addHeader(string $name, string $value): static
    {
        $this->sent[$name] = $value;
        return $this;
    }

    public function setCode(int $code, ?string $reason = null): static
    {
        return $this;
    }

    public function getCode(): int
    {
        return 200;
    }

    public function setContentType(string $type, ?string $charset = null): static
    {
        return $this;
    }

    public function redirect(string $url, int $code = self::S302_Found): void
    {
    }

    public function setExpiration(?string $expire): static
    {
        return $this;
    }

    public function isSent(): bool
    {
        return false;
    }

    public function getHeader(string $header): ?string
    {
        return $this->sent[$header] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->sent;
    }

    public function setCookie(
        string $name,
        string $value,
        string|int|DateTimeInterface|null $expire,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
    ): static {
        return $this;
    }

    public function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null): void
    {
    }
}

$apply = static function (?string $origin, array $config): array {
    $request = new Request(new UrlScript('https://api.test/'), headers: $origin === null ? [] : ['origin' => $origin]);
    $response = new RecordingResponse();
    $full = (new Processor())->process(CorsHandler::getConfigSchema(['POST', 'OPTIONS']), $config);
    (new CorsHandler($request, $response))->apply($full);
    return $response->sent;
};

// no origin, no configuration, or a foreign origin: nothing is sent
Assert::same([], $apply(null, ['allowedOrigins' => ['https://a.test']]));
Assert::same([], $apply('https://a.test', []));
Assert::same([], $apply('https://evil.test', ['allowedOrigins' => ['https://a.test']]));

// listed origin
$headers = $apply('https://a.test', ['allowedOrigins' => ['https://a.test']]);
Assert::same('https://a.test', $headers['Access-Control-Allow-Origin']);
Assert::same('Origin', $headers['Vary']);
Assert::same('POST, OPTIONS', $headers['Access-Control-Allow-Methods']);
Assert::same('1728000', $headers['Access-Control-Max-Age']);
Assert::false(isset($headers['Access-Control-Allow-Credentials']));

// credentials only when configured
$headers = $apply('https://a.test', ['allowedOrigins' => ['https://a.test'], 'allowCredentials' => true]);
Assert::same('true', $headers['Access-Control-Allow-Credentials']);

// a wildcard is sent literally: the request's origin is never reflected
$headers = $apply('https://evil.test', ['allowedOrigins' => ['*']]);
Assert::same('*', $headers['Access-Control-Allow-Origin']);
Assert::false(isset($headers['Access-Control-Allow-Credentials']));

// wildcard + credentials is refused
Assert::exception(
    static fn() => CorsHandler::assertValidConfig(['allowedOrigins' => ['*'], 'allowCredentials' => true], 'api'),
    InvalidArgumentException::class,
);
CorsHandler::assertValidConfig(['allowedOrigins' => ['*'], 'allowCredentials' => false], 'api');
CorsHandler::assertValidConfig(['allowedOrigins' => ['https://a.test'], 'allowCredentials' => true], 'api');

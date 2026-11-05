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
 * API documents of all endpoints, generated when the DI container compiles.
 */
abstract class Documents
{
    /**
     * @param array<string, string> $documents Endpoint name => JSON
     */
    public function __construct(private readonly array $documents)
    {
    }

    /**
     * @return list<string> Endpoints with a document
     */
    public function getEndpoints(): array
    {
        return array_map(strval(...), array_keys($this->documents));
    }

    public function has(string $endpoint): bool
    {
        return isset($this->documents[$endpoint]);
    }

    public function toJson(string $endpoint): string
    {
        return $this->documents[$endpoint]
            ?? throw new \InvalidArgumentException("Endpoint '{$endpoint}' has no document.");
    }

    public function toYaml(string $endpoint): string
    {
        return Yaml::encode($this->toObject($endpoint));
    }

    /**
     * Decoded to objects, because an empty schema {} must not turn into [].
     */
    public function toObject(string $endpoint): object
    {
        return json_decode($this->toJson($endpoint), false, 512, JSON_THROW_ON_ERROR);
    }
}

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

namespace Phlox\ApiCore;

/**
 * A controller method resolved for an API call.
 *
 * Handlers are collected when the DI container compiles and stored in it as
 * plain arrays (see toArray()/fromArray()).
 */
final readonly class Handler
{
    /**
     * @param class-string<ApiController> $class
     * @param list<list<string>> $roles The user must have a role from every group
     */
    public function __construct(
        public string $service,
        public string $class,
        public string $method,
        public bool $authorize,
        public array $roles = [],
    ) {
    }

    /**
     * @param array{service: string, class: class-string<ApiController>, method: string, authorize: bool, roles: list<list<string>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /**
     * @return array{service: string, class: class-string<ApiController>, method: string, authorize: bool, roles: list<list<string>>}
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'class' => $this->class,
            'method' => $this->method,
            'authorize' => $this->authorize,
            'roles' => $this->roles,
        ];
    }
}

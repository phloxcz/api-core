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

namespace Phlox\ApiCore\Attributes;

use Attribute;

/**
 * Requires a logged-in user and, when roles are given, AT LEAST ONE of them
 * (or all of them with `all: true`).
 *
 * Every attribute that applies is a separate condition and all of them must
 * hold, so an attribute on a method can only narrow what its controller or a
 * parent controller allows.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Authorize
{
    /** @var list<string> */
    public readonly array $roles;

    /**
     * @param string|list<string> $roles
     * @param bool $all Require all the roles instead of at least one
     */
    public function __construct(
        array|string $roles = [],
        public readonly bool $all = false,
    ) {
        $this->roles = is_array($roles) ? $roles : [$roles];
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * The roles as groups, where the user needs a role from every group.
     *
     * @return list<list<string>>
     */
    public function getRoleGroups(): array
    {
        if ($this->roles === []) {
            return [];
        }

        return $this->all
            ? array_map(static fn(string $role): array => [$role], $this->roles)
            : [$this->roles];
    }
}

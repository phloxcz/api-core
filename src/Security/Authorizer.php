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

namespace Phlox\ApiCore\Security;

use Nette\Security\User;
use Phlox\ApiCore\ApiException;

final class Authorizer
{
    public const E_UNAUTHORIZED = 401;
    public const E_FORBIDDEN = 403;

    public function __construct(
        private readonly User $user,
    ) {
    }

    /**
     * @param bool $required Whether a logged-in user is required
     * @param list<list<string>> $roleGroups The user must have a role from EVERY group
     * @throws ApiException
     */
    public function check(bool $required, array $roleGroups = []): void
    {
        if (!$required) {
            return;
        }

        if (!$this->user->isLoggedIn()) {
            throw new ApiException(
                self::E_UNAUTHORIZED,
                'Unauthorized',
                'User is not authenticated, but authentication is required.',
            );
        }

        $userRoles = $this->user->getRoles();
        foreach ($roleGroups as $group) {
            if (array_intersect($group, $userRoles) === []) {
                throw new ApiException(
                    self::E_FORBIDDEN,
                    'Forbidden',
                    'User needs at least one of the roles: ' . implode(', ', $group) . '.',
                );
            }
        }
    }
}

<?php

declare(strict_types=1);

use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Phlox\ApiCore\ApiException;
use Phlox\ApiCore\Security\Authorizer;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

final class MemoryStorage implements UserStorage
{
    private ?IIdentity $identity = null;
    private bool $authenticated = false;

    public function saveAuthentication(IIdentity $identity): void
    {
        $this->identity = $identity;
        $this->authenticated = true;
    }

    public function clearAuthentication(bool $clearIdentity): void
    {
        $this->authenticated = false;
    }

    public function getState(): array
    {
        return [$this->authenticated, $this->identity, null];
    }

    public function setExpiration(?string $expire, bool $clearIdentity): void
    {
    }
}

$user = new User(new MemoryStorage());
$authorizer = new Authorizer($user);

$failsWith = static function (int $code, bool $required, array $roles = []) use ($authorizer): void {
    $e = Assert::exception(static fn() => $authorizer->check($required, $roles), ApiException::class);
    Assert::same($code, $e->getCode());
    Assert::same($code, $e->getHttpCode());
};

// anonymous
$authorizer->check(false);
$failsWith(401, true);
$failsWith(401, true, [['admin']]); // not 403: logging in may well help

// "guest" is what Nette reports for anonymous users, it must not count as logged in
$failsWith(401, true, [['guest']]);

// logged in
$user->login(new SimpleIdentity(1, ['a', 'b']));
$authorizer->check(true);
$authorizer->check(true, [['a']]);
$authorizer->check(true, [['a', 'c']]); // one role of a group is enough
$authorizer->check(true, [['a'], ['b']]); // every group must be satisfied
$authorizer->check(true, [['c', 'a'], ['d', 'b']]);
$failsWith(403, true, [['c']]);
$failsWith(403, true, [['c', 'd']]);
$failsWith(403, true, [['a'], ['c']]);

$e = Assert::exception(static fn() => $authorizer->check(true, [['a'], ['c']]), ApiException::class);
Assert::same('Forbidden', $e->getMessage());
Assert::contains('c', $e->getInternalMessage());

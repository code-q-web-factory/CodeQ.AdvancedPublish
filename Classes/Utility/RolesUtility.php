<?php

namespace CodeQ\AdvancedPublish\Utility;

use Neos\Flow\Security\Policy\Role;

class RolesUtility
{
    /**
     * @param  array<Role>  $roles
     * @param  string  $roleIdentifier
     * @return bool
     */
    public static function containsRole(array $roles, string $roleIdentifier): bool
    {
        return array_reduce($roles, static function ($carry, $role) use ($roleIdentifier) {
            if ($carry === true) {
                return true;
            }
            if ($role->getIdentifier() === $roleIdentifier) {
                return true;
            }
            if ($role->getParentRoles() !== null) {
                return self::containsRole($role->getParentRoles(), $roleIdentifier);
            }
            return false;
        }, false);
    }
}

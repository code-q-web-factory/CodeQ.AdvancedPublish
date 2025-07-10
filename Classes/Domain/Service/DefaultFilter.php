<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use CodeQ\AdvancedPublish\Utility\RolesUtility;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Domain\Model\User;

class DefaultFilter implements ReviewerFilterInterface
{

    public function checkFilterConditionsForUserAndNode(User $user, NodeInterface $node): bool
    {
        $neosBackendAccount = UserService::findNeosBackendAccount($user);

        // If the user is a global reviewer, then allow right away
        if (RolesUtility::containsRole($neosBackendAccount->getRoles(), 'CodeQ.AdvancedPublish:CanReview')) {
            return true;
        }

        if (RolesUtility::containsRole($neosBackendAccount->getRoles(), 'Neos.Neos:Administrator')) {
            return true;
        }

        return false;
    }
}

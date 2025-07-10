<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Domain\Model\User;

interface ReviewerFilterInterface
{
    public function checkFilterConditionsForUserAndNode(User $user, NodeInterface $node): bool;
}

<?php

namespace CodeQ\AdvancedPublish\Aop;

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Aspect
 */
class UserServiceAspect
{
    /**
     * @Flow\InjectConfiguration(path="enabled")
     * @var bool
     */
    protected bool $isEnabled = false;

    /**
     * @param  JoinPointInterface  $joinPoint
     * @return bool
     *
     * @Flow\Around("method(Neos\Neos\Domain\Service\UserService->currentUserCanPublishToWorkspace())")
     */
    public function aroundCurrentUserCanPublishToWorkspace(JoinPointInterface $joinPoint)
    {
        if (!$this->isEnabled) {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        /** @var Workspace $workspace */
        $workspace = $joinPoint->getMethodArgument('workspace');

        if ($workspace->getName() !== 'live') {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        return false;
    }
}

<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Neos\Domain\Model\User;

class PolicyService
{
    /**
     * @Flow\Inject
     * @var PrivilegeManagerInterface
     */
    protected $privilegeManager;

    /**
     * @Flow\InjectConfiguration(path="reviewers.filterImplementations")
     * @var array<string>
     */
    protected array $reviewerFilterImplementations;

    public function checkReviewerAllowedToPublishNode(User $reviewer, Node $node): void
    {
        array_map(static function ($filterClassName) use ($reviewer, $node) {
            if (class_exists($filterClassName)) {
                $filterInstance = new $filterClassName();
                if ($filterInstance instanceof ReviewerFilterInterface) {
                    $filterInstance->checkFilterConditionsForUserAndNode($reviewer, $node);
                }
            }
            return true;
        }, $this->reviewerFilterImplementations);
    }
}

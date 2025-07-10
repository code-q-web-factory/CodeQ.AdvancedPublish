<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use CodeQ\AdvancedPublish\Exception\ReviewerNotAllowedToPublishException;
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
        $results = array_map(static function ($filterClassName) use ($reviewer, $node) {
            if (class_exists($filterClassName)) {
                $filterInstance = new $filterClassName();
                if ($filterInstance instanceof ReviewerFilterInterface) {
                    return $filterInstance->checkFilterConditionsForUserAndNode($reviewer, $node);
                }
            }
            return false;
        }, $this->reviewerFilterImplementations);

        // If no filter implementations exist or none of them return true, throw an exception
        if (empty($results) || !in_array(true, $results, true)) {
            throw new ReviewerNotAllowedToPublishException('Der ausgewählter Reviewer darf diese Inhalte nicht veröffentlichen.', 1747138554895);
        }
    }
}

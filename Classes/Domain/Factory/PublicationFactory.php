<?php

namespace CodeQ\AdvancedPublish\Domain\Factory;

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Model\PublicationInterface;
use CodeQ\AdvancedPublish\Domain\Service\PolicyService;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\AdvancedPublish\Exception\ReviewerNotAllowedToPublishException;
use CodeQ\AdvancedPublish\Utility\IpAnonymizer;
use Neos\ContentRepository\Domain\Service\PublishingService;
use NEOSidekick\Revisions\Domain\Model\Revision;
use DateTimeImmutable;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Neos\Domain\Model\User;

/**
 * @Flow\Scope("singleton")
 */
class PublicationFactory
{
    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var PolicyService
     */
    protected $policyService;

    /**
     * @param  User  $reviewer
     * @return Publication
     * @throws ReviewerNotAllowedToPublishException
     */
    public function fromCurrentUserAndReviewer(User $reviewer): Publication
    {
        $currentUser = $this->userService->getCurrentlyAuthenticatedUser();
        $publication = new Publication($currentUser);
        $publication->setEditorIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));
        $publication->setReviewer($reviewer);

        $publicWorkspace = $this->userService->findPublicWorkspaceForCurrentUser();
        $publication->setWorkspace($publicWorkspace);

        array_map(
            function ($node) use ($reviewer) {
                $this->policyService->checkReviewerAllowedToPublishNode($reviewer, $node);
            },
            $this->publishingService->getUnpublishedNodes($publicWorkspace)
        );

        return $publication;
    }

    /**
     * @param  Revision  $revision
     * @return Publication
     */
    public function fromCurrentUserAndRevision(Revision $revision): Publication
    {
        $currentUser = $this->userService->getCurrentlyAuthenticatedUser();
        $publication = new Publication($currentUser);
        $publication->setEditorIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));
        $publication->setReviewer($currentUser);
        $publication->setReviewerIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));
        $publication->setStatus(PublicationInterface::STATUS_APPROVED);
        $publication->setResolved(new DateTimeImmutable());

        $publicWorkspace = $this->userService->findPublicWorkspaceForCurrentUser();
        $publication->setWorkspace($publicWorkspace);

        $publication->setRevision($revision);

        return $publication;
    }
}

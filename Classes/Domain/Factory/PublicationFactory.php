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
use Neos\ContentRepository\Domain\Model\Workspace;
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
        $publicWorkspace = $this->userService->findPublicWorkspaceForCurrentUser();

        if (!$publicWorkspace) {
            throw new \RuntimeException('Public workspace not found', 1752179675157);
        }

        $publication = $this->fromEditorAndReviewerForWorkspace($this->userService->getCurrentlyAuthenticatedUser(), $reviewer, $publicWorkspace);
        $publication->setEditorIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));

        return $publication;
    }

    /**
     * @param  User  $editor
     * @param  User  $reviewer
     * @param  Workspace  $workspace
     * @return Publication
     * @throws ReviewerNotAllowedToPublishException
     */
    public function fromEditorAndReviewerForWorkspace(User $editor, User $reviewer, Workspace $workspace): Publication
    {
        $publication = new Publication($editor);
        $publication->setReviewer($reviewer);
        $publication->setWorkspace($workspace);

        array_map(
            function ($node) use ($reviewer) {
                $this->policyService->checkReviewerAllowedToPublishNode($reviewer, $node);
            },
            $this->publishingService->getUnpublishedNodes($workspace)
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

        if (!$publicWorkspace) {
            throw new \RuntimeException('Public workspace not found', 1752179675157);
        }

        $publication->setWorkspace($publicWorkspace);

        $publication->setRevision($revision);

        return $publication;
    }
}

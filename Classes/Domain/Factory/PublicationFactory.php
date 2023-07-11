<?php

namespace CodeQ\AdvancedPublish\Domain\Factory;

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Model\PublicationInterface;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\AdvancedPublish\Utility\IpAnonymizer;
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
     * @param  User  $reviewer
     * @return Publication
     */
    public function fromCurrentUserAndReviewer(User $reviewer): Publication
    {
        $currentUser = $this->userService->getCurrentlyAuthenticatedUser();
        $publication = new Publication($currentUser);
        $publication->setEditorIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));
        $publication->setReviewer($reviewer);

        $publicWorkspace = $this->userService->findPublicWorkspaceForCurrentUser();
        $publication->setWorkspace($publicWorkspace);

        return $publication;
    }

    /**
     * @param  User  $user
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

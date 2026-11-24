<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use CodeQ\AdvancedPublish\Domain\Factory\PublicationFactory;
use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Model\PublicationInterface;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use CodeQ\AdvancedPublish\Exception\ConcurrentPublicationException;
use CodeQ\AdvancedPublish\Utility\IpAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Flow\Utility\Now;
use Neos\Http\Factories\ServerRequestFactory;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Neos\Service\PublishingService;

/**
 * @Flow\Scope("singleton")
 */
class PublicationService
{
    protected const WORKSPACE_LOCK_TIMEOUT_SECONDS = 120;

    /**
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var PublicationRepository
     */
    protected $publicationRepository;

    /**
     * @Flow\Inject
     * @var WorkspaceService
     */
    protected $workspaceService;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var PublicationFactory
     */
    protected $publicationFactory;

    /**
     * @Flow\Inject
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @Flow\Inject
     * @var ServerRequestFactory
     */
    protected $serverRequestFactory;

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var Now
     */
    protected $now;

    /**
     * @param  User  $reviewer
     * @param  string|null  $comment
     * @return Publication
     * @throws Exception
     */
    public function create(User $reviewer, string $comment = null): Publication
    {
        $publication = $this->addPublicationForCurrentUser($reviewer, $comment);

        $this->emitPublicationCreated($publication);

        return $publication;
    }

    /**
     * Creates a publication of the current user's public workspace and publishes it right away
     *
     * @param  User  $reviewer
     * @param  string|null  $comment
     * @return Publication
     * @throws Exception
     */
    public function createAndApprove(User $reviewer, string $comment = null): Publication
    {
        $workspace = $this->userService->findPublicWorkspaceForCurrentUser();
        if (!$workspace) {
            throw new \RuntimeException('Public workspace not found', 1752179675157);
        }

        $publication = $this->whileWorkspaceIsLocked($workspace, function () use ($reviewer, $comment) {
            $publication = $this->addPublicationForCurrentUser($reviewer, $comment);
            $this->publishAndPersist($publication);
            return $publication;
        });

        $this->emitPublicationCreated($publication);
        $this->emitPublicationPublishedAndClosed($publication);

        return $publication;
    }

    /**
     * @param  User  $reviewer
     * @param  string|null  $comment
     * @return Publication
     */
    protected function addPublicationForCurrentUser(User $reviewer, ?string $comment): Publication
    {
        $publication = null;
        $this->securityContext->withoutAuthorizationChecks(function () use ($reviewer, $comment, &$publication) {
            $publication = $this->publicationFactory->fromCurrentUserAndReviewer($reviewer);
            $publication->setComment($comment);
            $this->publicationRepository->add($publication);
        });

        return $publication;
    }

    /**
     * @param  Publication  $publication
     * @return void
     *
     * @Flow\Signal
     */
    public function emitPublicationCreated(Publication $publication): void
    {
    }

    /**
     * Records an approved publication for the given workspace and publishes the workspace,
     * e.g. from the command line where large publications cannot time out
     *
     * @param  Workspace  $workspace
     * @param  User  $editor
     * @param  User  $reviewer
     * @param  string|null  $comment
     * @return Publication
     * @throws Exception
     */
    public function createAndPublish(Workspace $workspace, User $editor, User $reviewer, string $comment = null): Publication
    {
        $publication = $this->whileWorkspaceIsLocked($workspace, function () use ($workspace, $editor, $reviewer, $comment) {
            $publication = null;
            $this->securityContext->withoutAuthorizationChecks(function () use ($workspace, $editor, $reviewer, $comment, &$publication) {
                $publication = $this->publicationFactory->fromEditorAndReviewerForWorkspace($editor, $reviewer, $workspace);
                $publication->setEditorIpAddress($this->anonymizedClientIp());
                $publication->setComment($comment);
                $this->publicationRepository->add($publication);
            });
            $this->publishAndPersist($publication);
            return $publication;
        });

        // Self-reviews notify the administrators as in the backend module; a review request to another reviewer would already be resolved
        if ($editor === $reviewer) {
            $this->emitPublicationCreated($publication);
        }
        $this->emitPublicationPublishedAndClosed($publication);

        return $publication;
    }

    /**
     * @param  Publication  $publication
     * @return void
     * @throws Exception
     */
    public function publishAndClose(Publication $publication): void
    {
        $this->whileWorkspaceIsLocked($publication->getWorkspace(), function () use ($publication) {
            $this->publishAndPersist($publication);
        });

        $this->emitPublicationPublishedAndClosed($publication);
    }

    /**
     * Serializes the publications of one workspace across requests and instances. Otherwise a double submit or two
     * approvals at once publish the same nodes twice, and the later flush fails with an OptimisticLockException.
     *
     * @param  Workspace  $workspace
     * @param  \Closure  $callback
     * @return mixed the callback's result
     * @throws ConcurrentPublicationException
     */
    protected function whileWorkspaceIsLocked(Workspace $workspace, \Closure $callback)
    {
        $connection = $this->entityManager->getConnection();
        // MySQL and MariaDB limit lock names to 64 characters
        $lockName = 'CodeQ.AdvancedPublish:' . sha1($workspace->getName());
        if ((int)$connection->fetchOne('SELECT GET_LOCK(?, ?)', [$lockName, self::WORKSPACE_LOCK_TIMEOUT_SECONDS]) !== 1) {
            throw ConcurrentPublicationException::stillInProgress();
        }

        try {
            $unpublishedNodes = [];
            $this->securityContext->withoutAuthorizationChecks(function () use ($workspace, &$unpublishedNodes) {
                $unpublishedNodes = $this->publishingService->getUnpublishedNodes($workspace);
            });
            // The request that held the lock before has published everything already
            if ($unpublishedNodes === []) {
                throw ConcurrentPublicationException::nothingLeftToPublish();
            }

            return $callback();
        } finally {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    /**
     * Persists right away, so that notifications are only sent for a publication that is stored
     *
     * @param  Publication  $publication
     * @return void
     * @throws Exception
     */
    protected function publishAndPersist(Publication $publication): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use (&$publication) {
            $publication->setResolved($this->now);
            $publication->setReviewerIpAddress($this->anonymizedClientIp());
            $publication->setChanges($this->workspaceService->renderStaticSiteChanges($this->workspaceService->computeSiteChanges($publication->getWorkspace())));

            $unpublishedNodes = $this->publishingService->getUnpublishedNodes($publication->getWorkspace());
            $this->publishingService->publishNodes($unpublishedNodes);

            $publication->setStatus(PublicationInterface::STATUS_APPROVED);
            $this->publicationRepository->update($publication);
        });

        $this->persistenceManager->persistAll();
    }

    /**
     * @param  Publication  $publication
     * @return void
     *
     * @Flow\Signal
     */
    public function emitPublicationPublishedAndClosed(Publication $publication): void
    {
    }

    /**
     * @param  Publication  $publication
     * @return void
     * @throws Exception
     */
    public function declineAndClose(Publication $publication): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use (&$publication) {
            $publication->setResolved($this->now);
            $publication->setReviewerIpAddress($this->anonymizedClientIp());
            $publication->setChanges($this->workspaceService->renderStaticSiteChanges($this->workspaceService->computeSiteChanges($publication->getWorkspace())));
            $publication->setStatus(PublicationInterface::STATUS_DECLINED);
            $this->publicationRepository->update($publication);
        });

        $this->emitPublicationDeclinedAndClosed($publication);
    }

    /**
     * @param  Publication  $publication
     * @return void
     *
     * @Flow\Signal
     */
    public function emitPublicationDeclinedAndClosed(Publication $publication): void
    {
    }

    /**
     * @param  Publication  $publication
     * @return void
     * @throws IllegalObjectTypeException
     */
    public function withdraw(Publication $publication): void
    {
        $publication->setStatus(PublicationInterface::STATUS_WITHDRAWN);
        $publication->setChanges($this->workspaceService->renderStaticSiteChanges($this->workspaceService->computeSiteChanges($publication->getWorkspace())));
        $this->publicationRepository->update($publication);

        $this->emitPublicationWithdrawn($publication);
    }

    /**
     * @param  Publication  $publication
     * @return void
     *
     * @Flow\Signal
     */
    public function emitPublicationWithdrawn(Publication $publication): void
    {
    }

    /**
     * Removes information about editor and reviewer from the Publication
     *
     * @param  Publication  $publication
     * @return void
     * @throws IllegalObjectTypeException
     */
    public function anonymize(Publication $publication): void
    {
        $publication->setEditor(null);
        $publication->setEditorIpAddress(null);
        $publication->setReviewer(null);
        $publication->setReviewerIpAddress(null);

        $this->publicationRepository->update($publication);
    }

    /**
     * @param  Publication  $publication
     * @return void
     *
     * @Flow\Signal
     */
    public function emitPublicationAnonymized(Publication $publication): void
    {
    }

    /**
     * Command line requests have no client address
     *
     * @return string
     */
    protected function anonymizedClientIp(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']) : '';
    }
}

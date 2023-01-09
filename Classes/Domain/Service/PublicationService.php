<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use CodeQ\AdvancedPublish\Domain\Factory\PublicationFactory;
use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Model\PublicationInterface;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use CodeQ\AdvancedPublish\Utility\IpAnonymizer;
use DateTimeImmutable;
use Exception;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Flow\Security\Policy\Role;
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
        $publication = null;
        $this->securityContext->withoutAuthorizationChecks(function () use ($reviewer, $comment, &$publication) {
            $publication = $this->publicationFactory->fromCurrentUserAndReviewer($reviewer);
            $publication->setComment($comment);
            $this->publicationRepository->add($publication);
        });

        $this->emitPublicationCreated($publication);

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
     * @return array<User>
     */
    public function findAuthorizedReviewers(): array
    {
        $authorizedReviewers = [];
        $userIterator = $this->userRepository->findAllIterator();
        /** @var User $user */
        foreach ($userIterator as $user) {
            foreach ($user->getAccounts() as $account) {
                if ($account->hasRole(new Role('CodeQ.AdvancedPublish:LivePublisher'))) {
                    $authorizedReviewers[] = $user;
                    break;
                }
            }
        }

        return $authorizedReviewers;
    }

    /**
     * @param  Publication  $publication
     * @return void
     * @throws Exception
     */
    public function publishAndClose(Publication $publication): void
    {
        $this->securityContext->withoutAuthorizationChecks(function () use (&$publication) {
            $publication->setResolved($this->now);
            $publication->setReviewerIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));
            $publication->setChanges($this->workspaceService->renderStaticSiteChanges($this->workspaceService->computeSiteChanges($publication->getWorkspace())));

            $unpublishedNodes = $this->publishingService->getUnpublishedNodes($publication->getWorkspace());
            $this->publishingService->publishNodes($unpublishedNodes);

            $publication->setStatus(PublicationInterface::STATUS_APPROVED);
            $this->publicationRepository->update($publication);
        });

        $this->emitPublicationPublishedAndClosed($publication);
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
            $publication->setReviewerIpAddress(IpAnonymizer::anonymizeIp($_SERVER['REMOTE_ADDR']));
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
}

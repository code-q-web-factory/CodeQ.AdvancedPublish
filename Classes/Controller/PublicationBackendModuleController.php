<?php

namespace CodeQ\AdvancedPublish\Controller;

/*
 * This file is part of the CodeQ.AdvancedPublish package.
 */

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use CodeQ\AdvancedPublish\Domain\Service\PublicationService;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\AdvancedPublish\Domain\Service\WorkspaceService;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\UserRepository;

class PublicationBackendModuleController extends AbstractModuleController
{
    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var PublicationRepository
     */
    protected $publicationRepository;

    /**
     * @Flow\Inject
     * @var PublicationService
     */
    protected $publicationService;

    /**
     * @Flow\Inject
     * @var WorkspaceService
     */
    protected $workspaceService;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject()
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @param  string|null  $filter
     * @return void
     */
    public function indexAction(string $filter = null): void
    {
        if ($filter === 'all') {
            $publications = $this->publicationRepository->findAll();
        } else {
            if ($filter === 'assigned') {
                $publications = $this->publicationRepository->findByReviewer($this->userService->getCurrentlyAuthenticatedUser());
            } else {
                $publications = $this->publicationRepository->findByEditor($this->userService->getCurrentlyAuthenticatedUser());
            }
        }

        $this->view->assignMultiple([
            'publications' => $publications,
            'filter' => $filter,
        ]);
    }

    /**
     * @return void
     */
    public function newAction(): void
    {
        $authorizedReviewers = $this->userService->getAuthorizedReviewers();
        $currentUser = $this->userService->getCurrentlyAuthenticatedUser();

        $userHasNoPendingPublications = $this->publicationRepository->countPendingByEditor($currentUser) === 0;
        $changesCount = $this->workspaceService->computeChangesCount($this->userService->findPublicWorkspaceForCurrentUser());
        $siteChanges = $this->workspaceService->computeSiteChanges($this->userService->findPublicWorkspaceForCurrentUser());
        $this->view->assignMultiple([
            'authorizedReviewers' => $authorizedReviewers,
            'canCreateNewPublication' => $userHasNoPendingPublications && $changesCount['total'] > 0,
            'siteChanges' => $siteChanges,
            'hasSiteChanges' => $changesCount['total'] > 0,
            'userHasNoPendingPublications' => $userHasNoPendingPublications,
            'currentUser' => $this->userService->getCurrentlyAuthenticatedUser(),
            'pendingPublication' => $this->publicationRepository->findPendingByEditor($currentUser)->getFirst(),
        ]);
    }

    /**
     * @param  User  $reviewer
     * @param  string|null  $comment
     * @return void
     *
     * @Flow\Validate(argumentName="reviewer", type="NotEmpty")
     * @throws StopActionException
     */
    public function createAction(User $reviewer, string $comment = null)
    {
        $this->publicationService->create($reviewer, $comment);
        $this->redirect('index');
    }

    /**
     * @param  Publication  $publication
     * @return void
     */
    public function reviewAction(Publication $publication): void
    {
        $this->view->assignMultiple([
            'publication' => $publication,
            'siteChanges' => $this->workspaceService->computeSiteChanges($publication->getWorkspace()),
        ]);
    }

    /**
     * @param  Publication  $publication
     * @param  string  $action
     * @return void
     * @throws StopActionException
     */
    public function resolveAction(Publication $publication, string $action = 'approve'): void
    {
        if ($action === 'approve') {
            $this->publicationService->publishAndClose($publication);
        } else {
            $this->publicationService->declineAndClose($publication);
        }
        $this->redirect('index');
    }

    /**
     * @param  Publication  $publication
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    public function showAction(Publication $publication): void
    {
        if ($publication->getStatus() === 'pending') {
            $siteChanges = $this->workspaceService->computeSiteChanges($publication->getWorkspace());
        } else {
            $siteChanges = $this->workspaceService->hydrateStaticSiteChanges($publication->getChanges());
        }

        $revisionPageTitle = null;
        if ($publication->getRevision()) {
            /** @var NodeData|null $revisionNodeData */
            $revisionNodeDatas = $this->nodeDataRepository->findByNodeIdentifier($publication->getRevision()->getNodeIdentifier());
            /** @var NodeData $nodeData */
            foreach ($revisionNodeDatas as $nodeData) {
                if ($nodeData->getDimensionValues()['language'][0] !== 'de') {
                    continue;
                }
                $revisionPageTitle = $nodeData->getProperty('title');
            }
        }

        $this->view->assignMultiple([
            'publication' => $publication,
            'siteChanges' => $siteChanges,
            'avoidPrinting' => $this->settings['protocol']['avoidPrinting'],
            'avoidCopying' => $this->settings['protocol']['avoidCopying'],
            'revisionPageTitle' => $revisionPageTitle,
        ]);
    }

    /**
     * @param  Publication  $publication
     * @return void
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     */
    public function withdrawAction(Publication $publication): void
    {
        $this->publicationService->withdraw($publication);
        // todo flash message
        $this->redirect('index');
    }

    /**
     * @return void
     * @throws StopActionException
     */
    public function discardPersonalWorkspaceAction(): void
    {
        $this->userService->discardOwnPublicWorkspaceAndWithdrawPendingPublications();
        // todo flash message
        $this->redirect('index');
    }

    /**
     * @param  User  $reviewer
     * @param  string|null  $comment
     * @return void
     */
    public function createAndApproveAction(User $reviewer, string $comment = null): void
    {
        $publication = $this->publicationService->create($reviewer, $comment);
        $this->publicationService->publishAndClose($publication);
        $this->redirect('index');
    }
}

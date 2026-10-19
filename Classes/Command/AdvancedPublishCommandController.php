<?php

namespace CodeQ\AdvancedPublish\Command;

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Model\PublicationInterface;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use CodeQ\AdvancedPublish\Domain\Service\PublicationService;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\AdvancedPublish\Exception\ReviewerNotAllowedToPublishException;
use DateTime;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Neos\Domain\Service\UserService as NeosUserService;
use Neos\Neos\Service\PublishingService;

class AdvancedPublishCommandController extends CommandController
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
     * @var PublicationService
     */
    protected $publicationService;

    /**
     * @Flow\Inject
     * @var NeosUserService
     */
    protected $neosUserService;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @return void
     */
    public function setupCommand(): void
    {
        $users = $this->userRepository->findAll();
        $this->output->progressStart($users->count());

        /** @var User $user */
        foreach ($users as $user) {
            $this->userService->createWorkspacesForUser($user);
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();
    }

    /**
     * Publishes a workspace to live and records it as an approved publication
     *
     * Use this instead of the backend module for publications that are too large for a web request.
     * The reviewer is checked like in the backend module: reviewer role, reviewer filters for every
     * change and, if editor and reviewer are the same user, the right to review own requests.
     *
     * @param string $workspace Name of the workspace to publish, it must be based on live
     * @param string $editor Username of the editor who made the changes
     * @param string $reviewer Username of the reviewer who approved the changes
     * @param string|null $comment Comment for the publication protocol
     * @return void
     * @throws \Exception
     */
    public function publishCommand(string $workspace, string $editor, string $reviewer, string $comment = null): void
    {
        $workspaceToPublish = $this->workspaceRepository->findOneByName($workspace);
        if (!$workspaceToPublish instanceof Workspace) {
            $this->outputLine('<error>Workspace "%s" does not exist.</error>', [$workspace]);
            $this->quit(1);
        }
        if ($workspaceToPublish->getBaseWorkspace()?->getName() !== 'live') {
            $this->outputLine('<error>Workspace "%s" is not based on live.</error>', [$workspace]);
            $this->quit(1);
        }

        $editorUser = $this->findUserByUsername($editor);
        $reviewerUser = $this->findUserByUsername($reviewer);
        if (!$this->userService->isAuthorizedReviewer($reviewerUser, $editorUser)) {
            $this->outputLine('<error>User "%s" is not allowed to review changes of "%s".</error>', [$reviewer, $editor]);
            $this->quit(1);
        }

        $nodeCount = count($this->publishingService->getUnpublishedNodes($workspaceToPublish));
        if ($nodeCount === 0) {
            $this->outputLine('<error>Workspace "%s" has no unpublished changes.</error>', [$workspace]);
            $this->quit(1);
        }

        $this->outputLine('Publishing %d nodes of workspace "%s" to live ...', [$nodeCount, $workspace]);
        try {
            $publication = $this->publicationService->createAndPublish($workspaceToPublish, $editorUser, $reviewerUser, $comment);
        } catch (ReviewerNotAllowedToPublishException $exception) {
            $this->outputLine('<error>%s</error>', [$exception->getMessage()]);
            $this->quit(1);
        }
        $this->outputLine('Published and recorded as publication %s.', [$this->persistenceManager->getIdentifierByObject($publication)]);
    }

    /**
     * Approves a pending publication and publishes its workspace to live
     *
     * Does the same as the approve button in the backend module, for publications that are too large
     * for a web request. The publication keeps the reviewer the editor requested.
     *
     * @param string $publication Identifier of the pending publication, e.g. from the review link in the backend module
     * @return void
     * @throws \Exception
     */
    public function approveCommand(string $publication): void
    {
        $publicationToApprove = $this->publicationRepository->findByIdentifier($publication);
        if (!$publicationToApprove instanceof Publication) {
            $this->outputLine('<error>Publication "%s" does not exist.</error>', [$publication]);
            $this->quit(1);
        }
        if ($publicationToApprove->getStatus() !== PublicationInterface::STATUS_PENDING) {
            $this->outputLine('<error>Publication "%s" is %s, only pending publications can be approved.</error>', [$publication, $publicationToApprove->getStatus()]);
            $this->quit(1);
        }

        $this->outputLine('Publishing %d nodes of workspace "%s" to live, requested by %s for review by %s ...', [
            count($this->publishingService->getUnpublishedNodes($publicationToApprove->getWorkspace())),
            $publicationToApprove->getWorkspace()->getName(),
            $publicationToApprove->getEditor()?->getLabel() ?? '-',
            $publicationToApprove->getReviewer()?->getLabel() ?? '-',
        ]);
        $this->publicationService->publishAndClose($publicationToApprove);
        $this->outputLine('Approved and published.');
    }

    /**
     * Anonymizes outdated publications
     * by removing information about
     * the editor and the reviewer,
     * if CodeQ.AdvancedPublish.anonymizeCommand.lifetime
     * is set to a valid date modifier string.
     *
     * @return void
     * @throws IllegalObjectTypeException
     */
    public function anonymizeCommand(): void
    {
        $lifetime = $this->settings['anonymizeCommand']['lifetime'];

        if ($lifetime !== false) {
            $dateTime = new DateTime();
            $dateTime = $dateTime->modify(sprintf('-%s', $lifetime));
            $publicationsToBeAnonymized = $this->publicationRepository->findByCreationDateOrEarlier($dateTime);

            $this->output->output(PHP_EOL . 'Found %s publications for anonymization' . PHP_EOL, [$publicationsToBeAnonymized->count()]);

            $this->output->progressStart($publicationsToBeAnonymized->count());
            /** @var Publication $publication */
            foreach ($publicationsToBeAnonymized->toArray() as $publication) {
                $this->publicationService->anonymize($publication);
                $this->output->progressAdvance();
            }
            $this->output->progressFinish();
            $this->output->output(PHP_EOL);
        } else {
            $this->output->output(PHP_EOL . 'This command is disabled by configuration. To enable, set the configuration of CodeQ.AdvancedPublish.anonymizeCommand.lifetime to a valid date modifier string.' . PHP_EOL);
        }
    }

    /**
     * Removes outdated publications from the database,
     * if CodeQ.AdvancedPublish.cleanupCommand.lifetime
     * is set to a valid date modifier string.
     *
     * @return void
     * @throws IllegalObjectTypeException
     */
    public function cleanupCommand(): void
    {
        $lifetime = $this->settings['cleanupCommand']['lifetime'];

        if ($lifetime !== false) {
            $dateTime = new DateTime();
            $dateTime = $dateTime->modify(sprintf('-%s', $lifetime));
            $publicationsToBeRemoved = $this->publicationRepository->findByCreationDateOrEarlier($dateTime);

            $this->output->output(PHP_EOL . 'Found %s publications for removal' . PHP_EOL, [$publicationsToBeRemoved->count()]);

            $this->output->progressStart($publicationsToBeRemoved->count());
            /** @var Publication $publication */
            foreach ($publicationsToBeRemoved->toArray() as $publication) {
                $this->publicationRepository->remove($publication);
                $this->output->progressAdvance();
            }
            $this->output->progressFinish();
            $this->output->output(PHP_EOL);
        } else {
            $this->output->output(PHP_EOL . 'This command is disabled by configuration. To enable, set the configuration of CodeQ.AdvancedPublish.cleanupCommand.lifetime to a valid date modifier string.' . PHP_EOL);
        }
    }

    /**
     * @param  string  $username
     * @return User
     */
    protected function findUserByUsername(string $username): User
    {
        $user = $this->neosUserService->getUser($username);
        if (!$user instanceof User) {
            $this->outputLine('<error>User "%s" does not exist.</error>', [$username]);
            $this->quit(1);
        }

        return $user;
    }
}

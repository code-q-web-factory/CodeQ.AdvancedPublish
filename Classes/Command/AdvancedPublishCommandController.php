<?php

namespace CodeQ\AdvancedPublish\Command;

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use CodeQ\AdvancedPublish\Domain\Service\PublicationService;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use DateTime;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\UserRepository;

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
}

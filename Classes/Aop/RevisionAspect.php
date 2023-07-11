<?php

namespace CodeQ\AdvancedPublish\Aop;

use CodeQ\AdvancedPublish\Domain\Factory\PublicationFactory;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use NEOSidekick\Revisions\Domain\Model\Revision;
use Exception;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * @Flow\Aspect
 */
class RevisionAspect
{
    /**
     * @Flow\InjectConfiguration(path="enabled")
     * @var bool
     */
    protected bool $isEnabled = false;

    /**
     * @Flow\Inject()
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject()
     * @var PublicationFactory
     */
    protected $publicationFactory;

    /**
     * @Flow\Inject()
     * @var PublicationRepository
     */
    protected $publicationRepository;

    /**
     * @param  JoinPointInterface  $joinPoint
     * @return void
     *
     * @Flow\AfterReturning("method(NEOSidekick\Revisions\Service\RevisionService->applyRevision())")
     */
    public function afterReturningApplyRevision(JoinPointInterface $joinPoint): void
    {
        if ($this->isEnabled) {
            try {
                $revisionIdentifier = $joinPoint->getMethodArgument('identifier');
                /** @var Revision|null $revision */
                $revision = $this->persistenceManager->getObjectByIdentifier($revisionIdentifier, Revision::class);

                if (!is_null($revision)) {
                    $publication = $this->publicationFactory->fromCurrentUserAndRevision($revision);
                    $this->publicationRepository->add($publication);
                }
            } catch (Exception $e) {
            }
        }
    }
}

<?php

namespace CodeQ\AdvancedPublish;

use CodeQ\AdvancedPublish\Domain\Service\NotificationService;
use CodeQ\AdvancedPublish\Domain\Service\PublicationService;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Domain\Service\UserService;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap)
    {
        $bootstrap->getSignalSlotDispatcher()->connect(ConfigurationManager::class, 'configurationManagerReady', function (ConfigurationManager $configurationManager) use ($bootstrap) {
            $isEnabled = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'CodeQ.AdvancedPublish.enabled');
            if ($isEnabled) {
                $dispatcher = $bootstrap->getSignalSlotDispatcher();
                $dispatcher->connect(UserService::class, 'userCreated', Domain\Service\UserService::class, 'createWorkspacesForUser');
                $dispatcher->connect(UserService::class, 'userDeleted', Domain\Service\UserService::class, 'onUserDeleted');

                $dispatcher->connect(PublicationService::class, 'publicationCreated', NotificationService::class, 'sendCreationNotification');
                $dispatcher->connect(PublicationService::class, 'publicationWithdrawn', NotificationService::class, 'sendWithdrawalNotificationToReviewer');
                $dispatcher->connect(PublicationService::class, 'publicationPublishedAndClosed', NotificationService::class, 'sendPublishedNotification');
                $dispatcher->connect(PublicationService::class, 'publicationDeclinedAndClosed', NotificationService::class, 'sendDeclinedNotification');
            }
        });
    }
}

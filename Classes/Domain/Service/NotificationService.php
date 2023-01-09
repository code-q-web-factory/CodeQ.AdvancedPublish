<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Http\Factories\ServerRequestFactory;
use Sandstorm\TemplateMailer\Domain\Service\EmailService;

/**
 * @Flow\Scope("singleton")
 */
class NotificationService
{
    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var EmailService
     */
    protected $emailService;

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
     * @var ActionRequestFactory
     */
    protected $actionRequestFactory;

    /**
     * @Flow\InjectConfiguration(path="notifications")
     * @var array
     */
    protected $notificationSettings;

    /**
     * @param  Publication  $publication
     * @return void
     */
    public function sendCreationNotification(Publication $publication): void
    {
        if ($publication->getEditor() !== $publication->getReviewer()) {
            // Notify reviewer that there is a new review
            $email = $this->userService->getEmailAddressForUser($publication->getReviewer());
            if ($email) {
                $this->sendEmail(
                    'ReviewerNotification',
                    'Neue Anfrage zur Begutachtung',
                    [$email],
                    [
                        'publication' => $publication,
                        'uriToPublication' => $this->getUriToPublicationInBackendModule($publication, 'review'),
                    ]
                );
            }
        } else {
            // Notify configured email that a user create a self-reviewed request
            $administratorEmails = $this->notificationSettings['administratorEmails'];
            if (sizeof($administratorEmails)) {
                $this->sendEmail(
                    'AdministratorCreationNotification',
                    'Neue selbst-begutachtete Freigabeanfrage',
                    $administratorEmails,
                    [
                        'publication' => $publication,
                        'uriToPublication' => $this->getUriToPublicationInBackendModule($publication),
                    ]
                );
            }
        }
    }

    /**
     * @param  Publication  $publication
     * @return void
     */
    public function sendWithdrawalNotificationToReviewer(Publication $publication): void
    {
        if ($publication->getEditor() !== $publication->getReviewer()) {
            $email = $this->userService->getEmailAddressForUser($publication->getReviewer());
            if ($email) {
                $this->sendEmail(
                    'ReviewerWithdrawalNotification',
                    'Begutachtungsanfrage wurde zurückgezogen',
                    [$email],
                    [
                        'publication' => $publication,
                    ]
                );
            }
        }
    }

    /**
     * @param  Publication  $publication
     * @return void
     */
    public function sendPublishedNotification(Publication $publication): void
    {
        if ($publication->getEditor() !== $publication->getReviewer()) {
            $email = $this->userService->getEmailAddressForUser($publication->getEditor());
            if ($email) {
                $this->sendEmail(
                    'EditorApprovalNotification',
                    'Begutachtungsanfrage wurde angenommen und veröffentlicht',
                    [$email],
                    [
                        'publication' => $publication,
                    ]
                );
            }
        }
    }

    /**
     * @param  Publication  $publication
     * @return void
     */
    public function sendDeclinedNotification(Publication $publication): void
    {
        if ($publication->getEditor() !== $publication->getReviewer()) {
            $email = $this->userService->getEmailAddressForUser($publication->getEditor());
            if ($email) {
                $this->sendEmail(
                    'EditorRejectionNotification',
                    'Begutachtungsanfrage wurde abgelehnt',
                    [$email],
                    [
                        'publication' => $publication,
                    ]
                );
            }
        }
    }

    /**
     * @param  string  $templateName
     * @param  string  $subject
     * @param  array  $recipient
     * @param  array  $variables
     * @return bool
     */
    protected function sendEmail(
        string $templateName,
        string $subject,
        array $recipient,
        array $variables = []
    ): bool {
        return $this->emailService->sendTemplateEmail($templateName, $subject, $recipient, $variables, $this->notificationSettings['templateMailerSender']);
    }

    /**
     * @param  Publication  $publication
     * @param  string  $action
     * @return string
     */
    protected function getUriToPublicationInBackendModule(Publication $publication, string $action = 'show'): string
    {
        $baseUri = $this->notificationSettings['baseUri'];

        if ($baseUri) {
            $routeParameters = RouteParameters::createEmpty()->withParameter('requestUriHost', $baseUri);

            $fakeHttpRequest = $this->serverRequestFactory
                ->createServerRequest('GET', $baseUri)
                ->withAttribute(ServerRequestAttributes::ROUTING_PARAMETERS, $routeParameters);

            $fakeActionRequest = $this->actionRequestFactory->createActionRequest($fakeHttpRequest);

            $moduleArguments = ['module' => 'management/publications', 'moduleArguments' => ['@action' => $action, 'publication' => $publication]];
            $this->uriBuilder->setRequest($fakeActionRequest);

            return $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->uriFor('index', $moduleArguments, 'Backend\Module', 'Neos.Neos');
        }

        return '';
    }
}

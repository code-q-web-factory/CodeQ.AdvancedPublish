<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Repository\PublicationRepository;
use CodeQ\AdvancedPublish\Utility\RolesUtility;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Exception\WorkspaceException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Policy\Role;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Neos\Service\PublishingService;
use Neos\Neos\Utility\User as UserUtility;

/**
 * @Flow\Scope("singleton")
 */
class UserService
{
    /**
     * @Flow\Inject
     * @var \Neos\Neos\Domain\Service\UserService
     */
    protected $neosUserService;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @Flow\Inject
     * @var PublicationRepository
     */
    protected $publicationRepository;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var PublicationService
     */
    protected $publicationService;

    /**
     * @return Workspace|null
     */
    public function findPublicWorkspaceForCurrentUser(): ?Workspace
    {
        return $this->findPublicWorkspaceForUser($this->getCurrentlyAuthenticatedUser());
    }

    /**
     * @param  User  $user
     * @return Workspace|null
     */
    public function findPublicWorkspaceForUser(User $user): ?Workspace
    {
        $account = $this->findNeosBackendAccount($user);
        $publicWorkspaceIdentifier = 'public-' . UserUtility::slugifyUsername($account->getAccountIdentifier());

        return $this->workspaceRepository->findByIdentifier($publicWorkspaceIdentifier);
    }

    /**
     * @param  User  $user
     * @return Account
     * @throws Exception
     */
    public function findNeosBackendAccount(User $user): Account
    {
        foreach ($user->getAccounts() as $account) {
            if ($account->getAuthenticationProviderName() === 'Neos.Neos:Backend') {
                return $account;
            }
        }

        throw new Exception('User has no Neos.Neos:Backend account!');
    }

    /**
     * @return User|null
     */
    public function getCurrentlyAuthenticatedUser(): ?User
    {
        return $this->neosUserService->getCurrentUser();
    }

    /**
     * @param  User|null  $user
     * @return string|null
     */
    public function getEmailAddressForUser(?User $user): ?string
    {
        if (is_null($user)) {
            return null;
        }

        return $user->getPrimaryElectronicAddress()?->getIdentifier();
    }

    /**
     * @param  User  $user
     * @return void
     */
    public function createWorkspacesForUser(User $user): void
    {
        $liveWorkspace = $this->workspaceRepository->findByIdentifier('live');
        $account = $this->findNeosBackendAccount($user);
        $accountIdentifier = $account->getAccountIdentifier();
        $userWorkspaceName = UserUtility::getPersonalWorkspaceNameForUsername($accountIdentifier);
        $userWorkspace = $this->workspaceRepository->findByIdentifier($userWorkspaceName);

        $publicWorkspaceIdentifier = 'public-' . UserUtility::slugifyUsername($accountIdentifier);
        $publicWorkspace = $this->workspaceRepository->findByIdentifier($publicWorkspaceIdentifier);
        if (is_null($publicWorkspace)) {
            $publicWorkspace = new Workspace($publicWorkspaceIdentifier, $liveWorkspace, $user);
            $this->workspaceRepository->add($publicWorkspace);
        }

        $userWorkspace->setBaseWorkspace($publicWorkspace);
        $this->workspaceRepository->update($userWorkspace);
    }

    /**
     * @param  User  $user
     * @return void
     * @throws IllegalObjectTypeException
     */
    public function onUserDeleted(User $user): void
    {
        $publicWorkspace = $this->findPublicWorkspaceForUser($user);
        $this->workspaceRepository->remove($publicWorkspace);

        $pendingPublicationsForUser = $this->publicationRepository->findPendingByEditor($user);
        /** @var Publication $publication */
        foreach ($pendingPublicationsForUser as $publication) {
            $this->publicationRepository->remove($publication);
        }
    }

    /**
     * @return array<User>
     */
    public function getAuthorizedReviewers(): array
    {
        $currentUser = $this->getCurrentlyAuthenticatedUser();
        $users = $this->userRepository->findAll();
        $authorizedReviewers = [];
        /** @var User $user */
        foreach ($users as $user) {
            try {
                $neosBackendAccount = $this->findNeosBackendAccount($user);
            } catch (\Exception) {
                continue;
            }

            $isAdministrator = RolesUtility::containsRole($neosBackendAccount->getRoles(), 'Neos.Neos:Administrator');
            $canReview = RolesUtility::containsRole($neosBackendAccount->getRoles(), 'CodeQ.AdvancedPublish:AbstractReviewer') || $isAdministrator;
            if (!$canReview) {
                continue;
            }
            $isCurrentUser = $currentUser === $user;
            $canReviewOwnRequests = RolesUtility::containsRole($neosBackendAccount->getRoles(), 'CodeQ.AdvancedPublish:CanReviewOwnRequests') || $isAdministrator;
            if ($isCurrentUser && !$canReviewOwnRequests) {
                continue;
            }

            $authorizedReviewers[] = $user;
        }

        return $authorizedReviewers;
    }

    /**
     * @return void
     */
    public function discardOwnPublicWorkspaceAndWithdrawPendingPublications(): void
    {
        $user = $this->getCurrentlyAuthenticatedUser();

        $publicWorkspace = $this->findPublicWorkspaceForUser($user);
        if ($publicWorkspace) {
            try {
                $this->publishingService->discardAllNodes($publicWorkspace);
            } catch (WorkspaceException $e) {
                // This cannot happen, as the user workspace is never the live workspace
            }
        }

        $pendingPublications = $this->publicationRepository->findPendingByEditor($user);
        foreach ($pendingPublications as $pendingPublication) {
            $this->publicationService->withdraw($pendingPublication);
        }
    }
}

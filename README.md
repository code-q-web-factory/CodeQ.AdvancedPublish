# CodeQ.AdvancedPublish

This package enforces a four-eyes-principle for publishing changes.
For that reasons, every user gets an additional workspace, on which changes staged for publication are parked.
The user then can request a review from an authorized user, who can then publish the changes to the live workspace.
The reviewer can also reject the changes, whereupon the editor can revise the changes and request a new review.

## Roles

### CodeQ.AdvancedPublish:AbstractReviewer

This role can be used to give users the ability to review changes.
Using this role aims at not being fully able to publish changes,
but to be restricted later using custom filters in the `CodeQ.AdvancedPublish.reviewers.filterImplementations` setting.

### CodeQ.AdvancedPublish:CanReview

This role can be used to give users the ability to review changes with the aim to be able
to publish changes across the site without any restriction.

### CodeQ.AdvancedPublish:CanReviewOwnRequests

This role can be used to give users the ability to review their own requests.

### CodeQ.AdvancedPublish:CanViewProtocol

This role can be used to give users the ability to view the publication protocol.

### CodeQ.AdvancedPublish:SuperEditor

This role is allowed to see the original workspace dropdown in the Neos UI and to switch workspaces.
Other users only see a simplified publish button without the option to switch workspaces.

## Reviewer Filters

The package provides a flexible system to control which reviewers are allowed to publish specific content.
This is implemented through reviewer filters that check if a user has the necessary permissions to publish a node.

### How Reviewer Filters Work

1. Each filter implements the `ReviewerFilterInterface` and returns a boolean value.
2. If at least one filter returns `true`, the reviewer is allowed to publish the content.
3. If all filters return `false`, the reviewer is not allowed to publish the content.

### Implementing Custom Reviewer Filters

To create a custom reviewer filter:

1. Create a class that implements `CodeQ\AdvancedPublish\Domain\Service\ReviewerFilterInterface`
2. Implement the `checkFilterConditionsForUserAndNode` method that returns `true` if the user is allowed to publish the node, or `false` otherwise
3. Register your filter in the Settings.yaml configuration:

```yaml
CodeQ:
  AdvancedPublish:
    reviewers:
      filterImplementations:
        YourFilter: 'Your\Namespace\YourFilterClass'
```

### Example Filter Implementation

```php
<?php
namespace Your\Namespace;

use CodeQ\AdvancedPublish\Domain\Service\ReviewerFilterInterface;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\AdvancedPublish\Utility\RolesUtility;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Neos\Domain\Model\User;

class YourFilterClass implements ReviewerFilterInterface
{
    public function checkFilterConditionsForUserAndNode(User $user, NodeInterface $node): bool
    {
        $neosBackendAccount = UserService::findNeosBackendAccount($user);

        // Your custom logic to determine if the user can publish the node
        // Return true if allowed, false otherwise

        return $someCondition && RolesUtility::containsRole($neosBackendAccount->getRoles(), 'Your.Package:YourRole');
    }
}
```

## Suggested project changes

### Deny live publishing and manipulation of workspaces

```yaml
'Neos.Flow:Everybody':
  privileges:
    - privilegeTarget: 'Neos.Neos:Backend.PublishToLiveWorkspace'
      permission: DENY
    - privilegeTarget: 'Neos.Neos:Backend.PublishAllToLiveWorkspace'
      permission: DENY
    - privilegeTarget: 'Neos.Neos:Backend.CreateWorkspaces'
      permission: DENY
    - privilegeTarget: 'Neos.Neos:Backend.Module.Management.Workspaces.ManageOwnWorkspaces'
      permission: DENY
    - privilegeTarget: 'Neos.Neos:Backend.Module.Management.Workspaces.ManageInternalWorkspaces'
      permission: DENY
    - privilegeTarget: 'Neos.Neos:Backend.Module.Management.Workspaces.ManageAllPrivateWorkspaces'
      permission: DENY

```

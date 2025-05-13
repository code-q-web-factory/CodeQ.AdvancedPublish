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

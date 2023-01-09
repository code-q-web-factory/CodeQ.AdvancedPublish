# CodeQ.AdvancedPublish

WIP

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

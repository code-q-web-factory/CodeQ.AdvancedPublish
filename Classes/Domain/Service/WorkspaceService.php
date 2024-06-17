<?php

namespace CodeQ\AdvancedPublish\Domain\Service;

use DateTime;
use DateTimeImmutable;
use Exception;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Diff\Diff;
use Neos\Diff\Renderer\Html\HtmlArrayRenderer;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\SiteService;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Service\PublishingService;

class WorkspaceService
{
    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @param  Workspace  $workspace
     * @return void
     */
    public function publishAllChangesInWorkspaceToWorkspace(Workspace $workspace, Workspace $targetWorkspace): void
    {
        $this->publishingService->publishNodes($this->publishingService->getUnpublishedNodes($workspace), $targetWorkspace);
    }

    /**
     * Computes the number of added, changed and removed nodes for the given workspace
     *
     * @param  Workspace  $selectedWorkspace
     * @return array
     */
    public function computeChangesCount(Workspace $selectedWorkspace)
    {
        $changesCount = ['new' => 0, 'changed' => 0, 'removed' => 0, 'total' => 0];
        foreach ($this->computeSiteChanges($selectedWorkspace) as $siteChanges) {
            foreach ($siteChanges['documents'] as $documentChanges) {
                foreach ($documentChanges['changes'] as $change) {
                    if ($change['node']->isRemoved()) {
                        $changesCount['removed']++;
                    } elseif ($change['isNew']) {
                        $changesCount['new']++;
                    } else {
                        $changesCount['changed']++;
                    }
                    $changesCount['total']++;
                }
            }
        }

        return $changesCount;
    }

    /**
     * Builds an array of changes for sites in the given workspace
     *
     * @param  Workspace  $selectedWorkspace
     * @return array
     */
    public function computeSiteChanges(Workspace $selectedWorkspace)
    {
        $siteChanges = [];
        foreach ($this->publishingService->getUnpublishedNodes($selectedWorkspace) as $node) {
            /** @var NodeInterface $node */
            $skipCollectionChanges = $node->getNodeType()->isOfType('Neos.Neos:ContentCollection') && !$node->getNodeType()->isOfType('Neos.Neos:Content');
            if (!$skipCollectionChanges) {
                $pathParts = explode('/', $node->getPath());
                if (count($pathParts) > 2) {
                    $siteNodeName = $pathParts[2];
                    $q = new FlowQuery([$node]);
                    $document = $q->closest('[instanceof Neos.Neos:Document]')->get(0);

                    // $document will be null if we have a broken root line for this node. This actually should never happen, but currently can in some scenarios.
                    if ($document !== null) {
                        $documentPath = implode('/', array_slice(explode('/', $document->getPath()), 3));
                        $relativePath = str_replace(sprintf(SiteService::SITES_ROOT_PATH . '/%s/%s', $siteNodeName, $documentPath), '', $node->getPath());
                        if (!isset($siteChanges[$siteNodeName]['siteNode'])) {
                            $siteChanges[$siteNodeName]['siteNode'] = $this->siteRepository->findOneByNodeName($siteNodeName);
                        }
                        $siteChanges[$siteNodeName]['documents'][$documentPath]['documentNode'] = $document;

                        $change = [
                            'node' => $node,
                            'contentChanges' => $this->renderContentChanges($node),
                        ];
                        if ($node->getNodeType()->isOfType('Neos.Neos:Node')) {
                            $change['configuration'] = $node->getNodeType()->getFullConfiguration();
                        }
                        $siteChanges[$siteNodeName]['documents'][$documentPath]['changes'][$relativePath] = $change;
                    }
                }
            }
        }

        $liveContext = $this->contextFactory->create([
            'workspaceName' => 'live',
        ]);

        ksort($siteChanges);
        foreach ($siteChanges as $siteKey => $site) {
            foreach ($site['documents'] as $documentKey => $document) {
                $liveDocumentNode = $liveContext->getNodeByIdentifier($document['documentNode']->getIdentifier());
                $siteChanges[$siteKey]['documents'][$documentKey]['isMoved'] = $liveDocumentNode && $document['documentNode']->getPath() !== $liveDocumentNode->getPath();
                $siteChanges[$siteKey]['documents'][$documentKey]['isNew'] = $liveDocumentNode === null;
                foreach ($document['changes'] as $changeKey => $change) {
                    $liveNode = $liveContext->getNodeByIdentifier($change['node']->getIdentifier());
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['isNew'] = is_null($liveNode);
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['isMoved'] = $liveNode && $change['node']->getPath() !== $liveNode->getPath();
                }
            }
            ksort($siteChanges[$siteKey]['documents']);
        }

        return $siteChanges;
    }

    /**
     * Renders the difference between the original and the changed content of the given node and returns it, along
     * with meta information, in an array.
     *
     * @param  NodeInterface  $changedNode
     * @return array
     */
    public function renderContentChanges(NodeInterface $changedNode)
    {
        $contentChanges = [];
        $originalNode = $this->getOriginalNode($changedNode);
        $changeNodePropertiesDefaults = $changedNode->getNodeType()->getDefaultValuesForProperties($changedNode);

        $renderer = new HtmlArrayRenderer();
        foreach ($changedNode->getProperties() as $propertyName => $changedPropertyValue) {
            if ($originalNode === null && empty($changedPropertyValue) || (isset($changeNodePropertiesDefaults[$propertyName]) && $changedPropertyValue === $changeNodePropertiesDefaults[$propertyName])) {
                continue;
            }

            $originalPropertyValue = ($originalNode?->getProperty($propertyName));

            if ($changedPropertyValue === $originalPropertyValue && !$changedNode->isRemoved()) {
                continue;
            }

            if (!is_object($originalPropertyValue) && !is_object($changedPropertyValue)) {
                $originalSlimmedDownContent = $this->renderSlimmedDownContent($originalPropertyValue);
                $changedSlimmedDownContent = $changedNode->isRemoved() ? '' : $this->renderSlimmedDownContent($changedPropertyValue);

                $diff = new Diff(explode("\n", $originalSlimmedDownContent), explode("\n", $changedSlimmedDownContent), ['context' => 1]);
                $diffArray = $diff->render($renderer);
                $this->postProcessDiffArray($diffArray);

                if (count($diffArray) > 0) {
                    $contentChanges[$propertyName] = [
                        'type' => 'text',
                        'propertyLabel' => $this->getPropertyLabel($propertyName, $changedNode),
                        'diff' => $diffArray,
                    ];
                }
                // The && in belows condition is on purpose as creating a thumbnail for comparison only works if actually
                // BOTH are ImageInterface (or NULL).
            } elseif (
                ($originalPropertyValue instanceof ImageInterface || $originalPropertyValue === null)
                && ($changedPropertyValue instanceof ImageInterface || $changedPropertyValue === null)
            ) {
                $contentChanges[$propertyName] = [
                    'type' => 'image',
                    'propertyLabel' => $this->getPropertyLabel($propertyName, $changedNode),
                    'original' => $originalPropertyValue,
                    'changed' => $changedPropertyValue,
                ];
            } elseif ($originalPropertyValue instanceof AssetInterface || $changedPropertyValue instanceof AssetInterface) {
                $contentChanges[$propertyName] = [
                    'type' => 'asset',
                    'propertyLabel' => $this->getPropertyLabel($propertyName, $changedNode),
                    'original' => $originalPropertyValue,
                    'changed' => $changedPropertyValue,
                ];
            } elseif ($originalPropertyValue instanceof DateTime || $changedPropertyValue instanceof DateTime) {
                $changed = false;
                if (!$changedPropertyValue instanceof DateTime || !$originalPropertyValue instanceof DateTime) {
                    $changed = true;
                } elseif ($changedPropertyValue->getTimestamp() !== $originalPropertyValue->getTimestamp()) {
                    $changed = true;
                }
                if ($changed) {
                    $contentChanges[$propertyName] = [
                        'type' => 'datetime',
                        'propertyLabel' => $this->getPropertyLabel($propertyName, $changedNode),
                        'original' => $originalPropertyValue,
                        'changed' => $changedPropertyValue,
                    ];
                }
            }
        }

        return $contentChanges;
    }

    /**
     * Retrieves the given node's corresponding node in the base workspace (that is, which would be overwritten if the
     * given node would be published)
     *
     * @param  NodeInterface  $modifiedNode
     * @return NodeInterface
     */
    public function getOriginalNode(NodeInterface $modifiedNode)
    {
        $baseWorkspaceName = $modifiedNode->getWorkspace()->getBaseWorkspace()->getName();
        $contextProperties = $modifiedNode->getContext()->getProperties();
        $contextProperties['workspaceName'] = $baseWorkspaceName;
        $contentContext = $this->contextFactory->create($contextProperties);

        return $contentContext->getNodeByIdentifier($modifiedNode->getIdentifier());
    }

    /**
     * Renders a slimmed down representation of a property of the given node. The output will be HTML, but does not
     * contain any markup from the original content.
     *
     * Note: It's clear that this method needs to be extracted and moved to a more universal service at some point.
     * However, since we only implemented diff-view support for this particular controller at the moment, it stays
     * here for the time being. Once we start displaying diffs elsewhere, we should refactor the diff rendering part.
     *
     * @param  mixed  $propertyValue
     * @return string
     */
    public function renderSlimmedDownContent($propertyValue)
    {
        $content = '';
        if (is_string($propertyValue)) {
            $contentSnippet = preg_replace('/<br[^>]*>/', "\n", $propertyValue);
            $contentSnippet = preg_replace('/<[^>]*>/', ' ', $contentSnippet);
            $contentSnippet = str_replace('&nbsp;', ' ', $contentSnippet);
            $content = trim(preg_replace('/ {2,}/', ' ', $contentSnippet));
        }

        return $content;
    }

    /**
     * A workaround for some missing functionality in the Diff Renderer:
     *
     * This method will check if content in the given diff array is either completely new or has been completely
     * removed and wraps the respective part in <ins> or <del> tags, because the Diff Renderer currently does not
     * do that in these cases.
     *
     * @param  array  $diffArray
     * @return void
     */
    public function postProcessDiffArray(array &$diffArray)
    {
        foreach ($diffArray as $index => $blocks) {
            foreach ($blocks as $blockIndex => $block) {
                $baseLines = trim(implode('', $block['base']['lines']), " \t\n\r\0\xC2\xA0");
                $changedLines = trim(implode('', $block['changed']['lines']), " \t\n\r\0\xC2\xA0");
                if ($baseLines === '') {
                    foreach ($block['changed']['lines'] as $lineIndex => $line) {
                        $diffArray[$index][$blockIndex]['changed']['lines'][$lineIndex] = '<ins>' . $line . '</ins>';
                    }
                }
                if ($changedLines === '') {
                    foreach ($block['base']['lines'] as $lineIndex => $line) {
                        $diffArray[$index][$blockIndex]['base']['lines'][$lineIndex] = '<del>' . $line . '</del>';
                    }
                }
            }
        }
    }

    /**
     * Tries to determine a label for the specified property
     *
     * @param  string  $propertyName
     * @param  NodeInterface  $changedNode
     * @return string
     */
    public function getPropertyLabel($propertyName, NodeInterface $changedNode)
    {
        $properties = $changedNode->getNodeType()->getProperties();
        if (
            !isset($properties[$propertyName]) ||
            !isset($properties[$propertyName]['ui']['label'])
        ) {
            return $propertyName;
        }

        return $properties[$propertyName]['ui']['label'];
    }

    /**
     * @param  array  $siteChanges
     * @return array
     */
    public function renderStaticSiteChanges(array $siteChanges): array
    {
        /**
         * @var string $siteKey
         * @var array $site
         */
        foreach ($siteChanges as $siteKey => $site) {
            /** @var Site $siteNode */
            $siteNode = $site['siteNode'];
            unset($siteChanges[$siteKey]['siteNode']);
            $siteChanges[$siteKey]['siteNode']['name'] = $siteNode->getName();
            $siteChanges[$siteKey]['siteNode']['nodeName'] = $siteNode->getNodeName();

            $renderer = new HtmlArrayRenderer();
            /**
             * @var string $documentKey
             * @var array $document
             */
            foreach ($site['documents'] as $documentKey => $document) {
                /** @var TraversableNodeInterface $documentNode */
                $documentNode = $siteChanges[$siteKey]['documents'][$documentKey]['documentNode'];
                unset($siteChanges[$siteKey]['documents'][$documentKey]['documentNode']);
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['identifier'] = (string)$documentNode->getNodeAggregateIdentifier();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['path'] = (string)$documentNode->findNodePath();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['nodePath'] = (string)$documentNode->findNodePath();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['creationDateTime'] = $documentNode->getCreationDateTime()->format(DateTime::RFC3339_EXTENDED);
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['lastModificationDateTime'] = $documentNode->getLastModificationDateTime()->format(DateTime::RFC3339_EXTENDED);
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['label'] = $documentNode->getLabel();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['hidden'] = $documentNode->isHidden();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['removed'] = $documentNode->isRemoved();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['nodeType']['name'] = $documentNode->getNodeType()->getName();
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['nodeType']['icon'] = $documentNode->getNodeType()->getConfiguration('ui.icon');
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['breadcrumbs'] = [];

                $currentBreadcrumbNode = $documentNode;
                $i = 0;
                do {
                    if ($currentBreadcrumbNode->getNodeType()->getName() === 'unstructured') {
                        break;
                    }

                    $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['breadcrumbs'][$i]['label'] = $currentBreadcrumbNode->getLabel();
                    $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['breadcrumbs'][$i]['nodeType']['name'] = $currentBreadcrumbNode->getNodeType()->getName();
                    $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['breadcrumbs'][$i]['nodeType']['icon'] = $currentBreadcrumbNode->getNodeType()->getConfiguration('ui.icon');

                    $currentBreadcrumbNode = $currentBreadcrumbNode->findParentNode();
                    $i++;
                } while (true);
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['breadcrumbs'] = array_reverse($siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['breadcrumbs']);

                foreach ($document['changes'] as $changeKey => $change) {
                    /** @var TraversableNodeInterface $changedNode */
                    $changedNode = $change['node'];
                    // Freeze node information
                    unset($siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']);
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['identifier'] = (string)$changedNode->getNodeAggregateIdentifier();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['path'] = (string)$changedNode->findNodePath();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['nodePath'] = (string)$changedNode->findNodePath();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['creationDateTime'] = $changedNode->getCreationDateTime()->format(DateTime::RFC3339_EXTENDED);
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['lastModificationDateTime'] = $changedNode->getLastModificationDateTime()->format(DateTime::RFC3339_EXTENDED);
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['label'] = $changedNode->getLabel();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['hidden'] = $changedNode->isHidden();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['removed'] = $changedNode->isRemoved();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['nodeType']['name'] = $changedNode->getNodeType()->getName();
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['nodeType']['icon'] = $changedNode->getNodeType()->getConfiguration('ui.icon');
                    // Freeze configuration
                    $configuration = $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['configuration'];
                    unset($siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['configuration']);
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['configuration']['ui']['label'] = $configuration['ui']['label'];
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['configuration']['ui']['icon'] = $configuration['ui']['icon'];

                    // Replace asset objects with their public uri
                    // @todo improve asset changes
                    foreach ($change['contentChanges'] as $propertyName => $propertyChanges) {
                        if ($propertyChanges['type'] === 'image' || $propertyChanges['type'] === 'asset') {
                            /** @var AssetInterface $originalAsset */
                            $originalAsset = $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['contentChanges'][$propertyName]['original'];
                            /** @var AssetInterface $changedAsset */
                            $changedAsset = $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['contentChanges'][$propertyName]['changed'];

                            $originalAssetPublicUri = '';
                            if ($originalAsset && $originalAsset->getResource()) {
                                try {
                                    $originalAssetPublicUri = $this->resourceManager->getPublicPersistentResourceUri($originalAsset->getResource());
                                } catch (Exception $e) {
                                }
                            }

                            $changedAssetPublicUri = '';
                            if ($changedAsset && $changedAsset->getResource()) {
                                try {
                                    $changedAssetPublicUri = $this->resourceManager->getPublicPersistentResourceUri($changedAsset->getResource());
                                } catch (Exception $e) {
                                }
                            }

                            $diff = new Diff([$originalAssetPublicUri], [$changedAssetPublicUri], ['context' => 1]);
                            $diffArray = $diff->render($renderer);
                            $this->postProcessDiffArray($diffArray);

                            unset($siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['contentChanges'][$propertyName]);
                            $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['contentChanges'][$propertyName]['type'] = 'text';
                            $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['contentChanges'][$propertyName]['diff'] = $diffArray;
                        }
                    }
                }
            }
        }

        return $siteChanges;
    }

    /**
     * @param  array  $siteChanges
     * @return array
     */
    public function hydrateStaticSiteChanges(array $siteChanges): array
    {
        /**
         * @var string $siteKey
         * @var array $site
         */
        foreach ($siteChanges as $siteKey => $site) {
            /**
             * @var string $documentKey
             * @var array $document
             */
            foreach ($site['documents'] as $documentKey => $document) {
                $siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['lastModificationDateTime'] = new DateTimeImmutable($siteChanges[$siteKey]['documents'][$documentKey]['documentNode']['lastModificationDateTime']);

                foreach ($document['changes'] as $changeKey => $change) {
                    $siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['lastModificationDateTime'] = new DateTimeImmutable($siteChanges[$siteKey]['documents'][$documentKey]['changes'][$changeKey]['node']['lastModificationDateTime']);
                }
            }
        }

        return $siteChanges;
    }

    /**
     * Creates an array of workspace names and their respective titles which are possible base workspaces for other
     * workspaces.
     *
     * @param  Workspace|null  $excludedWorkspace  If set, this workspace will be excluded from the list of returned workspaces
     * @return array
     */
    public function prepareBaseWorkspaceOptions(Workspace $excludedWorkspace = null)
    {
        $baseWorkspaceOptions = [];
        foreach ($this->workspaceRepository->findAll() as $workspace) {
            /** @var Workspace $workspace */
            if (!$workspace->isPersonalWorkspace() && $workspace !== $excludedWorkspace && ($workspace->isPublicWorkspace() || $workspace->isInternalWorkspace() || $this->userService->currentUserCanManageWorkspace($workspace))) {
                $baseWorkspaceOptions[$workspace->getName()] = $workspace->getTitle();
            }
        }

        return $baseWorkspaceOptions;
    }

    /**
     * Creates an array of user names and their respective labels which are possible owners for a workspace.
     *
     * @return array
     */
    public function prepareOwnerOptions()
    {
        $ownerOptions = ['' => '-'];
        foreach ($this->userService->getUsers() as $user) {
            /** @var User $user */
            $ownerOptions[$this->persistenceManager->getIdentifierByObject($user)] = $user->getLabel();
        }

        return $ownerOptions;
    }
}

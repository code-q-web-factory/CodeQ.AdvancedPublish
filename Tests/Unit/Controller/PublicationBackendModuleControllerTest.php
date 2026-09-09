<?php

namespace CodeQ\AdvancedPublish\Tests\Unit\Controller;

use CodeQ\AdvancedPublish\Controller\PublicationBackendModuleController;
use CodeQ\AdvancedPublish\Domain\Factory\PublicationFactory;
use CodeQ\AdvancedPublish\Domain\Model\Publication;
use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\AdvancedPublish\Domain\Service\WorkspaceService;
use DateTimeImmutable;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Neos\Domain\Model\User;
use NEOSidekick\Revisions\Domain\Model\Revision;

class PublicationBackendModuleControllerTest extends UnitTestCase
{
    public function testAppliedRevisionWithoutSnapshotRetainsRevisionDetails(): void
    {
        $user = new User();
        $workspace = $this->createMock(Workspace::class);
        $revision = $this->createMock(Revision::class);
        $revision->method('getNodeIdentifier')->willReturn('synthetic-page');
        $userService = $this->createMock(UserService::class);
        $userService->method('getCurrentlyAuthenticatedUser')->willReturn($user);
        $userService->method('findPublicWorkspaceForCurrentUser')->willReturn($workspace);
        $factory = new PublicationFactory();
        $this->inject($factory, 'userService', $userService);

        $previousAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        try {
            $publication = $factory->fromCurrentUserAndRevision($revision);
        } finally {
            if ($previousAddress === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $previousAddress;
            }
        }

        // Doctrine hydrates the BLOB as a stream when loading the publication.
        $this->hydrateChangesBlob($publication);
        self::assertSame('approved', $publication->getStatus());
        self::assertNull($publication->getChanges());

        $node = $this->createMock(NodeData::class);
        $node->method('getDimensionValues')->willReturn(['language' => ['de']]);
        $node->method('getProperty')->with('title')->willReturn('Example page');
        $repository = $this->createMock(NodeDataRepository::class);
        $repository->expects(self::once())->method('findByNodeIdentifier')
            ->with('synthetic-page')->willReturn([$node]);
        $controller = $this->createController($publication, null, 'Example page');
        $this->inject($controller, 'nodeDataRepository', $repository);
        $controller->showAction($publication);

        self::assertSame($revision, $publication->getRevision());
        self::assertNull($publication->getChanges());
    }

    /**
     * @dataProvider completedStatuses
     */
    public function testCompletedPublicationHydratesSnapshotOnRepeatedReads(string $status): void
    {
        $publication = new Publication(new User());
        $publication->setStatus($status);
        $this->inject($publication, 'protocolSettings', ['enableCompression' => false]);
        $date = '2026-01-01T12:00:00+00:00';
        $changes = ['example' => ['documents' => [[
            'documentNode' => ['lastModificationDateTime' => $date],
            'changes' => [['node' => ['lastModificationDateTime' => $date]]],
        ]]]];
        $publication->setChanges($changes);
        $this->hydrateChangesBlob($publication);
        $expected = $changes;
        $expected['example']['documents'][0]['documentNode']['lastModificationDateTime'] = new DateTimeImmutable($date);
        $expected['example']['documents'][0]['changes'][0]['node']['lastModificationDateTime'] = new DateTimeImmutable($date);

        $controller = $this->createController($publication, $expected, null, 2);
        $controller->showAction($publication);
        $controller->showAction($publication);
        self::assertSame($changes, $publication->getChanges());
    }

    public function completedStatuses(): array
    {
        return [['approved'], ['declined'], ['withdrawn']];
    }

    public function testEmptySnapshotRemainsDistinctFromMissingSnapshot(): void
    {
        $publication = new Publication(new User());
        $publication->setStatus('approved');
        $this->inject($publication, 'protocolSettings', ['enableCompression' => false]);
        $publication->setChanges([]);
        $this->hydrateChangesBlob($publication);
        $this->createController($publication, [])->showAction($publication);
        self::assertSame([], $publication->getChanges());
    }

    public function testMissingSnapshotWithoutRevisionRemainsNull(): void
    {
        $publication = new Publication(new User());
        $publication->setStatus('approved');
        $this->hydrateChangesBlob($publication);
        $this->createController($publication, null)->showAction($publication);
    }

    public function testPendingPublicationComputesCurrentWorkspaceChanges(): void
    {
        $publication = new Publication(new User());
        $workspace = $this->createMock(Workspace::class);
        $publication->setWorkspace($workspace);
        $changes = ['example' => ['documents' => []]];
        $service = $this->createMock(WorkspaceService::class);
        $service->expects(self::once())->method('computeSiteChanges')->with($workspace)->willReturn($changes);
        $service->expects(self::never())->method('hydrateStaticSiteChanges');
        $controller = $this->createController($publication, $changes);
        $this->inject($controller, 'workspaceService', $service);
        $controller->showAction($publication);
    }

    private function hydrateChangesBlob(Publication $publication): void
    {
        $property = new \ReflectionProperty(Publication::class, 'changes');
        $property->setAccessible(true);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $property->getValue($publication));
        rewind($stream);
        $property->setValue($publication, $stream);
        $this->inject($publication, 'protocolSettings', ['enableCompression' => false]);
    }

    private function createController(
        Publication $publication,
        ?array $changes,
        ?string $revisionPageTitle = null,
        int $calls = 1
    ): PublicationBackendModuleController {
        $controller = new PublicationBackendModuleController();
        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::exactly($calls))->method('assignMultiple')->with([
            'publication' => $publication,
            'siteChanges' => $changes,
            'avoidPrinting' => false,
            'avoidCopying' => false,
            'revisionPageTitle' => $revisionPageTitle,
            'inEmbedMode' => false,
        ]);
        $this->inject($controller, 'view', $view);
        $this->inject($controller, 'workspaceService', new WorkspaceService());
        $this->inject($controller, 'settings', ['protocol' => ['avoidPrinting' => false, 'avoidCopying' => false]]);
        return $controller;
    }
}

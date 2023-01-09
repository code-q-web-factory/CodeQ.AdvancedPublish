<?php

namespace CodeQ\AdvancedPublish\Domain\Model;

use CodeQ\AdvancedPublish\Domain\Service\UserService;
use CodeQ\Revisions\Domain\Model\Revision;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\User;

/**
 * @Flow\Entity
 * @ORM\HasLifecycleCallbacks
 */
class Publication implements PublicationInterface
{
    /**
     * @Flow\Transient
     * @var bool
     */
    protected bool $useIgBinary = false;

    /**
     * @var DateTimeImmutable
     */
    protected DateTimeImmutable $created;

    /**
     * @ORM\Column(nullable=TRUE)
     * @var DateTimeImmutable|null
     */
    protected ?DateTimeImmutable $resolved = null;

    /**
     * @var string
     */
    protected string $status = self::STATUS_PENDING;

    /**
     * @ORM\ManyToOne()
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var User
     */
    protected $editor;

    /**
     * @ORM\Column(nullable=TRUE)
     * @var string|null
     */
    protected ?string $editorIpAddress = null;

    /**
     * @ORM\ManyToOne()
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var User
     */
    protected $reviewer;

    /**
     * @ORM\Column(nullable=TRUE)
     * @var string|null
     */
    protected ?string $reviewerIpAddress = null;

    /**
     * @ORM\Column(type="text", nullable=TRUE)
     * @var string|null
     */
    protected ?string $comment = null;

    /**
     * @ORM\Column(type="text", nullable=TRUE)
     * @var string|null
     */
    protected ?string $reasoning = null;

    /**
     * @ORM\ManyToOne
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var Workspace
     */
    protected Workspace $workspace;

    /**
     * @ORM\Column(type="blob")
     * @var string
     */
    protected $changes = '';

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @ORM\JoinColumn(nullable=TRUE)
     * @ORM\OneToOne()
     * @var Revision|null
     */
    protected $revision = null;

    /**
     * @Flow\InjectConfiguration(path="protocol")
     * @var array
     */
    protected $protocolSettings;

    /**
     * @param  User  $editor
     */
    public function __construct(User $editor)
    {
        $this->created = new DateTimeImmutable();
        $this->editor = $editor;
    }

    /**
     * @return void
     */
    public function initializeObject(): void
    {
        $this->useIgBinary = extension_loaded('igbinary');
    }

    /**
     * @return DateTimeImmutable
     */
    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getResolved(): ?DateTimeImmutable
    {
        return $this->resolved;
    }

    /**
     * @param  DateTimeImmutable  $resolved
     */
    public function setResolved(DateTimeImmutable $resolved): void
    {
        $this->resolved = $resolved;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param  string  $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return User|null
     */
    public function getEditor(): ?User
    {
        return $this->editor;
    }

    /**
     * @param  User|null  $editor
     */
    public function setEditor(?User $editor): void
    {
        $this->editor = $editor;
    }

    /**
     * @return string
     */
    public function getEditorIpAddress(): string
    {
        return $this->editorIpAddress;
    }

    /**
     * @param  string  $editorIpAddress
     */
    public function setEditorIpAddress(string $editorIpAddress): void
    {
        $this->editorIpAddress = $editorIpAddress;
    }

    /**
     * @return User|null
     */
    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    /**
     * @param  User|null  $reviewer
     */
    public function setReviewer(?User $reviewer): void
    {
        $this->reviewer = $reviewer;
    }

    /**
     * @return string
     */
    public function getReviewerIpAddress(): string
    {
        return $this->reviewerIpAddress;
    }

    /**
     * @param  string  $reviewerIpAddress
     */
    public function setReviewerIpAddress(string $reviewerIpAddress): void
    {
        $this->reviewerIpAddress = $reviewerIpAddress;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param  string|null  $comment
     */
    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * @return string|null
     */
    public function getReasoning(): ?string
    {
        return $this->reasoning;
    }

    /**
     * @param  string|null  $reasoning
     */
    public function setReasoning(?string $reasoning): void
    {
        $this->reasoning = $reasoning;
    }

    /**
     * @return Workspace
     */
    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    /**
     * @param  Workspace  $workspace
     */
    public function setWorkspace(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    /**
     * @return array
     */
    public function getChanges(): ?array
    {
        $changes = stream_get_contents($this->changes);
        $changes = ($this->isCompressionEnabledAndAvailable() && str_starts_with($changes, 'BZ')) ? $this->decompress($changes) : $changes;
        rewind($this->changes);

        if (!$changes) {
            return null;
        }

        return $this->useIgBinary ? igbinary_unserialize($changes) : unserialize($changes);
    }

    /**
     * @return string
     *
     * @Flow\Internal()
     */
    public function getChangesRaw(): string
    {
        return stream_get_contents($this->changes);
    }

    /**
     * @param  array  $changes
     */
    public function setChanges(array $changes): void
    {
        $changes = $this->useIgBinary ? igbinary_serialize($changes) : serialize($changes);
        if ($this->isCompressionEnabledAndAvailable()) {
            $this->changes = $this->compress($changes);
        } else {
            $this->changes = $changes;
        }
    }

    /**
     * @return Revision|null
     */
    public function getRevision(): ?Revision
    {
        return $this->revision;
    }

    /**
     * @param  Revision|null  $revision
     */
    public function setRevision(?Revision $revision): void
    {
        $this->revision = $revision;
    }

    /**
     * @return bool
     */
    public function getCanBeReviewedByCurrentUser(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->userService->getCurrentlyAuthenticatedUser() === $this->reviewer;
    }

    /**
     * @return bool
     */
    public function getIsWithdrawable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->userService->getCurrentlyAuthenticatedUser() === $this->editor;
    }

    /**
     * @param  string  $content
     * @return string
     */
    protected function compress(string $content): string
    {
        try {
            return bzcompress($content, 9);
        } catch (\Exception $e) {
        }

        return $content;
    }

    /**
     * @param  string  $content
     * @return string
     */
    protected function decompress(string $content): string
    {
        try {
            return bzdecompress($content);
        } catch (\Exception $e) {
        }

        return $content;
    }

    /**
     * @return bool
     */
    protected function isCompressionEnabledAndAvailable(): bool
    {
        return extension_loaded('bz2') && array_key_exists('enableCompression', $this->protocolSettings) && $this->protocolSettings['enableCompression'] === true;
    }
}

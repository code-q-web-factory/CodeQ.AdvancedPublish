<?php

namespace CodeQ\AdvancedPublish\Domain\Model;

interface PublicationInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_WITHDRAWN = 'withdrawn';
}

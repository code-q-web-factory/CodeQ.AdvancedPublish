<?php

namespace CodeQ\AdvancedPublish\Domain\Repository;

use CodeQ\AdvancedPublish\Domain\Model\PublicationInterface;
use DateTimeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Persistence\Repository;
use Neos\Neos\Domain\Model\User;

/**
 * @Flow\Scope("singleton")
 */
class PublicationRepository extends Repository
{
    protected $defaultOrderings = [
        'created' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * @param  User  $editor
     * @return int
     */
    public function countPendingByEditor(User $editor): int
    {
        return $this->findPendingByEditor($editor)->count();
    }

    /**
     * @param  User  $editor
     * @return QueryResultInterface
     */
    public function findPendingByEditor(User $editor): QueryResultInterface
    {
        $query = $this->createQuery();

        return $query->matching(
            $query->logicalAnd(
                $query->equals('editor', $editor),
                $query->equals('status', PublicationInterface::STATUS_PENDING)
            )
        )->execute();
    }

    /**
     * @param  DateTimeInterface  $dateTime
     * @return QueryResultInterface
     */
    public function findByCreationDateOrEarlier(DateTimeInterface $dateTime): QueryResultInterface
    {
        $query = $this->createQuery();

        return $query->matching(
            $query->lessThanOrEqual('created', $dateTime)
        )->execute();
    }
}

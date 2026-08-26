<?php

namespace Tnt\Ecommerce\Repository;

use dry\db\FetchException;
use dry\orm\Model;
use Tnt\Dbi\BaseRepository;
use Tnt\Dbi\Contracts\CriteriaCollectionInterface;
use Tnt\Dbi\CriteriaCollection;
use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\In;

/**
 * Base class for this package's repositories. Defaults the criteria
 * collection so `new CartRepository()` works without the container.
 *
 * @template TModel of Model
 */
abstract class Repository extends BaseRepository
{
    /**
     * @param CriteriaCollectionInterface|null $criteria
     */
    public function __construct(?CriteriaCollectionInterface $criteria = null)
    {
        parent::__construct($criteria ?? new CriteriaCollection());
    }

    /**
     * Filter to a single primary key.
     *
     * @param int $id
     * @return static
     */
    public function byId(int $id): static
    {
        $this->addCriteria(new Equals('id', $id));

        return $this;
    }

    /**
     * Filter to a set of primary keys. An empty set matches nothing, not
     * everything.
     *
     * @param array<int, int> $ids
     * @return static
     */
    public function byIds(array $ids): static
    {
        $this->addCriteria(new In('id', $ids));

        return $this;
    }

    /**
     * The first match, or null when there is none — unlike `first()`, which
     * throws on an empty result.
     *
     * @return TModel|null
     */
    public function firstOrNull()
    {
        try {
            /** @var TModel */
            return $this->first();
        } catch (FetchException) {
            return null;
        }
    }

    /**
     * All matches.
     *
     * @return iterable<int, TModel>
     */
    public function all(): iterable
    {
        /** @var iterable<int, TModel> */
        return $this->get();
    }
}

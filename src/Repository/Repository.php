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
 * Base class for this package's repositories.
 *
 * dry-dbi's {@see BaseRepository} demands a criteria collection up front, which
 * is fine when a project's own service provider registers dry-dbi's
 * `RepositoryProvider` and inconvenient everywhere else. Defaulting the
 * argument means `new CartRepository()` works, oak can still autowire the
 * collection when it is bound, and nothing in this package has to care which of
 * the two is true.
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
     * Filter to a set of primary keys.
     *
     * An empty set matches nothing rather than everything — dry-dbi's `In`
     * criterion degrades to `1 = 0` — which is what a caller filtering by a
     * list it happens to have emptied means.
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
     * The first match, or null when there is none.
     *
     * `first()` treats an empty result as a failure and throws, which is right
     * when the row has to be there and wrong for the many places in a shop
     * where its absence is an ordinary state — a visitor with no cart yet, a
     * product that was never stocked.
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

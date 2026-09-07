<?php

declare(strict_types=1);

namespace App\Domain\Search\Contracts;

/**
 * A model the global palette may search.
 *
 * Scout's `Searchable` is a trait, so there is no type that means "this model
 * has an index". This is that type. It adds no code — `searchableAs()` already
 * comes from the trait — but it makes the set of searchable models an explicit,
 * checkable list rather than something inferred from a `use` statement, and it
 * is what lets SearchQuery demand a model that actually has an index to query.
 */
interface SearchableIndex
{
    /**
     * The index name, prefix included.
     *
     * Declared without a return type on purpose. Scout's trait supplies this
     * method with no type declaration of its own, and an interface that demands
     * `: string` would make every model using the trait a fatal signature
     * mismatch. The docblock carries the type instead.
     *
     * @return string
     */
    public function searchableAs();
}

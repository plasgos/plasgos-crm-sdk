<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * A list response: `data` is always an array, `meta.pagination` locates the page.
 *
 * ```php
 * foreach ($client->contacts->list(['per_page' => 100])->autoPaging() as $contact) { ... }
 * ```
 *
 * Items are typed per operation from OpenAPI, e.g. `Page<ListContactsItem>`.
 *
 * @template-covariant TItem
 * @implements \IteratorAggregate<int, TItem>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /** @var list<TItem> */
    public readonly array $items;

    /** @var array{page: int, per_page: int, total: int, total_pages: int, has_next_page: bool, has_prev_page: bool} */
    public readonly array $pagination;

    /**
     * @param Response<mixed, array<string, mixed>> $response
     * @param \Closure(int): Page<TItem> $fetchPage
     */
    public function __construct(public readonly Response $response, private readonly \Closure $fetchPage)
    {
        // A list response carries a JSON array of the operation's items (Page<ListContactsItem> …).
        /** @var list<TItem> $items */
        $items = is_array($response->data) ? array_values($response->data) : [];
        $this->items = $items;
        $p = is_array($response->meta['pagination'] ?? null) ? $response->meta['pagination'] : [];
        $this->pagination = [
            'page' => self::int($p['page'] ?? null, 1),
            'per_page' => self::int($p['per_page'] ?? null, count($this->items)),
            'total' => self::int($p['total'] ?? null, count($this->items)),
            'total_pages' => self::int($p['total_pages'] ?? null, 1),
            'has_next_page' => (bool) ($p['has_next_page'] ?? false),
            'has_prev_page' => (bool) ($p['has_prev_page'] ?? false),
        ];
    }

    public function hasNextPage(): bool
    {
        return $this->pagination['has_next_page'];
    }

    /** @return Page<TItem>|null */
    public function nextPage(): ?self
    {
        return $this->hasNextPage() ? ($this->fetchPage)($this->pagination['page'] + 1) : null;
    }

    /**
     * Every item of every page, fetching the next page lazily.
     *
     * @return \Generator<int, TItem>
     */
    public function autoPaging(): \Generator
    {
        for ($page = $this; $page !== null; $page = $page->nextPage()) {
            yield from $page->items;
        }
    }

    /** @return \Iterator<int, TItem> */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    private static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}

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
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /** @var list<array<string, mixed>> */
    public readonly array $items;

    /** @var array{page: int, per_page: int, total: int, total_pages: int, has_next_page: bool, has_prev_page: bool} */
    public readonly array $pagination;

    /**
     * @param \Closure(int): Page $fetchPage
     */
    public function __construct(public readonly Response $response, private readonly \Closure $fetchPage)
    {
        $this->items = is_array($response->data) ? array_values($response->data) : [];
        $p = is_array($response->meta['pagination'] ?? null) ? $response->meta['pagination'] : [];
        $this->pagination = [
            'page' => (int) ($p['page'] ?? 1),
            'per_page' => (int) ($p['per_page'] ?? count($this->items)),
            'total' => (int) ($p['total'] ?? count($this->items)),
            'total_pages' => (int) ($p['total_pages'] ?? 1),
            'has_next_page' => (bool) ($p['has_next_page'] ?? false),
            'has_prev_page' => (bool) ($p['has_prev_page'] ?? false),
        ];
    }

    public function hasNextPage(): bool
    {
        return $this->pagination['has_next_page'];
    }

    public function nextPage(): ?self
    {
        return $this->hasNextPage() ? ($this->fetchPage)($this->pagination['page'] + 1) : null;
    }

    /**
     * Every item of every page, fetching the next page lazily.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function autoPaging(): \Generator
    {
        for ($page = $this; $page !== null; $page = $page->nextPage()) {
            yield from $page->items;
        }
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}

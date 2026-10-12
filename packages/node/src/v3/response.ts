/** Rate-limit headers of a v3 response (null when absent). */
export interface RateLimit {
  limit: number | null;
  remaining: number | null;
  reset: number | null;
}

/** Pagination block of a list response (`meta.pagination`). */
export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next_page: boolean;
  has_prev_page: boolean;
}

/** A single v3 result (docs/SDK_SPEC.md §4.1). `data` keeps the wire format (snake_case). */
export class ApiResponse<T = any> {
  constructor(
    readonly httpStatus: number,
    readonly message: string,
    readonly data: T,
    readonly meta: Record<string, any>,
    readonly requestId: string | null,
    readonly rateLimit: RateLimit,
    readonly replayed: boolean,
    readonly headers: Record<string, string>,
  ) {}

  header(name: string): string | null {
    return this.headers[name.toLowerCase()] ?? null;
  }
}

/**
 * A list result (docs/SDK_SPEC.md §4.2).
 *
 * ```ts
 * for await (const contact of (await client.contacts.list({ per_page: 100 })).autoPaging()) { ... }
 * ```
 */
export class Page<T = any> implements Iterable<T> {
  readonly items: T[];
  readonly pagination: Pagination;

  constructor(
    readonly response: ApiResponse<T[]>,
    private readonly fetchPage: (page: number) => Promise<Page<T>>,
  ) {
    this.items = Array.isArray(response.data) ? [...response.data] : [];
    const p = (response.meta?.pagination ?? {}) as Partial<Pagination>;
    this.pagination = {
      page: Number(p.page ?? 1),
      per_page: Number(p.per_page ?? this.items.length),
      total: Number(p.total ?? this.items.length),
      total_pages: Number(p.total_pages ?? 1),
      has_next_page: Boolean(p.has_next_page ?? false),
      has_prev_page: Boolean(p.has_prev_page ?? false),
    };
  }

  hasNextPage(): boolean {
    return this.pagination.has_next_page;
  }

  async nextPage(): Promise<Page<T> | null> {
    return this.hasNextPage() ? this.fetchPage(this.pagination.page + 1) : null;
  }

  /** Every item of every page, fetching the next page lazily. */
  async *autoPaging(): AsyncGenerator<T> {
    for (let page: Page<T> | null = this; page !== null; page = await page.nextPage()) {
      yield* page.items;
    }
  }

  [Symbol.iterator](): Iterator<T> {
    return this.items[Symbol.iterator]();
  }

  get length(): number {
    return this.items.length;
  }
}

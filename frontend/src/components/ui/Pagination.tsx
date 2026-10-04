import { IconChevronLeft, IconChevronRight } from './icons'

function pageList(current: number, last: number): (number | 'ellipsis')[] {
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1)
  }
  const pages: (number | 'ellipsis')[] = [1]
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  if (start > 2) pages.push('ellipsis')
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  if (end < last - 1) pages.push('ellipsis')
  pages.push(last)
  return pages
}

export function Pagination({
  page,
  lastPage,
  total,
  onChange,
}: {
  page: number
  lastPage: number
  total: number
  onChange: (page: number) => void
}) {
  if (lastPage <= 1) return null

  const pages = pageList(page, lastPage)

  return (
    <nav aria-label="Paginasi" className="mt-4 flex flex-wrap items-center justify-between gap-3">
      <p className="text-sm text-slate-500">
        Total {total.toLocaleString('id-ID')} data
      </p>
      <div className="flex items-center gap-1">
        <button
          type="button"
          aria-label="Halaman sebelumnya"
          disabled={page <= 1}
          onClick={() => onChange(page - 1)}
          className="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        >
          <IconChevronLeft className="h-4 w-4" />
        </button>
        {pages.map((p, idx) =>
          p === 'ellipsis' ? (
            <span key={`e-${idx}`} className="px-1 text-slate-400">
              …
            </span>
          ) : (
            <button
              key={p}
              type="button"
              onClick={() => onChange(p)}
              aria-current={p === page ? 'page' : undefined}
              className={`h-8 min-w-8 rounded-lg px-2 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 ${
                p === page
                  ? 'bg-primary-600 text-white'
                  : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
              }`}
            >
              {p}
            </button>
          ),
        )}
        <button
          type="button"
          aria-label="Halaman berikutnya"
          disabled={page >= lastPage}
          onClick={() => onChange(page + 1)}
          className="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        >
          <IconChevronRight className="h-4 w-4" />
        </button>
      </div>
    </nav>
  )
}

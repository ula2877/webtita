import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getTasks } from '../../services/visitService'
import { getMyWilayah } from '../../services/wilayahService'
import { getErrorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { SearchInput } from '../../components/ui/SearchInput'
import { Select, Combobox } from '../../components/ui/form'
import { Pagination } from '../../components/ui/Pagination'
import { ErrorState, LoadingState, EmptyState } from '../../components/ui/feedback'
import { HasilBadge, StatusBadge } from '../../components/ui/Badge'
import { IconChevronRight, IconRefresh } from '../../components/ui/icons'
import { Button } from '../../components/ui/Button'
import type { Arrear, Paginated, Wilayah } from '../../types'

const STATUS_FILTERS = [
  { value: '', label: 'Semua' },
  { value: 'belum_dikunjungi', label: 'Belum Dikunjungi' },
  { value: 'sudah_dikunjungi', label: 'Sudah Dikunjungi' },
]

export function TasksPage() {
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('')
  const [wilayahId, setWilayahId] = useState<number | ''>('')
  const [page, setPage] = useState(1)
  const [data, setData] = useState<Paginated<Arrear> | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [wilayahList, setWilayahList] = useState<Wilayah[]>([])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setData(
        await getTasks({
          page,
          per_page: 25,
          search: search || undefined,
          status: filter || undefined,
          wilayah_id: wilayahId || undefined,
        }),
      )
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [page, search, filter, wilayahId])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    getMyWilayah()
      .then(setWilayahList)
      .catch(() => setWilayahList([]))
  }, [])

  function changeSearch(value: string) {
    setSearch(value)
    setPage(1)
  }

  function changeFilter(value: string) {
    setFilter(value)
    setPage(1)
  }

  function changeWilayah(value: string | number | '') {
    setWilayahId((value === '' || typeof value === 'number') ? value : '')
    setPage(1)
  }

  function resetFilters() {
    setSearch('')
    setFilter('')
    setWilayahId('')
    setPage(1)
  }

  const hasActiveFilters = search || filter || wilayahId

  const wilayahOptions = wilayahList.map((w) => ({
    value: w.id,
    label: `${w.name} (${w.code})`,
  }))

  return (
    <div>
      <PageHeader
        title="Tugas Penagihan"
        description="Daftar tugas yang ditugaskan kepada Anda"
      />

      <Card className="mb-4 p-4">
        <div className="space-y-4">
          <div>
            <SearchInput
              name="search"
              label="Cari"
              value={search}
              onChange={changeSearch}
              placeholder="Cari nama / no sambungan..."
            />
          </div>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <Combobox
                name="wilayah_id"
                label="Wilayah"
                placeholder="Semua Wilayah"
                value={wilayahId}
                onChange={changeWilayah}
                options={wilayahOptions}
                searchPlaceholder="Cari wilayah..."
                clearable
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">
                Status Kunjungan
              </label>
              <Select
                name="status"
                value={filter}
                onChange={(e) => changeFilter(e.target.value)}
              >
                {STATUS_FILTERS.map((f) => (
                  <option key={f.value} value={f.value}>
                    {f.label}
                  </option>
                ))}
              </Select>
            </div>

            <div className="flex items-end">
              <Button
                type="button"
                variant="secondary"
                size="md"
                onClick={resetFilters}
                disabled={!hasActiveFilters}
              >
                <IconRefresh className="h-4 w-4" />
                Reset Filter
              </Button>
            </div>
          </div>
        </div>
      </Card>

      {error && !loading && <ErrorState message={error} onRetry={load} />}
      {loading && <Card className="p-4"><LoadingState /></Card>}

      {data && !loading && (
        <>
          {data.data.length === 0 ? (
            <Card>
              <EmptyState
                title={hasActiveFilters ? 'Tidak ada tugas yang sesuai' : 'Tidak ada tugas penagihan'}
                description={hasActiveFilters
                  ? 'Coba ubah filter pencarian Anda.'
                  : 'Semua tugas pada periode ini telah selesai, atau belum ada tugas untuk Anda.'}
              />
            </Card>
          ) : (
            <>
              <ul className="space-y-3">
                {data.data.map((item) => (
                  <li key={item.id}>
                    <Card className="p-4">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="font-medium text-slate-900">{item.nama}</p>
                          <p className="mt-0.5 text-sm text-slate-500">
                            No. Sambungan: {item.no_sambungan}
                          </p>
                        </div>
                        {item.visit ? (
                          <HasilBadge hasil={item.visit.status_kunjungan} />
                        ) : (
                          <StatusBadge status={item.status} />
                        )}
                      </div>
                      <div className="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                        <div>
                          <p className="text-xs text-slate-400">Tunggakan</p>
                          <p className="text-slate-700">{item.jumlah_bulan_tunggakan} bln</p>
                        </div>
                        <div>
                          <p className="text-xs text-slate-400">Periode</p>
                          <p className="text-slate-700">{item.period?.label ?? '-'}</p>
                        </div>
                        <div>
                          <p className="text-xs text-slate-400">Kunjungan</p>
                          <p className="text-slate-700">{formatDate(item.visit?.visited_at)}</p>
                        </div>
                      </div>
                      <Link
                        to={`/petugas/tasks/${item.id}/visit`}
                        className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:w-auto"
                      >
                        {item.visit ? 'Lihat Hasil' : 'Buka Tugas'}
                        <IconChevronRight className="h-4 w-4" />
                      </Link>
                    </Card>
                  </li>
                ))}
              </ul>
              <Pagination
                page={data.meta.current_page}
                lastPage={data.meta.last_page}
                total={data.meta.total}
                onChange={setPage}
              />
            </>
          )}
        </>
      )}
    </div>
  )
}

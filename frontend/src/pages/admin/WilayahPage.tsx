import { useCallback, useEffect, useState } from 'react'
import { getWilayah } from '../../services/wilayahService'
import { getPetugas } from '../../services/petugasService'
import { getErrorMessage } from '../../lib/api'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { Select } from '../../components/ui/form'
import { SearchInput } from '../../components/ui/SearchInput'
import { Pagination } from '../../components/ui/Pagination'
import { ErrorState, LoadingState, EmptyState } from '../../components/ui/feedback'
import type { Paginated, Petugas, Wilayah } from '../../types'

export function WilayahPage() {
  const [search, setSearch] = useState('')
  const [petugasFilterId, setPetugasFilterId] = useState<number | undefined>(undefined)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)

  const [data, setData] = useState<Paginated<Wilayah> | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [petugasList, setPetugasList] = useState<Petugas[]>([])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const result = await getWilayah({
        page,
        per_page: perPage,
        search: search || undefined,
        petugas_id: petugasFilterId,
      })
      setData(result)
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, search, petugasFilterId])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    getPetugas()
      .then(setPetugasList)
      .catch(() => {
        // filter remains usable without petugas data
      })
  }, [])

  function resetPage() {
    setPage(1)
  }

  const activeFilters = search || petugasFilterId

  return (
    <div>
      <PageHeader
        title="Data Wilayah"
        description="Daftar wilayah penagihan beserta petugas yang bertugas"
      />

      <Card className="mb-4 p-4">
        <div className="grid gap-3 lg:grid-cols-12">
          <div className="lg:col-span-5">
            <SearchInput
              name="search"
              label="Cari"
              value={search}
              onChange={(value) => {
                setSearch(value)
                resetPage()
              }}
              placeholder="Cari kode / nama wilayah..."
            />
          </div>
          <div className="lg:col-span-4">
            <Select
              name="petugas_id"
              label="Petugas"
              value={petugasFilterId ?? ''}
              onChange={(e) => {
                setPetugasFilterId(e.target.value ? Number(e.target.value) : undefined)
                resetPage()
              }}
            >
              <option value="">Semua</option>
              {petugasList.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex items-end lg:col-span-1">
            <button
              type="button"
              onClick={() => {
                setSearch('')
                setPetugasFilterId(undefined)
                resetPage()
              }}
              className="text-sm font-medium text-primary-600 hover:text-primary-700"
            >
              Reset
            </button>
          </div>
        </div>
      </Card>

      {error && <ErrorState message={error} onRetry={load} />}

      {loading && (
        <Card className="p-4">
          <LoadingState />
        </Card>
      )}

      {!loading && data && (
        <>
          {data.data.length === 0 ? (
            <Card>
              <EmptyState
                title={activeFilters ? 'Tidak ada hasil pencarian' : 'Belum ada data wilayah'}
                description={
                  activeFilters
                    ? 'Coba ubah kata kunci atau filter yang digunakan.'
                    : 'Import data Excel terlebih dahulu pada menu Import Data.'
                }
              />
            </Card>
          ) : (
            <Card>
              {/* Desktop table */}
              <div className="hidden overflow-x-auto lg:block">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-xs text-slate-500">
                      <th className="px-4 py-3 font-medium">No</th>
                      <th className="px-4 py-3 font-medium">Kode Wilayah</th>
                      <th className="px-4 py-3 font-medium">Nama Wilayah</th>
                      <th className="px-4 py-3 font-medium">Petugas</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {data.data.map((item, index) => (
                      <tr key={item.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 text-slate-500">
                          {(data.meta.current_page - 1) * data.meta.per_page + index + 1}
                        </td>
                        <td className="px-4 py-3 font-mono text-xs font-medium text-slate-900">
                          {item.code}
                        </td>
                        <td className="px-4 py-3 font-medium text-slate-900">{item.name}</td>
                        <td className="px-4 py-3 text-slate-600">
                          {item.petugas?.name ?? '-'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Mobile cards */}
              <ul className="divide-y divide-slate-100 lg:hidden">
                {data.data.map((item) => (
                  <li key={item.id} className="p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-medium text-slate-900">{item.name}</p>
                        <p className="mt-0.5 font-mono text-xs text-slate-500">
                          Kode: {item.code}
                        </p>
                      </div>
                    </div>
                    <div className="mt-2 text-sm">
                      <p className="text-xs text-slate-400">Petugas</p>
                      <p className="text-slate-700">{item.petugas?.name ?? '-'}</p>
                    </div>
                  </li>
                ))}
              </ul>

              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3">
                <Pagination
                  page={data.meta.current_page}
                  lastPage={data.meta.last_page}
                  total={data.meta.total}
                  onChange={setPage}
                />
                <Select
                  name="per_page"
                  aria-label="Data per halaman"
                  value={perPage}
                  onChange={(e) => {
                    setPerPage(Number(e.target.value))
                    resetPage()
                  }}
                  className="w-28"
                >
                  <option value={25}>25 / hal</option>
                  <option value={50}>50 / hal</option>
                  <option value={100}>100 / hal</option>
                </Select>
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  )
}
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getImports } from '../../services/importService'
import { getErrorMessage } from '../../lib/api'
import { formatDateTime, formatNumber } from '../../lib/format'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { ErrorState, LoadingState, EmptyState } from '../../components/ui/feedback'
import { ImportStatusBadge } from '../../components/ui/Badge'
import { IconEye } from '../../components/ui/icons'
import { Pagination } from '../../components/ui/Pagination'
import type { ImportLog, Paginated } from '../../types'

export function ImportHistoryPage() {
  const [data, setData] = useState<Paginated<ImportLog> | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setData(await getImports({ page, per_page: 25 }))
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [page])

  useEffect(() => {
    load()
  }, [load])

  return (
    <div>
      <PageHeader
        title="Histori Import"
        description="Riwayat import data tunggakan"
      />

      {error && !loading && <ErrorState message={error} onRetry={load} />}
      {loading && <Card className="p-4"><LoadingState /></Card>}

      {data && !loading && (
        <Card>
          {data.data.length === 0 ? (
            <EmptyState
              title="Belum ada histori import"
              description="Import data tunggakan terlebih dahulu untuk melihat riwayat."
            />
          ) : (
            <>
              <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-xs text-slate-500">
                      <th className="px-4 py-3 font-medium">Tanggal</th>
                      <th className="px-4 py-3 font-medium">Periode</th>
                      <th className="px-4 py-3 font-medium">Nama File</th>
                      <th className="px-4 py-3 font-medium">Total</th>
                      <th className="px-4 py-3 font-medium">Berhasil</th>
                      <th className="px-4 py-3 font-medium">Gagal</th>
                      <th className="px-4 py-3 font-medium">Status</th>
                      <th className="px-4 py-3 font-medium">Diupload Oleh</th>
                      <th className="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {data.data.map((item) => (
                      <tr key={item.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 text-slate-600">{formatDateTime(item.created_at)}</td>
                        <td className="px-4 py-3 text-slate-700">{item.period?.label ?? '-'}</td>
                        <td className="max-w-52 truncate px-4 py-3 text-slate-700">{item.file_name}</td>
                        <td className="px-4 py-3 text-slate-700">{formatNumber(item.total_rows)}</td>
                        <td className="px-4 py-3 text-emerald-700">{formatNumber(item.success_rows)}</td>
                        <td className="px-4 py-3 text-red-700">{formatNumber(item.failed_rows)}</td>
                        <td className="px-4 py-3">
                          <ImportStatusBadge status={item.status} />
                        </td>
                        <td className="px-4 py-3 text-slate-600">{item.uploaded_by ?? '-'}</td>
                        <td className="px-4 py-3">
                          <Link
                            to={`/admin/imports/${item.id}`}
                            className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50"
                          >
                            <IconEye className="h-4 w-4" />
                            Detail
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <ul className="divide-y divide-slate-100 md:hidden">
                {data.data.map((item) => (
                  <li key={item.id} className="p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-medium text-slate-900">{item.file_name}</p>
                        <p className="mt-0.5 text-sm text-slate-500">{item.period?.label ?? '-'}</p>
                      </div>
                      <ImportStatusBadge status={item.status} />
                    </div>
                    <div className="mt-3 grid grid-cols-3 gap-2 text-sm">
                      <div>
                        <p className="text-xs text-slate-400">Total</p>
                        <p className="text-slate-700">{formatNumber(item.total_rows)}</p>
                      </div>
                      <div>
                        <p className="text-xs text-slate-400">Berhasil</p>
                        <p className="text-emerald-700">{formatNumber(item.success_rows)}</p>
                      </div>
                      <div>
                        <p className="text-xs text-slate-400">Gagal</p>
                        <p className="text-red-700">{formatNumber(item.failed_rows)}</p>
                      </div>
                    </div>
                    <p className="mt-2 text-xs text-slate-500">{formatDateTime(item.created_at)}</p>
                    <Link
                      to={`/admin/imports/${item.id}`}
                      className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600"
                    >
                      <IconEye className="h-4 w-4" />
                      Lihat Detail
                    </Link>
                  </li>
                ))}
              </ul>

              <div className="border-t border-slate-100 px-4 py-3">
                <Pagination
                  page={data.meta.current_page}
                  lastPage={data.meta.last_page}
                  total={data.meta.total}
                  onChange={setPage}
                />
              </div>
            </>
          )}
        </Card>
      )}
    </div>
  )
}

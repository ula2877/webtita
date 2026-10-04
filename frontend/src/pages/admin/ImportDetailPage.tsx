import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { getImport } from '../../services/importService'
import { getErrorMessage } from '../../lib/api'
import { formatDateTime, formatNumber } from '../../lib/format'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { ErrorState, LoadingState, EmptyState } from '../../components/ui/feedback'
import { ImportStatusBadge } from '../../components/ui/Badge'
import { IconChevronLeft } from '../../components/ui/icons'
import type { ImportLog } from '../../types'

export function ImportDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [data, setData] = useState<ImportLog | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError('')
    try {
      setData(await getImport(Number(id)))
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    load()
  }, [load])

  const errors = data?.error_details ?? []

  return (
    <div>
      <PageHeader
        title="Detail Import"
        description={data ? `Import ${data.file_name}` : 'Detail riwayat import'}
        action={
          <Link
            to="/admin/imports"
            className="inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
          >
            <IconChevronLeft className="h-4 w-4" />
            Kembali
          </Link>
        }
      />

      {loading && <LoadingState />}
      {error && !loading && <ErrorState message={error} onRetry={load} />}

      {data && !loading && (
        <div className="space-y-6">
          <Card className="p-5">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-sm font-semibold text-slate-900">Ringkasan</h3>
              <ImportStatusBadge status={data.status} />
            </div>
            <dl className="grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-sm text-slate-500">Tanggal Import</dt>
                <dd className="text-sm font-medium text-slate-900">
                  {formatDateTime(data.created_at)}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Periode</dt>
                <dd className="text-sm font-medium text-slate-900">{data.period?.label ?? '-'}</dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Nama File</dt>
                <dd className="text-sm font-medium text-slate-900">{data.file_name}</dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Diupload Oleh</dt>
                <dd className="text-sm font-medium text-slate-900">{data.uploaded_by ?? '-'}</dd>
              </div>
            </dl>
            <div className="mt-4 grid grid-cols-3 gap-4">
              <div className="rounded-lg bg-emerald-50 p-4 text-center">
                <p className="text-2xl font-semibold text-emerald-700">
                  {formatNumber(data.success_rows)}
                </p>
                <p className="mt-1 text-xs text-emerald-600">Berhasil</p>
              </div>
              <div className="rounded-lg bg-red-50 p-4 text-center">
                <p className="text-2xl font-semibold text-red-700">
                  {formatNumber(data.failed_rows)}
                </p>
                <p className="mt-1 text-xs text-red-600">Gagal</p>
              </div>
              <div className="rounded-lg bg-slate-100 p-4 text-center">
                <p className="text-2xl font-semibold text-slate-700">
                  {formatNumber(data.total_rows)}
                </p>
                <p className="mt-1 text-xs text-slate-600">Total</p>
              </div>
            </div>
          </Card>

          <Card>
            <div className="border-b border-slate-100 px-5 py-4">
              <h3 className="text-sm font-semibold text-slate-900">Detail Error</h3>
            </div>
            {errors.length === 0 ? (
              <EmptyState
                title="Tidak ada error"
                description="Seluruh baris pada file ini berhasil diimport."
              />
            ) : (
              <ul className="divide-y divide-slate-100">
                {errors.map((item, index) => (
                  <li
                    key={index}
                    className="flex items-start gap-3 px-5 py-3 text-sm"
                  >
                    <span
                      className={`mt-1 h-2 w-2 shrink-0 rounded-full ${
                        item.type === 'error' ? 'bg-red-500' : 'bg-amber-500'
                      }`}
                    />
                    <div>
                      <p className="text-slate-700">
                        {item.row !== null && (
                          <span className="font-medium text-slate-900">Baris {item.row}: </span>
                        )}
                        {item.message}
                      </p>
                      <p className="text-xs text-slate-400">
                        {item.type === 'error' ? 'Error' : 'Peringatan'}
                      </p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      )}
    </div>
  )
}

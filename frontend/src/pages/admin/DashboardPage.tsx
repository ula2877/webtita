import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getAdminDashboard, getPeriods } from '../../services/dashboardService'
import { getErrorMessage } from '../../lib/api'
import { formatNumber } from '../../lib/format'
import { Card, CardHeader, PageHeader, ProgressBar, StatCard } from '../../components/ui/surfaces'
import { Select } from '../../components/ui/form'
import { ErrorState, LoadingState } from '../../components/ui/feedback'
import { IconCheck, IconList, IconUsers, IconAlert, IconMoreVertical } from '../../components/ui/icons'
import type { AdminDashboard as AdminDashboardData, Period } from '../../types'

export function AdminDashboardPage() {
  const [periods, setPeriods] = useState<Period[]>([])
  const [periodId, setPeriodId] = useState<number | undefined>(undefined)
  const [data, setData] = useState<AdminDashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const dashboard = await getAdminDashboard(periodId)
      setData(dashboard)
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [periodId])

  useEffect(() => {
    getPeriods()
      .then((list) => {
        setPeriods(list)
        // Default = periode terbaru yang tersedia.
        if (list.length > 0 && periodId === undefined) {
          setPeriodId(list[0].id)
        }
      })
      .catch(() => {
        // period selector is optional; dashboard still loads with default period
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const resultItems = data
    ? [
        { label: 'Ada Orang', value: data.results.ada_orang, accent: 'green' as const },
        { label: 'Rumah Kosong', value: data.results.rumah_kosong, accent: 'amber' as const },
        { label: 'Tidak Ada Orang', value: data.results.tidak_ada_orang, accent: 'gray' as const },
        { label: 'Lainnya', value: data.results.lainnya, accent: 'blue' as const },
      ]
    : []

  return (
    <div>
      <PageHeader
        title="Dashboard Admin"
        description={
          data?.period ? `Ringkasan penagihan periode ${data.period.label}` : 'Ringkasan penagihan'
        }
        action={
          <Select
            name="period_id"
            aria-label="Pilih periode"
            value={periodId ?? ''}
            onChange={(e) => setPeriodId(e.target.value ? Number(e.target.value) : undefined)}
            className="w-48"
          >
            {periods.map((p) => (
              <option key={p.id} value={p.id}>
                {p.label}
              </option>
            ))}
          </Select>
        }
      />

      {error && !loading && <ErrorState message={error} onRetry={load} />}
      {loading && <LoadingState />}

      {data && !loading && (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard
              label="Total Tunggakan"
              value={formatNumber(data.total)}
              icon={<IconList className="h-5 w-5" />}
            />
            <StatCard
              label="Sudah Dikunjungi"
              value={formatNumber(data.visited)}
              accent="green"
              icon={<IconCheck className="h-5 w-5" />}
            />
            <StatCard
              label="Belum Dikunjungi"
              value={formatNumber(data.unvisited)}
              accent="amber"
              icon={<IconAlert className="h-5 w-5" />}
            />
            <StatCard label="Progress" value={`${data.progress}%`} accent="blue" />
          </div>

          <Card>
            <CardHeader title="Hasil Kunjungan" subtitle="Statistik hasil kunjungan petugas" />
            <div className="grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
              {resultItems.map((item) => (
                <div key={item.label} className="rounded-lg border border-slate-100 p-4">
                  <p className="text-sm text-slate-500">{item.label}</p>
                  <p className="mt-1 text-2xl font-semibold text-slate-900">
                    {formatNumber(item.value)}
                  </p>
                </div>
              ))}
            </div>
          </Card>

          <Card>
            <CardHeader
              title="Progress Petugas"
              subtitle="Perkembangan kunjungan setiap petugas"
              action={<IconUsers className="h-5 w-5 text-slate-400" />}
            />
            <div className="space-y-5 p-5">
              {data.petugas_progress.length === 0 ? (
                <p className="text-sm text-slate-500">
                  Belum ada data progress petugas pada periode ini.
                </p>
              ) : (
                data.petugas_progress.map((item) => (
                  <div key={item.petugas.id}>
                    <div className="mb-1.5 flex items-center justify-between gap-3">
                      <p className="text-sm font-medium text-slate-700">{item.petugas.name}</p>
                      <p className="text-sm text-slate-500">
                        {formatNumber(item.visited)} / {formatNumber(item.total)} · {item.progress}%
                      </p>
                      <Link
                        to={`/admin/petugas/${item.petugas.id}/review`}
                        className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                        title="Review pekerjaan"
                        aria-label={`Review pekerjaan ${item.petugas.name}`}
                      >
                        <IconMoreVertical className="h-5 w-5" />
                      </Link>
                    </div>
                    <ProgressBar value={item.progress} />
                  </div>
                ))
              )}
            </div>
          </Card>
        </div>
      )}
    </div>
  )
}

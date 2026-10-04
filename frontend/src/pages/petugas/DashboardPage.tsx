import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getPetugasDashboard, getPeriods } from '../../services/dashboardService'
import { getMyWilayah } from '../../services/wilayahService'
import { getErrorMessage } from '../../lib/api'
import { formatNumber } from '../../lib/format'
import { useAuth } from '../../context/AuthContext'
import { Card, PageHeader, StatCard } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { Select } from '../../components/ui/form'
import { ErrorState, LoadingState } from '../../components/ui/feedback'
import { HasilBadge, StatusBadge } from '../../components/ui/Badge'
import { IconCheck, IconList, IconAlert, IconChevronRight, IconUsers } from '../../components/ui/icons'
import type { Period, PetugasDashboard, Wilayah } from '../../types'

export function PetugasDashboardPage() {
  const { user } = useAuth()
  const [data, setData] = useState<PetugasDashboard | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [wilayahList, setWilayahList] = useState<Wilayah[] | null>(null)
  const [periods, setPeriods] = useState<Period[]>([])
  const [periodId, setPeriodId] = useState<number | undefined>(undefined)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setData(await getPetugasDashboard(periodId))
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [periodId])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    getPeriods()
      .then((list) => {
        setPeriods(list)
        // Default = periode terbaru yang tersedia di database.
        if (list.length > 0 && periodId === undefined) {
          setPeriodId(list[0].id)
        }
      })
      .catch(() => {
        // dashboard tetap load dengan periode default
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    getMyWilayah()
      .then(setWilayahList)
      .catch(() => {
        setWilayahList([])
      })
  }, [])

  const wilayahStatsById = new Map(
    (data?.wilayah_stats ?? []).map((w) => [w.id, w] as const),
  )

  return (
    <div>
      <PageHeader
        title={`Halo, ${user?.name ?? 'Petugas'}`}
        description={data?.period ? `Periode ${data.period.label}` : undefined}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              name="period_id"
              aria-label="Pilih periode"
              value={periodId ?? ''}
              onChange={(e) => setPeriodId(e.target.value ? Number(e.target.value) : undefined)}
              className="w-44"
            >
              {periods.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.label}
                </option>
              ))}
            </Select>
            <Link to="/petugas/tasks">
              <Button size="lg">
                <IconList className="h-4 w-4" />
                Lihat Tugas Penagihan
              </Button>
            </Link>
          </div>
        }
      />

      {error && !loading && <ErrorState message={error} onRetry={load} />}
      {loading && <LoadingState />}

      {data && !loading && (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <StatCard
              label="Total Tugas"
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
            <StatCard
              label="Total Pelanggan Menunggak"
              value={formatNumber(data.total_pelanggan ?? 0)}
              icon={<IconUsers className="h-5 w-5" />}
            />
            <StatCard
              label="Total Tagihan"
              value={`Rp ${formatNumber(data.total_nominal_tagihan ?? 0)}`}
              accent="green"
            />
          </div>

          <Card>
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
              <h3 className="text-sm font-semibold text-slate-900">Tugas Terbaru</h3>
              <Link
                to="/petugas/tasks"
                className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700"
              >
                Lihat semua
                <IconChevronRight className="h-4 w-4" />
              </Link>
            </div>
            {data.recent_arrears.length === 0 ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">
                Belum ada tugas penagihan untuk Anda.
              </p>
            ) : (
              <ul className="divide-y divide-slate-100">
                {data.recent_arrears.map((item) => (
                  <li key={item.id} className="flex items-center justify-between gap-3 px-5 py-3">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-slate-900">{item.nama}</p>
                      <p className="truncate text-sm text-slate-500">
                        No. Sambungan: {item.no_sambungan} · {item.jumlah_bulan_tunggakan} bln
                      </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                      {item.visit ? (
                        <HasilBadge hasil={item.visit.status_kunjungan} />
                      ) : (
                        <StatusBadge status={item.status} />
                      )}
                      <Link
                        to={`/petugas/tasks/${item.id}`}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        aria-label={`Buka tugas ${item.nama ?? ''}`}
                      >
                        <IconChevronRight className="h-4 w-4" />
                      </Link>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
              <h3 className="text-sm font-semibold text-slate-900">
                <span className="inline-flex items-center gap-2">
                  <IconList className="h-4 w-4 text-primary-600" />
                  Wilayah Saya
                </span>
              </h3>
              {wilayahList && wilayahList.length > 0 && (
                <span className="rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700">
                  {formatNumber(wilayahList.length)} wilayah
                </span>
              )}
            </div>
            {wilayahList === null ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">
                Memuat wilayah...
              </p>
            ) : wilayahList.length === 0 ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">
                Belum ada wilayah yang ditugaskan kepada Anda.
              </p>
            ) : (
              <ul className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                {wilayahList.map((item) => {
                  const stats = wilayahStatsById.get(item.id)
                  return (
                    <li
                      key={item.id}
                      className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3"
                    >
                      <p className="font-medium text-slate-900">{item.name}</p>
                      <p className="mt-0.5 font-mono text-xs text-slate-500">Kode: {item.code}</p>
                      {stats && (
                        <div className="mt-2 flex items-center justify-between text-xs">
                          <span className="text-slate-500">
                            {formatNumber(stats.customer_count)} pelanggan
                          </span>
                          <span className="font-medium text-slate-700">
                            Rp {formatNumber(stats.total_tagihan)}
                          </span>
                        </div>
                      )}
                    </li>
                  )
                })}
              </ul>
            )}
          </Card>
        </div>
      )}
    </div>
  )
}

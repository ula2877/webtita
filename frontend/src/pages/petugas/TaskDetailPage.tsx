import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { getTask } from '../../services/visitService'
import { getErrorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { HASIL_KUNJUNGAN_LABELS } from '../../lib/labels'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { ErrorState, LoadingState } from '../../components/ui/feedback'
import { HasilBadge, StatusBadge } from '../../components/ui/Badge'
function PhotoDisplay({ src, alt }: { src: string; alt: string }) {
  const [error, setError] = useState(false)

  if (error) {
    return (
      <div className="flex items-center justify-center rounded-lg bg-slate-100 text-sm text-slate-400 h-48 max-w-xs">
        Foto gagal dimuat
      </div>
    )
  }

  return (
    <div className="max-w-xs mx-auto">
      <img
        src={src}
        alt={alt}
        onError={() => setError(true)}
        className="h-48 w-full object-cover rounded-lg border border-slate-200"
      />
      <button
        type="button"
        onClick={() => window.open(src, '_blank')}
        className="mt-2 text-xs text-primary-600 hover:text-primary-700 underline"
      >
        Buka foto ukuran penuh
      </button>
    </div>
  )
}
import { IconChevronLeft, IconEdit } from '../../components/ui/icons'
import type { Arrear } from '../../types'

function DetailRow({ label, value }: { label: string; value?: string | number | null }) {
  return (
    <div className="grid grid-cols-3 gap-2 py-2">
      <dt className="text-sm text-slate-500">{label}</dt>
      <dd className="col-span-2 text-sm font-medium text-slate-900">{value ?? '-'}</dd>
    </div>
  )
}

export function TaskDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [data, setData] = useState<Arrear | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError('')
    try {
      setData(await getTask(Number(id)))
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    load()
  }, [load])

  const hasVisit = !!data?.visit

  return (
    <div>
      <PageHeader
        title="Detail Tugas"
        description={data ? `${data.nama} · ${data.no_sambungan}` : 'Detail tugas penagihan'}
        action={
          <Link
            to="/petugas/tasks"
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
        <div className="grid gap-6 lg:grid-cols-2">
          <Card className="p-5">
            <h3 className="mb-2 border-b border-slate-100 pb-3 text-sm font-semibold text-slate-900">
              Data Pelanggan
            </h3>
            <dl className="divide-y divide-slate-100">
              <DetailRow label="No Sambungan" value={data.no_sambungan} />
              <DetailRow label="Nama" value={data.nama} />
              <DetailRow label="Jumlah Tunggakan" value={`${data.jumlah_bulan_tunggakan} bulan`} />
              <DetailRow label="Periode" value={data.period?.label} />
              <div className="py-2">
                <div className="grid grid-cols-3 gap-2">
                  <span className="text-sm text-slate-500">Status</span>
                  <div className="col-span-2">
                    <StatusBadge status={data.status} />
                  </div>
                </div>
              </div>
            </dl>
          </Card>

          <Card className="p-5">
            <h3 className="mb-2 border-b border-slate-100 pb-3 text-sm font-semibold text-slate-900">
              Hasil Kunjungan
            </h3>
            {hasVisit && data.visit ? (
              <>
                <dl className="divide-y divide-slate-100">
                  <div className="grid grid-cols-3 gap-2 py-2">
                    <span className="text-sm text-slate-500">Hasil</span>
                    <div className="col-span-2">
                      <HasilBadge hasil={data.visit.status_kunjungan} />
                    </div>
                  </div>
                  <DetailRow label="Tanggal Kunjungan" value={formatDate(data.visit.visited_at)} />
                  <DetailRow
                    label="Keterangan"
                    value={data.visit.keterangan ?? HASIL_KUNJUNGAN_LABELS[data.visit.status_kunjungan]}
                  />
                </dl>
                {data.visit.foto_bukti && (
                  <div className="mt-4">
                    <p className="mb-2 text-sm text-slate-500">Foto Bukti</p>
                    <PhotoDisplay
                      src={data.visit.foto_bukti}
                      alt={`Foto bukti kunjungan ${data.nama ?? ''}`}
                    />
                  </div>
                )}
              </>
            ) : (
              <p className="py-4 text-sm text-slate-500">
                Tugas ini belum dikunjungi.
              </p>
            )}
            <div className="mt-4">
              <Link to={`/petugas/tasks/${data.id}/visit`}>
                <Button className="w-full sm:w-auto" size="lg">
                  {hasVisit ? (
                    <>
                      <IconEdit className="h-4 w-4" />
                      Ubah Hasil Kunjungan
                    </>
                  ) : (
                    'Isi Hasil Kunjungan'
                  )}
                </Button>
              </Link>
            </div>
          </Card>
        </div>
      )}
    </div>
  )
}

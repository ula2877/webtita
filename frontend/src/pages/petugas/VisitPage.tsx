import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { getTask, submitVisit } from '../../services/visitService'
import { getErrorMessage, getFieldErrors } from '../../lib/api'
import { HASIL_KUNJUNGAN_OPTIONS } from '../../lib/labels'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { Textarea, Input } from '../../components/ui/form'
import { RadioCardGroup, FormError } from '../../components/ui/RadioCard'
import { FileUpload } from '../../components/ui/FileUpload'
import { ErrorState, LoadingState } from '../../components/ui/feedback'
import { useToast } from '../../context/ToastContext'
import { IconChevronLeft, IconCalendar, IconCamera, IconCheck } from '../../components/ui/icons'
import type { Arrear, HasilKunjungan } from '../../types'

export function VisitPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()

  const [data, setData] = useState<Arrear | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  const [status, setStatus] = useState<HasilKunjungan | ''>('')
  const [keterangan, setKeterangan] = useState('')
  const [tanggalKunjungan, setTanggalKunjungan] = useState<string>('')
  const [photo, setPhoto] = useState<File | null>(null)
  const [existingPhotoUrl, setExistingPhotoUrl] = useState<string | null>(null)
  const [photoError, setPhotoError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const load = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setLoadError('')
    try {
      const task = await getTask(Number(id))
      setData(task)
      // Use arrears.foto_bukti (from arrears table) as primary, fallback to visit.foto_bukti
      const existingPhoto = task.foto_bukti || task.visit?.foto_bukti
      const existingPhotoUrlValue = task.foto_url || task.visit?.foto_url
      if (existingPhoto) {
        setExistingPhotoUrl(existingPhotoUrlValue ?? existingPhoto)
      }
      if (task.visit) {
        setStatus(task.visit.status_kunjungan)
        setKeterangan(task.visit.keterangan ?? '')
        // Use existing updated_at from arrears (which stores tanggal kunjungan)
        if (task.updated_at) {
          const date = new Date(task.updated_at)
          setTanggalKunjungan(date.toISOString().split('T')[0])
        }
      } else {
        // For new visits (not yet visited), leave date empty - user must select
        setTanggalKunjungan('')
      }
    } catch (err) {
      setLoadError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    load()
  }, [load])

  async function handleSubmit() {
    if (!data || !status) {
      setFieldErrors({ status_kunjungan: 'Pilih hasil kunjungan.' })
      return
    }
    if (!tanggalKunjungan) {
      setFieldErrors({ tanggal_kunjungan: 'Tanggal kunjungan wajib diisi.' })
      return
    }
    setSubmitting(true)
    setSubmitError('')
    setFieldErrors({})
    try {
      await submitVisit(data.id, {
        status_kunjungan: status,
        keterangan: keterangan.trim() || undefined,
        foto_bukti: photo ?? undefined,
        tanggal_kunjungan: tanggalKunjungan,
      })
      toast.success('Hasil kunjungan berhasil disimpan.')
      navigate('/petugas/tasks', { replace: true })
    } catch (err) {
      const fieldError = getFieldErrors(err)
      if (Object.keys(fieldError).length > 0) {
        setFieldErrors(fieldError)
      } else {
        setSubmitError(getErrorMessage(err))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Isi Hasil Kunjungan"
        description={data ? `${data.nama} · No. Sambungan ${data.no_sambungan}` : undefined}
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
      {loadError && !loading && <ErrorState message={loadError} onRetry={load} />}

      {data && !loading && (
        <Card className="p-5 w-full">
          <div className="mb-5 flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
            <div>
              <p className="font-medium text-slate-900">{data.nama}</p>
              <p className="text-sm text-slate-500">
                No Sambungan: {data.no_sambungan} · Tunggakan {data.jumlah_bulan_tunggakan} bln
              </p>
            </div>
            <span className="text-sm text-slate-500">{data.period?.label}</span>
          </div>

          <FormError>{submitError}</FormError>

          <div className="space-y-6 lg:grid lg:grid-cols-2 lg:gap-6">
            {/* Left Column: Tanggal Kunjungan & Foto Bukti */}
            <div className="space-y-6">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-slate-700 flex items-center gap-1.5">
                  <IconCalendar className="h-4 w-4 text-slate-400" />
                  Tanggal Kunjungan
                </label>
                <Input
                  type="date"
                  value={tanggalKunjungan}
                  onChange={(e) => {
                    setTanggalKunjungan(e.target.value)
                    setFieldErrors((prev) => ({ ...prev, tanggal_kunjungan: '' }))
                  }}
                  error={!!fieldErrors.tanggal_kunjungan}
                  errorMessage={fieldErrors.tanggal_kunjungan}
                  required
                />
              </div>

              <div>
                <h3 className="mb-2 text-sm font-semibold text-slate-900 flex items-center gap-1.5">
                  <IconCamera className="h-4 w-4 text-slate-400" />
                  Foto Bukti
                </h3>
                <FileUpload
                  file={photo}
                  existingPhotoUrl={existingPhotoUrl}
                  onChange={(file, error) => {
                    setPhoto(file)
                    setPhotoError(error ?? '')
                    // Clear existing photo when new file is selected
                    if (file) {
                      setExistingPhotoUrl(null)
                    }
                  }}
                />
                {photoError && <p className="mt-1 text-sm text-red-600">{photoError}</p>}
                <p className="mt-2 text-xs text-slate-400">
                  Foto diambil langsung melalui kamera atau diupload dari galeri.
                </p>
              </div>
            </div>

            {/* Right Column: Hasil Kunjungan & Keterangan */}
            <div className="space-y-6">
              <div>
                <h3 className="mb-2 text-sm font-semibold text-slate-900 flex items-center gap-1.5">
                  <IconCheck className="h-4 w-4 text-slate-400" />
                  Hasil Kunjungan
                </h3>
                <RadioCardGroup
                  name="status_kunjungan"
                  value={status}
                  onChange={(value) => {
                    setStatus(value as HasilKunjungan)
                    setFieldErrors((prev) => ({ ...prev, status_kunjungan: '' }))
                  }}
                  options={HASIL_KUNJUNGAN_OPTIONS}
                />
                {fieldErrors.status_kunjungan && (
                  <p className="mt-1 text-sm text-red-600">{fieldErrors.status_kunjungan}</p>
                )}
              </div>

              <div>
                <h3 className="mb-2 text-sm font-semibold text-slate-900">Keterangan</h3>
                <Textarea
                  name="keterangan"
                  rows={3}
                  value={keterangan}
                  onChange={(e) => setKeterangan(e.target.value)}
                  placeholder={
                    status === 'lainnya'
                      ? 'Tuliskan keterangan...'
                      : 'Catatan kunjungan (opsional)'
                  }
                  error={!!fieldErrors.keterangan}
                  errorMessage={fieldErrors.keterangan}
                  maxLength={1000}
                />
                {fieldErrors.keterangan && (
                  <p className="mt-1 text-sm text-red-600">{fieldErrors.keterangan}</p>
                )}
              </div>
            </div>
          </div>

          <Button
            type="button"
            size="lg"
            className="w-full lg:w-auto"
            loading={submitting}
            onClick={handleSubmit}
          >
            {submitting ? 'Menyimpan...' : 'Simpan Hasil Kunjungan'}
          </Button>
        </Card>
      )}
    </div>
  )
}

import { useState } from 'react'
import type { ChangeEvent, DragEvent } from 'react'
import { importWilayah } from '../../services/importService'
import { getErrorMessage } from '../../lib/api'
import { formatNumber } from '../../lib/format'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { FormError } from '../../components/ui/RadioCard'
import { LoadingState } from '../../components/ui/feedback'
import { useToast } from '../../context/ToastContext'
import { IconCheck, IconUpload, IconX } from '../../components/ui/icons'
import type {
  DetailTagihanImportDetail,
  DetailTagihanImportResult,
  PetugasImportDetail,
  PetugasImportResult,
  StrukturImportResult,
  WilayahImportDetail,
  WilayahImportResult,
} from '../../types'

const MAX_SIZE = 10 * 1024 * 1024

function PetugasResultRow({ item }: { item: PetugasImportDetail }) {
  if (item.status === 'invalid') {
    return (
      <li className="flex items-start gap-2 text-sm text-red-700">
        <IconX className="mt-0.5 h-4 w-4 shrink-0" />
        <span>
          {item.row !== null ? `Baris ${item.row}: ` : ''}
          {item.name ? `${item.name} — ${item.note}` : item.note}
        </span>
      </li>
    )
  }

  return (
    <li className="flex items-start gap-2 text-sm text-slate-700">
      <IconCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
      <span>
        {item.name} — {item.note}
      </span>
    </li>
  )
}

function WilayahResultRow({ item }: { item: WilayahImportDetail }) {
  const invalid = item.status === 'invalid'
  return (
    <li className={`flex items-start gap-2 text-sm ${invalid ? 'text-red-700' : 'text-slate-700'}`}>
      {invalid ? (
        <IconX className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
      ) : (
        <IconCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
      )}
      <span>
        Baris {item.row} · {item.nama} ({item.code}) — {item.note}
      </span>
    </li>
  )
}

function DetailTagihanResultRow({ item }: { item: DetailTagihanImportDetail }) {
  const invalid = item.status === 'invalid'
  return (
    <li className={`flex items-start gap-2 text-sm ${invalid ? 'text-red-700' : 'text-slate-700'}`}>
      {invalid ? (
        <IconX className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
      ) : (
        <IconCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
      )}
      <span>
        Baris {item.row} · {item.no_sambungan} · {item.nama} ({formatNumber(item.tagihan)}) —{' '}
        {item.note}
      </span>
    </li>
  )
}

function ResultStat({
  value,
  label,
  tone,
}: {
  value: number
  label: string
  tone: 'primary' | 'emerald' | 'slate' | 'red'
}) {
  const boxTones = {
    primary: 'bg-primary-50 text-primary-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    slate: 'bg-slate-100 text-slate-700',
    red: 'bg-red-50 text-red-700',
  }
  const labelTones = {
    primary: 'text-primary-600',
    emerald: 'text-emerald-600',
    slate: 'text-slate-600',
    red: 'text-red-600',
  }
  return (
    <div className={`rounded-lg p-3 text-center ${boxTones[tone]}`}>
      <p className="text-xl font-semibold">{formatNumber(value)}</p>
      <p className={`mt-1 text-xs ${labelTones[tone]}`}>{label}</p>
    </div>
  )
}

function PetugasResultCard({ petugas }: { petugas: PetugasImportResult }) {
  return (
    <Card className="flex h-full flex-col p-5">
      <div className="flex items-center justify-between gap-2">
        <h4 className="text-sm font-semibold text-slate-900">Hasil Sinkronisasi Petugas</h4>
        <p className="text-xs text-slate-500">Sheet: {petugas.sheet_name}</p>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <ResultStat value={petugas.created} label="User Baru" tone="emerald" />
        <ResultStat value={petugas.existing} label="Sudah Ada" tone="slate" />
        <ResultStat value={petugas.invalid} label="Data Invalid" tone="red" />
        <ResultStat value={petugas.total} label="Total Data" tone="primary" />
      </div>
      {petugas.details.length > 0 && (
        <ul className="mt-3 max-h-60 flex-1 space-y-1 overflow-y-auto border-t border-slate-100 pt-3">
          {petugas.details.map((item, index) => (
            <PetugasResultRow key={index} item={item} />
          ))}
        </ul>
      )}
    </Card>
  )
}

function WilayahResultCard({ wilayah }: { wilayah: WilayahImportResult }) {
  return (
    <Card className="flex h-full flex-col p-5">
      <div className="flex items-center justify-between gap-2">
        <h4 className="text-sm font-semibold text-slate-900">Hasil Sinkronisasi Wilayah</h4>
        <p className="text-xs text-slate-500">Sheet: {wilayah.sheet_name}</p>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <ResultStat value={wilayah.created} label="Wilayah Baru" tone="emerald" />
        <ResultStat value={wilayah.updated} label="Diperbarui" tone="slate" />
        <ResultStat value={wilayah.invalid} label="Data Invalid" tone="red" />
        <ResultStat value={wilayah.total} label="Total Data" tone="primary" />
      </div>
      {wilayah.details.length > 0 && (
        <ul className="mt-3 max-h-60 flex-1 space-y-1 overflow-y-auto border-t border-slate-100 pt-3">
          {wilayah.details.map((item, index) => (
            <WilayahResultRow key={index} item={item} />
          ))}
        </ul>
      )}
    </Card>
  )
}

function DetailTagihanResultCard({ detailTagihan }: { detailTagihan: DetailTagihanImportResult }) {
  return (
    <Card className="p-5 lg:col-span-2">
      <div className="flex items-center justify-between gap-2">
        <h4 className="text-sm font-semibold text-slate-900">Hasil Sinkronisasi Detail Tagihan</h4>
        <p className="text-xs text-slate-500">
          {detailTagihan.sheet_count > 1
            ? `${detailTagihan.sheet_count} sheet`
            : `Sheet: ${detailTagihan.sheet_name}`}
        </p>
      </div>
      {detailTagihan.sheets && detailTagihan.sheets.length > 1 && (
        <div className="mt-3 space-y-2">
          {detailTagihan.sheets.map((sheet) => (
            <div key={sheet.sheet_name} className="rounded-md border border-slate-100 bg-slate-50 p-2">
              <p className="text-xs font-medium text-slate-700">{sheet.sheet_name}</p>
              <div className="mt-1 grid grid-cols-4 gap-2 text-xs text-slate-500">
                <span>Total: {sheet.total}</span>
                <span>Baru: {sheet.pelanggan_created}</span>
                <span>Update: {sheet.pelanggan_updated}</span>
                <span>Invalid: {sheet.invalid}</span>
              </div>
            </div>
          ))}
        </div>
      )}
      <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <ResultStat value={detailTagihan.pelanggan_created} label="Pelanggan Baru" tone="emerald" />
        <ResultStat value={detailTagihan.pelanggan_updated} label="Pelanggan Diperbarui" tone="slate" />
        <ResultStat value={detailTagihan.tagihan_created} label="Tagihan Baru" tone="primary" />
        <ResultStat value={detailTagihan.tagihan_updated} label="Tagihan Diperbarui" tone="slate" />
        <ResultStat value={detailTagihan.invalid} label="Data Invalid" tone="red" />
        <ResultStat value={detailTagihan.total} label="Total Data" tone="primary" />
      </div>
      {detailTagihan.wilayah_not_found.length > 0 && (
        <div className="mt-3 rounded-lg bg-amber-50 p-3">
          <p className="text-xs font-medium text-amber-700">
            Kelompok berikut tidak bisa dicocokkan ke wilayah rekap:
          </p>
          <ul className="mt-1 list-inside list-disc space-y-0.5 text-xs text-amber-700">
            {detailTagihan.wilayah_not_found.map((name) => (
              <li key={name}>{name}</li>
            ))}
          </ul>
        </div>
      )}
      {detailTagihan.details.length > 0 && (
        <ul className="mt-3 max-h-60 space-y-1 overflow-y-auto border-t border-slate-100 pt-3">
          {detailTagihan.details.map((item, index) => (
            <DetailTagihanResultRow key={index} item={item} />
          ))}
        </ul>
      )}
    </Card>
  )
}

function StrukturImportCard() {
  const toast = useToast()
  const [file, setFile] = useState<File | null>(null)
  const [fileError, setFileError] = useState('')
  const [dragging, setDragging] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [result, setResult] = useState<StrukturImportResult | null>(null)

  function handleFiles(files: FileList | null) {
    const selected = files?.[0]
    if (!selected) return
    const extension = selected.name.split('.').pop()?.toLowerCase()
    if (extension !== 'xlsx' && extension !== 'xls') {
      setFileError('File harus berformat .xlsx atau .xls.')
      setFile(null)
      return
    }
    if (selected.size > MAX_SIZE) {
      setFileError('Ukuran file maksimal 10 MB.')
      setFile(null)
      return
    }
    setFileError('')
    setFile(selected)
  }

  function onDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault()
    setDragging(false)
    handleFiles(event.dataTransfer.files)
  }

  async function handleUpload() {
    if (!file) return
    setUploading(true)
    setResult(null)
    setFileError('')
    try {
      const data = await importWilayah(file)
      setResult(data)
      toast.success(data.message)
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setUploading(false)
    }
  }

  const petugas = result?.petugas
  const wilayah = result?.wilayah
  const detailTagihan = result?.detail_tagihan

  return (
    <>
      <Card className="max-w-3xl p-5">
        <h3 className="text-sm font-semibold text-slate-900">Sinkronisasi Petugas, Wilayah &amp; Tagihan</h3>
        <p className="mt-1 text-xs text-slate-500">
          Buat atau perbarui petugas, wilayah, pelanggan, dan tagihan dari struktur Excel.
        </p>

        <ul className="mt-3 space-y-1 text-xs text-slate-600">
          <li className="flex items-start gap-2">
            <IconCheck className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-600" />
            <span>
              <span className="font-medium text-slate-700">Sheet yang akan diproses:</span>{' '}
              ✓ ketua kelompok, lalu ✓ rekap wilayah, lalu ✓ detail tagihan
            </span>
          </li>
          <li className="flex items-start gap-2">
            <IconCheck className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-600" />
            <span>Nama dari kolom "nama" dijadikan user petugas baru jika belum ada.</span>
          </li>
          <li className="flex items-start gap-2">
            <IconCheck className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-600" />
            <span>Baris rekap wilayah dicocokkan ke petugas & disimpan berdasarkan kode (upsert).</span>
          </li>
          <li className="flex items-start gap-2">
            <IconCheck className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-600" />
            <span>
              Pelanggan pada "detail tagihan" disimpan berdasarkan NO.SAMBUNGAN; tagihan per periode
              di-update jika sudah ada.
            </span>
          </li>
          <li className="flex items-start gap-2">
            <IconCheck className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-600" />
            <span>Import bersifat non-destruktif (tidak menghapus data lama).</span>
          </li>
        </ul>

        <div className="mt-4">
          {file ? (
            <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
              <div className="flex min-w-0 items-center gap-3">
                <div className="rounded-lg bg-primary-100 p-2 text-primary-600">
                  <IconUpload className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-slate-800">{file.name}</p>
                  <p className="text-xs text-slate-500">
                    {(file.size / (1024 * 1024)).toFixed(1)} MB
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setFile(null)}
                aria-label="Hapus file"
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
              >
                <IconX className="h-4 w-4" />
              </button>
            </div>
          ) : (
            <div
              onDragOver={(e) => {
                e.preventDefault()
                setDragging(true)
              }}
              onDragLeave={() => setDragging(false)}
              onDrop={onDrop}
              onClick={() => document.getElementById('struktur-excel-input')?.click()}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  document.getElementById('struktur-excel-input')?.click()
                }
              }}
              className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-10 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 ${
                dragging
                  ? 'border-primary-500 bg-primary-50'
                  : 'border-slate-300 bg-white hover:border-slate-400'
              }`}
            >
              <IconUpload className="h-8 w-8 text-slate-400" />
              <p className="text-sm font-medium text-slate-700">
                Drag &amp; Drop Excel <span className="font-normal text-slate-400">atau</span>{' '}
                <span className="text-primary-600">Pilih File</span>
              </p>
              <p className="text-xs text-slate-500">Format: .xlsx / .xls · Maksimal 10 MB</p>
            </div>
          )}
          <input
            id="struktur-excel-input"
            type="file"
            accept=".xlsx,.xls"
            onChange={(e: ChangeEvent<HTMLInputElement>) => handleFiles(e.target.files)}
            className="hidden"
          />
        </div>

        {fileError && (
          <div className="mt-2">
            <FormError>{fileError}</FormError>
          </div>
        )}

        <div className="mt-5">
          <Button loading={uploading} disabled={!file} onClick={handleUpload} size="lg">
            {uploading ? 'Memproses...' : 'Upload & Sinkronkan'}
          </Button>
        </div>

        {uploading && (
          <div className="mt-4">
            <LoadingState label="Memproses file Excel, mohon tunggu..." />
          </div>
        )}
      </Card>

      {result && !uploading && (
        <div className="mt-6">
          <h3 className="mb-4 text-sm font-semibold text-slate-900">Hasil Sinkronisasi</h3>
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {petugas && <PetugasResultCard petugas={petugas} />}
            {wilayah && <WilayahResultCard wilayah={wilayah} />}
            {detailTagihan && <DetailTagihanResultCard detailTagihan={detailTagihan} />}
          </div>
        </div>
      )}
    </>
  )
}

export function ImportPage() {
  return (
    <div>
      <PageHeader
        title="Import Data Tunggakan"
        description="Upload file Excel berisi data tunggakan pelanggan"
      />
      <StrukturImportCard />
    </div>
  )
}
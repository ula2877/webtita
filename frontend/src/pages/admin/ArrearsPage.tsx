import { useCallback, useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import {
  assignArrears,
  deleteArrear,
  deleteArrears,
  getArrears,
  updateArrear,
} from '../../services/arrearsService'
import { getPetugas } from '../../services/petugasService'
import { getWilayah } from '../../services/wilayahService'
import { getPeriods } from '../../services/dashboardService'
import { exportExcel } from '../../services/exportService'
import { getErrorMessage, getFieldErrors } from '../../lib/api'
import { formatDate, formatNumber, formatRupiah } from '../../lib/format'
import { HASIL_KUNJUNGAN_LABELS } from '../../lib/labels'
import { Card, PageHeader } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { Input, Select, Combobox } from '../../components/ui/form'
import { SearchInput } from '../../components/ui/SearchInput'
import { Pagination } from '../../components/ui/Pagination'
import { ErrorState, LoadingState, EmptyState } from '../../components/ui/feedback'
import { HasilBadge, StatusBadge } from '../../components/ui/Badge'
import { Modal, ConfirmDialog } from '../../components/ui/Modal'

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
import { FormError } from '../../components/ui/RadioCard'
import { useToast } from '../../context/ToastContext'
import {
  IconDownload,
  IconEye,
  IconChevronDown,
  IconEdit,
  IconTrash,
} from '../../components/ui/icons'
import type { Arrear, Paginated, Period, Petugas, Wilayah } from '../../types'

function SortHeader({
  label,
  active,
  order,
  onClick,
}: {
  label: string
  active: boolean
  order: 'asc' | 'desc'
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="inline-flex items-center gap-1 font-medium hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
    >
      {label}
      <IconChevronDown
        className={`h-3.5 w-3.5 transition-transform ${active && order === 'desc' ? 'rotate-180' : ''} ${
          active ? 'text-primary-600' : 'text-slate-300'
        }`}
      />
    </button>
  )
}

function HeaderCheckbox({
  checked,
  indeterminate,
  onChange,
  label,
}: {
  checked: boolean
  indeterminate: boolean
  onChange: () => void
  label: string
}) {
  const ref = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (ref.current) {
      ref.current.indeterminate = indeterminate
    }
  }, [indeterminate])

  return (
    <input
      ref={ref}
      type="checkbox"
      aria-label={label}
      checked={checked}
      onChange={onChange}
      className="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
    />
  )
}

function DetailRow({ label, value }: { label: string; value?: string | number | null }) {
  return (
    <div className="grid grid-cols-3 gap-2 py-2">
      <dt className="text-sm text-slate-500">{label}</dt>
      <dd className="col-span-2 text-sm font-medium text-slate-900">{value ?? '-'}</dd>
    </div>
  )
}

function SectionLabel({ children }: { children: string }) {
  return (
    <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-400">{children}</h4>
  )
}

function ArrearDetailContent({ arrear, onClose }: { arrear: Arrear; onClose: () => void }) {
  const visit = arrear.visit

  return (
    <div>
      <div className="space-y-5">
        <section>
          <SectionLabel>Informasi Pelanggan</SectionLabel>
          <dl className="mt-1 divide-y divide-slate-100">
            <DetailRow label="Nama" value={arrear.nama} />
            <DetailRow label="No Sambungan" value={arrear.no_sambungan} />
            <DetailRow label="Alamat" value={arrear.address} />
          </dl>
        </section>

        <section>
          <SectionLabel>Wilayah &amp; Petugas</SectionLabel>
          <dl className="mt-1 divide-y divide-slate-100">
            <DetailRow label="Wilayah" value={arrear.wilayah?.name} />
            <DetailRow label="Petugas" value={arrear.petugas?.name} />
          </dl>
        </section>

        <section>
          <SectionLabel>Data Tagihan</SectionLabel>
          <dl className="mt-1 divide-y divide-slate-100">
            <DetailRow label="Periode" value={arrear.period?.label} />
            <DetailRow label="Tunggakan" value={`${arrear.jumlah_bulan_tunggakan} bln`} />
          </dl>
          <div className="mt-3 rounded-lg bg-primary-50 p-4">
            <p className="text-xs font-medium text-primary-600">Nominal Tagihan</p>
            <p className="mt-1 text-2xl font-bold text-primary-700">
              {formatRupiah(arrear.jumlah_tagihan)}
            </p>
          </div>
        </section>

        <section>
          <SectionLabel>Hasil Kunjungan</SectionLabel>
          <dl className="mt-1 divide-y divide-slate-100">
            <div className="grid grid-cols-3 gap-2 py-2">
              <dt className="text-sm text-slate-500">Status</dt>
              <dd className="col-span-2">
                <StatusBadge status={arrear.status} />
              </dd>
            </div>
            {visit ? (
              <>
                <div className="grid grid-cols-3 gap-2 py-2">
                  <dt className="text-sm text-slate-500">Hasil</dt>
                  <dd className="col-span-2">
                    <HasilBadge hasil={visit.status_kunjungan} />
                  </dd>
                </div>
                <DetailRow label="Tanggal Kunjungan" value={formatDate(visit.visited_at)} />
                <DetailRow label="Keterangan" value={visit.keterangan} />
              </>
            ) : (
              <>
                <DetailRow label="Tanggal Kunjungan" />
                <DetailRow label="Keterangan" />
              </>
            )}
          </dl>
          <div className="mt-3">
            {visit?.foto_bukti ? (
              <PhotoDisplay
                src={visit.foto_bukti}
                alt={`Foto bukti kunjungan ${arrear.nama ?? ''}`}
              />
            ) : (
              <p className="text-sm text-slate-500">Belum ada foto bukti.</p>
            )}
          </div>
        </section>
      </div>

      <div className="mt-5 flex justify-end border-t border-slate-100 pt-4">
        <Button variant="secondary" onClick={onClose}>
          Tutup
        </Button>
      </div>
    </div>
  )
}

interface EditForm {
  petugas_id: string
  jumlah_bulan_tunggakan: string
  jumlah_tagihan: string
}

export function ArrearsPage() {
  const toast = useToast()

  const [search, setSearch] = useState('')
  const [periodId, setPeriodId] = useState<number | undefined>(undefined)
  const [petugasFilterId, setPetugasFilterId] = useState<number | undefined>(undefined)
  const [wilayahFilterId, setWilayahFilterId] = useState<number | undefined>(undefined)
  const [statusFilter, setStatusFilter] = useState('')
  const [hasilFilter, setHasilFilter] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [sort, setSort] = useState<{ field: string; order: 'asc' | 'desc' }>({
    field: 'id',
    order: 'desc',
  })

  const [data, setData] = useState<Paginated<Arrear> | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [petugasList, setPetugasList] = useState<Petugas[]>([])
  const [wilayahList, setWilayahList] = useState<Wilayah[]>([])
  const [periodList, setPeriodList] = useState<Period[]>([])
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [assignPetugasId, setAssignPetugasId] = useState('')
  const [assigning, setAssigning] = useState(false)
  const [exporting, setExporting] = useState(false)

  const [editingArrear, setEditingArrear] = useState<Arrear | null>(null)
  const [editForm, setEditForm] = useState<EditForm>({
    petugas_id: '',
    jumlah_bulan_tunggakan: '',
    jumlah_tagihan: '',
  })
  const [savingEdit, setSavingEdit] = useState(false)
  const [editFormError, setEditFormError] = useState('')
  const [editFieldErrors, setEditFieldErrors] = useState<Record<string, string>>({})

  const [deleteTarget, setDeleteTarget] = useState<Arrear | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false)
  const [bulkDeleting, setBulkDeleting] = useState(false)
  const [detailArrear, setDetailArrear] = useState<Arrear | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const result = await getArrears({
        page,
        per_page: perPage,
        search: search || undefined,
        period_id: periodId,
        petugas_id: petugasFilterId,
        wilayah_id: wilayahFilterId,
        status: statusFilter || undefined,
        hasil_kunjungan: hasilFilter || undefined,
        sort: sort.field,
        order: sort.order,
      })
      setData(result)
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, search, periodId, petugasFilterId, wilayahFilterId, statusFilter, hasilFilter, sort])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    getPetugas()
      .then(setPetugasList)
      .catch(() => {
        // filters remain usable without petugas data
      })
    getWilayah({ per_page: 100 })
      .then((result) => setWilayahList(result.data))
      .catch(() => {
        // filters remain usable without wilayah data
      })
    getPeriods()
      .then(setPeriodList)
      .catch(() => {
        // filters remain usable without periods data
      })
  }, [])

  function resetPage() {
    setPage(1)
  }

  function clearSelection() {
    setSelected(new Set())
  }

  function toggleSort(field: string) {
    setSort((prev) => {
      if (prev.field === field) {
        return { field, order: prev.order === 'asc' ? 'desc' : 'asc' }
      }
      return { field, order: 'asc' }
    })
    resetPage()
  }

  function toggleSelect(id: number) {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  function toggleSelectAll() {
    setSelected((prev) => {
      if (!data) return prev
      const allSelected = data.data.every((item) => prev.has(item.id))
      const next = new Set(prev)
      if (allSelected) {
        data.data.forEach((item) => next.delete(item.id))
      } else {
        data.data.forEach((item) => next.add(item.id))
      }
      return next
    })
  }

  async function handleAssign() {
    if (!assignPetugasId || selected.size === 0) return
    setAssigning(true)
    try {
      const result = await assignArrears(Array.from(selected), Number(assignPetugasId))
      toast.success(result.message)
      setSelected(new Set())
      setAssignPetugasId('')
      load()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setAssigning(false)
    }
  }

  function openEdit(arrear: Arrear) {
    setEditingArrear(arrear)
    setEditForm({
      petugas_id: arrear.petugas?.id != null ? String(arrear.petugas.id) : '',
      jumlah_bulan_tunggakan: String(arrear.jumlah_bulan_tunggakan),
      jumlah_tagihan: arrear.jumlah_tagihan != null ? String(arrear.jumlah_tagihan) : '',
    })
    setEditFormError('')
    setEditFieldErrors({})
  }

  async function handleSaveEdit(event: FormEvent) {
    event.preventDefault()
    if (!editingArrear) return
    setSavingEdit(true)
    setEditFormError('')
    setEditFieldErrors({})
    try {
      const result = await updateArrear(editingArrear.id, {
        petugas_id: editForm.petugas_id ? Number(editForm.petugas_id) : null,
        jumlah_bulan_tunggakan: Number(editForm.jumlah_bulan_tunggakan),
        jumlah_tagihan: editForm.jumlah_tagihan ? Number(editForm.jumlah_tagihan) : null,
      })
      toast.success(result.message ?? 'Data berhasil diperbarui.')
      setEditingArrear(null)
      load()
    } catch (err) {
      const fieldError = getFieldErrors(err)
      if (Object.keys(fieldError).length > 0) {
        setEditFieldErrors(fieldError)
      } else {
        setEditFormError(getErrorMessage(err))
      }
    } finally {
      setSavingEdit(false)
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const { message } = await deleteArrear(deleteTarget.id)
      toast.success(message ?? 'Data berhasil dihapus.')
      setDeleteTarget(null)
      setSelected((prev) => {
        const next = new Set(prev)
        next.delete(deleteTarget.id)
        return next
      })
      if (data && page > 1 && data.meta.total - 1 <= (page - 1) * perPage) {
        setPage(page - 1)
      } else {
        load()
      }
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setDeleting(false)
    }
  }

  function handleBulkCancel() {
    if (bulkDeleting) return
    setBulkDeleteOpen(false)
  }

  async function handleBulkDelete() {
    if (selected.size === 0) return
    const ids = Array.from(selected)
    setBulkDeleting(true)
    try {
      const result = await deleteArrears(ids)
      toast.success(`${formatNumber(result.deleted_count)} data berhasil dihapus.`)
      setBulkDeleteOpen(false)
      setSelected(new Set())
      if (data && page > 1 && data.meta.total - result.deleted_count <= (page - 1) * perPage) {
        setPage(page - 1)
      } else {
        load()
      }
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setBulkDeleting(false)
    }
  }

  async function handleExport() {
    setExporting(true)
    try {
      const { blob, filename } = await exportExcel({
        period_id: periodId,
        petugas_id: petugasFilterId,
        status: statusFilter || undefined,
        hasil_kunjungan: hasilFilter || undefined,
      })
      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = filename
      document.body.appendChild(anchor)
      anchor.click()
      anchor.remove()
      URL.revokeObjectURL(url)
      toast.success('Data berhasil diexport.')
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setExporting(false)
    }
  }

  const activeFilters =
    search || periodId || petugasFilterId || wilayahFilterId || statusFilter || hasilFilter

  return (
    <div>
      <PageHeader
        title="Data Tunggakan"
        description="Data tunggakan pelanggan per periode"
        action={
          <Button variant="secondary" loading={exporting} onClick={handleExport}>
            <IconDownload className="h-4 w-4" />
            Export Excel
          </Button>
        }
      />

      <Card className="mb-4 p-4">
        <div className="grid gap-3 lg:grid-cols-12">
          <div className="lg:col-span-2">
            <SearchInput
              name="search"
              label="Cari"
              value={search}
              onChange={(value) => {
                setSearch(value)
                clearSelection()
                resetPage()
              }}
              placeholder="Cari nama / no sambungan..."
            />
          </div>
          <div className="lg:col-span-2">
            <Select
              name="period_id"
              label="Periode"
              value={periodId ?? ''}
              onChange={(e) => {
                setPeriodId(e.target.value ? Number(e.target.value) : undefined)
                clearSelection()
                resetPage()
              }}
            >
              <option value="">Semua</option>
              {periodList.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.label}
                </option>
              ))}
            </Select>
          </div>
          <div className="lg:col-span-2">
            <Select
              name="petugas_id"
              label="Petugas"
              value={petugasFilterId ?? ''}
              onChange={(e) => {
                setPetugasFilterId(e.target.value ? Number(e.target.value) : undefined)
                clearSelection()
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
          <div className="lg:col-span-2">
            <Combobox
              name="wilayah_id"
              label="Wilayah"
              placeholder="Semua Wilayah"
              value={wilayahFilterId ?? ''}
              onChange={(value) => {
                setWilayahFilterId(value === '' ? undefined : Number(value))
                clearSelection()
                resetPage()
              }}
              options={wilayahList.map((w) => ({
                value: w.id,
                label: `${w.code} · ${w.name}`,
              }))}
              searchPlaceholder="Cari wilayah..."
              clearable
            />
          </div>
          <div className="lg:col-span-2">
            <Select
              name="status"
              label="Status"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value)
                clearSelection()
                resetPage()
              }}
            >
              <option value="">Semua</option>
              <option value="belum_dikunjungi">Belum Dikunjungi</option>
              <option value="sudah_dikunjungi">Sudah Dikunjungi</option>
            </Select>
          </div>
          <div className="lg:col-span-2">
            <Select
              name="hasil_kunjungan"
              label="Hasil Kunjungan"
              value={hasilFilter}
              onChange={(e) => {
                setHasilFilter(e.target.value)
                clearSelection()
                resetPage()
              }}
            >
              <option value="">Semua</option>
              {Object.entries(HASIL_KUNJUNGAN_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex items-end justify-end lg:col-span-12">
            <Button
              variant="ghost"
              onClick={() => {
                setSearch('')
                setPeriodId(undefined)
                setPetugasFilterId(undefined)
                setWilayahFilterId(undefined)
                setStatusFilter('')
                setHasilFilter('')
                setSort({ field: 'id', order: 'desc' })
                clearSelection()
                resetPage()
              }}
            >
              Reset
            </Button>
          </div>
        </div>
      </Card>

      {selected.size > 0 && (
        <Card className="mb-4 border-primary-200 bg-primary-50 p-4">
          <div className="flex flex-wrap items-center gap-3">
            <p className="text-sm font-medium text-primary-800">
              {formatNumber(selected.size)} data dipilih
            </p>
            <div className="min-w-48 flex-1 sm:max-w-xs">
              <Select
                name="assign_petugas"
                aria-label="Pilih petugas"
                value={assignPetugasId}
                onChange={(e) => setAssignPetugasId(e.target.value)}
              >
                <option value="">Pilih Petugas...</option>
                {petugasList
                  .filter((p) => p.is_active)
                  .map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
              </Select>
            </div>
            <Button
              size="md"
              disabled={!assignPetugasId}
              loading={assigning}
              onClick={handleAssign}
            >
              Assign
            </Button>
            <Button variant="danger" onClick={() => setBulkDeleteOpen(true)}>
              <IconTrash className="h-4 w-4" />
              Hapus Terpilih
            </Button>
          </div>
        </Card>
      )}

      {error && <ErrorState message={error} onRetry={load} />}

      {loading && <Card className="p-4"><LoadingState /></Card>}

      {!loading && data && (
        <>
          {data.data.length === 0 ? (
            <Card>
              <EmptyState
                title={activeFilters ? 'Tidak ada hasil pencarian' : 'Belum ada data tunggakan'}
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
                      <th className="px-4 py-3">
                        <HeaderCheckbox
                          label="Pilih semua"
                          checked={data.data.every((item) => selected.has(item.id))}
                          indeterminate={
                            !data.data.every((item) => selected.has(item.id)) &&
                            data.data.some((item) => selected.has(item.id))
                          }
                          onChange={toggleSelectAll}
                        />
                      </th>
                      <th className="px-4 py-3 font-medium">No</th>
                      <th className="px-4 py-3 font-medium">
                        <SortHeader
                          label="No Sambungan"
                          active={sort.field === 'no_sambungan'}
                          order={sort.order}
                          onClick={() => toggleSort('no_sambungan')}
                        />
                      </th>
                      <th className="px-4 py-3 font-medium">
                        <SortHeader
                          label="Nama Pelanggan"
                          active={sort.field === 'nama'}
                          order={sort.order}
                          onClick={() => toggleSort('nama')}
                        />
                      </th>
                      <th className="px-4 py-3 font-medium">
                        <SortHeader
                          label="Tunggakan"
                          active={sort.field === 'jumlah_bulan_tunggakan'}
                          order={sort.order}
                          onClick={() => toggleSort('jumlah_bulan_tunggakan')}
                        />
                      </th>
                      <th className="px-4 py-3 font-medium">Petugas</th>
                      <th className="px-4 py-3 font-medium">
                        <SortHeader
                          label="Status"
                          active={sort.field === 'status'}
                          order={sort.order}
                          onClick={() => toggleSort('status')}
                        />
                      </th>
                      <th className="px-4 py-3 font-medium">Hasil Kunjungan</th>
                      <th className="px-4 py-3 font-medium">Tanggal Kunjungan</th>
                      <th className="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {data.data.map((item, index) => (
                      <tr key={item.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">
                          <input
                            type="checkbox"
                            aria-label={`Pilih ${item.nama ?? ''}`}
                            checked={selected.has(item.id)}
                            onChange={() => toggleSelect(item.id)}
                            className="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                          />
                        </td>
                        <td className="px-4 py-3 text-slate-500">
                          {(data.meta.current_page - 1) * data.meta.per_page + index + 1}
                        </td>
                        <td className="px-4 py-3 font-medium text-slate-900">{item.no_sambungan}</td>
                        <td className="px-4 py-3 text-slate-700">{item.nama}</td>
                        <td className="px-4 py-3 text-slate-700">
                          {item.jumlah_bulan_tunggakan} bln
                        </td>
                        <td className="px-4 py-3 text-slate-600">{item.petugas?.name ?? '-'}</td>
                        <td className="px-4 py-3">
                          <StatusBadge status={item.status} />
                        </td>
                        <td className="px-4 py-3">
                          {item.visit ? (
                            <HasilBadge hasil={item.visit.status_kunjungan} />
                          ) : (
                            <span className="text-slate-400">-</span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-slate-600">
                          {formatDate(item.visit?.visited_at)}
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-1">
                            <button
                          type="button"
                          onClick={() => setDetailArrear(item)}
                          className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50"
                        >
                          <IconEye className="h-4 w-4" />
                          Detail
                        </button>
                        <button
                          type="button"
                          onClick={() => openEdit(item)}
                          className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50"
                        >
                          <IconEdit className="h-4 w-4" />
                          Edit
                        </button>
                            <button
                              type="button"
                              onClick={() => setDeleteTarget(item)}
                              className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                            >
                              <IconTrash className="h-4 w-4" />
                              Hapus
                            </button>
                          </div>
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
                      <div className="flex items-start gap-3">
                        <input
                          type="checkbox"
                          aria-label={`Pilih ${item.nama ?? ''}`}
                          checked={selected.has(item.id)}
                          onChange={() => toggleSelect(item.id)}
                          className="mt-1 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                        />
                        <div>
                          <p className="font-medium text-slate-900">{item.nama}</p>
                          <p className="mt-0.5 text-sm text-slate-500">
                            No. Sambungan: {item.no_sambungan}
                          </p>
                        </div>
                      </div>
                      <StatusBadge status={item.status} />
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
                      <div>
                        <p className="text-xs text-slate-400">Tunggakan</p>
                        <p className="text-slate-700">{item.jumlah_bulan_tunggakan} bln</p>
                      </div>
                      <div>
                        <p className="text-xs text-slate-400">Petugas</p>
                        <p className="text-slate-700">{item.petugas?.name ?? '-'}</p>
                      </div>
                      <div>
                        <p className="text-xs text-slate-400">Hasil Kunjungan</p>
                        {item.visit ? (
                          <HasilBadge hasil={item.visit.status_kunjungan} />
                        ) : (
                          <span className="text-slate-400">-</span>
                        )}
                      </div>
                      <div>
                        <p className="text-xs text-slate-400">Tanggal Kunjungan</p>
                        <p className="text-slate-700">{formatDate(item.visit?.visited_at)}</p>
                      </div>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setDetailArrear(item)}
                        className="inline-flex items-center gap-1 rounded-lg text-sm font-medium text-primary-600"
                      >
                        <IconEye className="h-4 w-4" />
                        Detail
                      </button>
                      <button
                        type="button"
                        onClick={() => openEdit(item)}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-primary-600 hover:bg-slate-50"
                      >
                        <IconEdit className="h-4 w-4" />
                        Edit
                      </button>
                      <button
                        type="button"
                        onClick={() => setDeleteTarget(item)}
                        className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                      >
                        <IconTrash className="h-4 w-4" />
                        Hapus
                      </button>
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

      <Modal
        open={!!detailArrear}
        title="Detail Tagihan"
        onClose={() => setDetailArrear(null)}
        size="lg"
      >
        {detailArrear && (
          <ArrearDetailContent arrear={detailArrear} onClose={() => setDetailArrear(null)} />
        )}
      </Modal>

      <Modal
        open={!!editingArrear}
        title="Edit Data Tunggakan"
        onClose={() => setEditingArrear(null)}
      >
        <FormError>{editFormError}</FormError>
        {editingArrear && (
          <form onSubmit={handleSaveEdit} className="space-y-4">
            <dl className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-slate-50">
              <div className="grid grid-cols-3 gap-2 px-4 py-2.5">
                <dt className="text-sm text-slate-500">No Sambungan</dt>
                <dd className="col-span-2 text-sm font-medium text-slate-900">
                  {editingArrear.no_sambungan ?? '-'}
                </dd>
              </div>
              <div className="grid grid-cols-3 gap-2 px-4 py-2.5">
                <dt className="text-sm text-slate-500">Nama Pelanggan</dt>
                <dd className="col-span-2 text-sm font-medium text-slate-900">
                  {editingArrear.nama ?? '-'}
                </dd>
              </div>
              <div className="grid grid-cols-3 gap-2 px-4 py-2.5">
                <dt className="text-sm text-slate-500">Alamat</dt>
                <dd className="col-span-2 text-sm font-medium text-slate-900">
                  {editingArrear.address || '-'}
                </dd>
              </div>
              <div className="grid grid-cols-3 gap-2 px-4 py-2.5">
                <dt className="text-sm text-slate-500">Wilayah</dt>
                <dd className="col-span-2 text-sm font-medium text-slate-900">
                  {editingArrear.wilayah?.name || '-'}
                </dd>
              </div>
              <div className="grid grid-cols-3 gap-2 px-4 py-2.5">
                <dt className="text-sm text-slate-500">Periode</dt>
                <dd className="col-span-2 text-sm font-medium text-slate-900">
                  {editingArrear.period?.label ?? '-'}
                </dd>
              </div>
            </dl>
            <Select
              name="petugas_id"
              label="Petugas"
              value={editForm.petugas_id}
              onChange={(e) => setEditForm({ ...editForm, petugas_id: e.target.value })}
              error={!!editFieldErrors.petugas_id}
              errorMessage={editFieldErrors.petugas_id}
            >
              <option value="">Tidak ada</option>
              {petugasList
                .filter((p) => p.is_active || Number(editForm.petugas_id) === p.id)
                .map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
            </Select>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                name="jumlah_bulan_tunggakan"
                label="Bulan Tunggakan"
                type="number"
                min={1}
                required
                value={editForm.jumlah_bulan_tunggakan}
                onChange={(e) => setEditForm({ ...editForm, jumlah_bulan_tunggakan: e.target.value })}
                error={!!editFieldErrors.jumlah_bulan_tunggakan}
                errorMessage={editFieldErrors.jumlah_bulan_tunggakan}
              />
              <Input
                name="jumlah_tagihan"
                label="Jumlah Tagihan (Rp)"
                type="number"
                min={0}
                value={editForm.jumlah_tagihan}
                onChange={(e) => setEditForm({ ...editForm, jumlah_tagihan: e.target.value })}
                error={!!editFieldErrors.jumlah_tagihan}
                errorMessage={editFieldErrors.jumlah_tagihan}
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="secondary" onClick={() => setEditingArrear(null)}>
                Batal
              </Button>
              <Button type="submit" loading={savingEdit}>
                Simpan
              </Button>
            </div>
          </form>
        )}
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        title="Hapus Data Tunggakan?"
        message={
          deleteTarget
            ? `Data tunggakan untuk ${deleteTarget.nama ?? 'pelanggan'} dengan nomor sambungan ${deleteTarget.no_sambungan ?? '-'} akan dihapus.`
            : ''
        }
        confirmLabel="Hapus"
        loading={deleting}
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />

      <ConfirmDialog
        open={bulkDeleteOpen}
        title="Hapus Data Terpilih?"
        message={
          selected.size > 0
            ? `Anda akan menghapus ${formatNumber(selected.size)} data tunggakan. Data yang sudah dihapus tidak dapat dikembalikan.`
            : ''
        }
        confirmLabel={selected.size > 0 ? `Hapus ${formatNumber(selected.size)} Data` : 'Hapus'}
        loading={bulkDeleting}
        onConfirm={handleBulkDelete}
        onCancel={handleBulkCancel}
      />
    </div>
  )
}

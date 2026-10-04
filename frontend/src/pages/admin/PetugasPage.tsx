import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { createPetugas, getPetugas, togglePetugasStatus, updatePetugas } from '../../services/petugasService'
import { getPeriods } from '../../services/dashboardService'
import { getErrorMessage, getFieldErrors } from '../../lib/api'
import { formatNumber } from '../../lib/format'
import { Card, PageHeader, ProgressBar } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { Input, Select } from '../../components/ui/form'
import { Modal, ConfirmDialog } from '../../components/ui/Modal'
import { FormError } from '../../components/ui/RadioCard'
import { ErrorState, LoadingState, EmptyState } from '../../components/ui/feedback'
import { ActiveBadge } from '../../components/ui/Badge'
import { useToast } from '../../context/ToastContext'
import { IconEdit, IconPlus } from '../../components/ui/icons'
import type { Period, Petugas } from '../../types'

interface PetugasForm {
  name: string
  password: string
  is_active: boolean
}

const emptyForm: PetugasForm = { name: '', password: '', is_active: true }

export function PetugasPage() {
  const toast = useToast()
  const [data, setData] = useState<Petugas[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Petugas | null>(null)
  const [form, setForm] = useState<PetugasForm>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const [toggleTarget, setToggleTarget] = useState<Petugas | null>(null)
  const [toggling, setToggling] = useState(false)
  const [periods, setPeriods] = useState<Period[]>([])
  const [periodId, setPeriodId] = useState<number | undefined>(undefined)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setData(await getPetugas(periodId))
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
        // Default = periode terbaru yang tersedia.
        if (list.length > 0 && periodId === undefined) {
          setPeriodId(list[0].id)
        }
      })
      .catch(() => {
        // daftar tetap usable tanpa data periode
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setFormError('')
    setFieldErrors({})
    setModalOpen(true)
  }

  function openEdit(petugas: Petugas) {
    setEditing(petugas)
    setForm({
      name: petugas.name,
      password: '',
      is_active: petugas.is_active,
    })
    setFormError('')
    setFieldErrors({})
    setModalOpen(true)
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setFormError('')
    setFieldErrors({})
    setSaving(true)
    try {
      if (editing) {
        const payload = {
          name: form.name.trim(),
          password: form.password || undefined,
          is_active: form.is_active,
        }
        const result = await updatePetugas(editing.id, payload)
        toast.success(result.message ?? 'Petugas berhasil diperbarui.')
      } else {
        const result = await createPetugas({
          name: form.name.trim(),
          password: form.password,
          is_active: form.is_active,
        })
        toast.success(result.message ?? 'Petugas berhasil ditambahkan.')
      }
      setModalOpen(false)
      load()
    } catch (err) {
      const fieldError = getFieldErrors(err)
      if (Object.keys(fieldError).length > 0) {
        setFieldErrors(fieldError)
      } else {
        setFormError(getErrorMessage(err))
      }
    } finally {
      setSaving(false)
    }
  }

  async function handleToggle() {
    if (!toggleTarget) return
    setToggling(true)
    try {
      const result = await togglePetugasStatus(toggleTarget.id)
      toast.success(result.message ?? 'Status petugas diperbarui.')
      setToggleTarget(null)
      load()
    } catch (err) {
      toast.error(getErrorMessage(err))
    } finally {
      setToggling(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Petugas"
        description="Kelola akun petugas penagihan — jumlah tugas dihitung per periode"
        action={
          <div className="flex items-center gap-3">
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
            <Button onClick={openCreate}>
              <IconPlus className="h-4 w-4" />
              Tambah Petugas
            </Button>
          </div>
        }
      />

      {error && !loading && <ErrorState message={error} onRetry={load} />}
      {loading && <Card className="p-4"><LoadingState /></Card>}

      {data && !loading && (
        <Card>
          {data.length === 0 ? (
            <EmptyState
              title="Belum ada petugas"
              description="Tambahkan petugas untuk mulai melakukan penagihan."
              action={
                <Button variant="secondary" size="sm" onClick={openCreate}>
                  <IconPlus className="h-4 w-4" />
                  Tambah Petugas
                </Button>
              }
            />
          ) : (
            <>
              <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-xs text-slate-500">
                      <th className="px-4 py-3 font-medium">Nama</th>
                      <th className="px-4 py-3 font-medium">Status</th>
                      <th className="px-4 py-3 font-medium">Jumlah Tugas</th>
                      <th className="px-4 py-3 font-medium">Progress</th>
                      <th className="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {data.map((petugas) => (
                      <tr key={petugas.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 font-medium text-slate-900">{petugas.name}</td>
                        <td className="px-4 py-3">
                          <ActiveBadge active={petugas.is_active} />
                        </td>
                        <td className="px-4 py-3 text-slate-700">
                          {petugas.total_arrears !== undefined
                            ? formatNumber(petugas.total_arrears)
                            : '-'}
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2">
                            <div className="w-28">
                              <ProgressBar value={petugas.progress ?? 0} />
                            </div>
                            <span className="text-xs text-slate-500">{petugas.progress ?? 0}%</span>
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              onClick={() => openEdit(petugas)}
                              className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50"
                            >
                              <IconEdit className="h-4 w-4" />
                              Edit
                            </button>
                            <button
                              type="button"
                              onClick={() => setToggleTarget(petugas)}
                              className={`rounded-lg px-2 py-1 text-xs font-medium ${
                                petugas.is_active
                                  ? 'text-red-600 hover:bg-red-50'
                                  : 'text-emerald-600 hover:bg-emerald-50'
                              }`}
                            >
                              {petugas.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <ul className="divide-y divide-slate-100 md:hidden">
                {data.map((petugas) => (
                  <li key={petugas.id} className="p-4">
                    <div className="flex items-start justify-between gap-3">
<div>
                          <p className="font-medium text-slate-900">{petugas.name}</p>
                          <p className="mt-0.5 text-xs text-slate-500">
                            {petugas.total_arrears !== undefined
                              ? `${formatNumber(petugas.total_arrears)} tugas`
                              : '-'}
                          </p>
                        </div>
                      <ActiveBadge active={petugas.is_active} />
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                      <div className="flex-1">
                        <ProgressBar value={petugas.progress ?? 0} />
                      </div>
                      <span className="text-xs text-slate-500">
                        {petugas.progress ?? 0}% ·{' '}
                        {petugas.total_arrears !== undefined
                          ? `${formatNumber(petugas.total_arrears)} tugas`
                          : '-'}
                      </span>
                    </div>
                    <div className="mt-3 flex gap-2">
                      <button
                        type="button"
                        onClick={() => openEdit(petugas)}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-primary-600 hover:bg-slate-50"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        onClick={() => setToggleTarget(petugas)}
                        className={`rounded-lg border px-3 py-1.5 text-xs font-medium ${
                          petugas.is_active
                            ? 'border-red-200 text-red-600 hover:bg-red-50'
                            : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                        }`}
                      >
                        {petugas.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            </>
          )}
        </Card>
      )}

      <Modal
        open={modalOpen}
        title={editing ? 'Edit Petugas' : 'Tambah Petugas'}
        onClose={() => setModalOpen(false)}
      >
        <FormError>{formError}</FormError>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            name="name"
            label="Nama"
            required
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            error={!!fieldErrors.name}
            errorMessage={fieldErrors.name}
          />
          <Input
            name="password"
            label={editing ? 'Password (kosongkan jika tidak diganti)' : 'Password'}
            type="password"
            required={!editing}
            minLength={6}
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            error={!!fieldErrors.password}
            errorMessage={fieldErrors.password}
          />
          <label className="flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              className="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
            />
            Akun aktif
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" onClick={() => setModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" loading={saving}>
              {editing ? 'Simpan Perubahan' : 'Simpan'}
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toggleTarget}
        title={toggleTarget?.is_active ? 'Nonaktifkan Petugas?' : 'Aktifkan Petugas?'}
        message={
          toggleTarget?.is_active
            ? `${toggleTarget.name} tidak akan dapat login setelah dinonaktifkan.`
            : `${toggleTarget?.name} akan dapat login kembali setelah diaktifkan.`
        }
        confirmLabel={toggleTarget?.is_active ? 'Nonaktifkan' : 'Aktifkan'}
        loading={toggling}
        onConfirm={handleToggle}
        onCancel={() => setToggleTarget(null)}
      />
    </div>
  )
}

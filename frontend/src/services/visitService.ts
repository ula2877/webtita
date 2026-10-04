import { api } from '../lib/api'
import type { Arrear, HasilKunjungan, Paginated, Visit } from '../types'

export interface TasksQuery {
  page?: number
  per_page?: number
  status?: string
  search?: string
  wilayah_id?: number
}

export async function getTasks(params: TasksQuery): Promise<Paginated<Arrear>> {
  const { data } = await api.get<Paginated<Arrear>>('/petugas/tasks', { params })
  return data
}

export async function getTask(id: number): Promise<Arrear> {
  const { data } = await api.get<{ data: Arrear }>(`/petugas/tasks/${id}`)
  return data.data
}

export interface VisitPayload {
  status_kunjungan: HasilKunjungan
  keterangan?: string
  foto_bukti?: File
  tanggal_kunjungan?: string
}

export async function submitVisit(id: number, payload: VisitPayload): Promise<Visit> {
  const formData = new FormData()
  formData.append('status_kunjungan', payload.status_kunjungan)
  if (payload.keterangan) {
    formData.append('keterangan', payload.keterangan)
  }
  if (payload.foto_bukti) {
    formData.append('foto_bukti', payload.foto_bukti)
  }
  if (payload.tanggal_kunjungan) {
    formData.append('tanggal_kunjungan', payload.tanggal_kunjungan)
  }
  const { data } = await api.post<{ data: Visit }>(`/petugas/tasks/${id}/visit`, formData)
  return data.data
}

import { api } from '../lib/api'
import type { Petugas } from '../types'

export async function getPetugas(periodId?: number): Promise<Petugas[]> {
  const { data } = await api.get<{ data: Petugas[] }>('/admin/petugas', {
    params: periodId ? { period_id: periodId } : undefined,
  })
  return data.data
}

export interface PetugasPayload {
  name: string
  password?: string
  is_active?: boolean
}

interface PetugasResponse {
  data: Petugas
  message: string
}

export async function createPetugas(payload: PetugasPayload): Promise<PetugasResponse> {
  const { data } = await api.post<PetugasResponse>('/admin/petugas', payload)
  return data
}

export async function updatePetugas(id: number, payload: PetugasPayload): Promise<PetugasResponse> {
  const { data } = await api.put<PetugasResponse>(`/admin/petugas/${id}`, payload)
  return data
}

export async function togglePetugasStatus(id: number): Promise<PetugasResponse> {
  const { data } = await api.patch<PetugasResponse>(`/admin/petugas/${id}/status`)
  return data
}

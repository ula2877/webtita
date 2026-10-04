import { api } from '../lib/api'
import type { Arrear, AssignResult, BulkDeleteArrearResult, Paginated } from '../types'

export interface ArrearsQuery {
  page?: number
  per_page?: number
  period_id?: number
  petugas_id?: number
  wilayah_id?: number
  status?: string
  hasil_kunjungan?: string
  search?: string
  sort?: string
  order?: 'asc' | 'desc'
}

export async function getArrears(params: ArrearsQuery): Promise<Paginated<Arrear>> {
  const { data } = await api.get<Paginated<Arrear>>('/admin/arrears', { params })
  return data
}

export async function getArrear(id: number): Promise<Arrear> {
  const { data } = await api.get<{ data: Arrear }>(`/admin/arrears/${id}`)
  return data.data
}

export async function assignArrears(arrearIds: number[], petugasId: number): Promise<AssignResult> {
  const { data } = await api.post<AssignResult>('/admin/arrears/assign', {
    arrear_ids: arrearIds,
    petugas_id: petugasId,
  })
  return data
}

export interface UpdateArrearPayload {
  petugas_id?: number | null
  jumlah_bulan_tunggakan: number
  jumlah_tagihan?: number | null
}

export interface ArrearMessageResponse {
  data: Arrear
  message: string
}

export async function updateArrear(
  id: number,
  payload: UpdateArrearPayload,
): Promise<ArrearMessageResponse> {
  const { data } = await api.put<ArrearMessageResponse>(`/admin/arrears/${id}`, payload)
  return data
}

export async function deleteArrear(id: number): Promise<{ message: string }> {
  const { data } = await api.delete<{ message: string }>(`/admin/arrears/${id}`)
  return data
}

export async function deleteArrears(ids: number[]): Promise<BulkDeleteArrearResult> {
  const { data } = await api.delete<BulkDeleteArrearResult>('/admin/arrears/bulk', {
    data: { ids },
  })
  return data
}

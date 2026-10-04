import { api } from '../lib/api'
import type { Paginated, Wilayah } from '../types'

export interface WilayahQuery {
  page?: number
  per_page?: number
  search?: string
  petugas_id?: number
}

export async function getWilayah(params: WilayahQuery): Promise<Paginated<Wilayah>> {
  const { data } = await api.get<Paginated<Wilayah>>('/admin/wilayah', { params })
  return data
}

export async function getMyWilayah(): Promise<Wilayah[]> {
  const { data } = await api.get<{ data: Wilayah[] }>('/petugas/wilayah')
  return data.data
}
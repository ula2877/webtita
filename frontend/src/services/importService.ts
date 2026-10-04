import { api } from '../lib/api'
import type { ImportLog, Paginated, PetugasImportResult, StrukturImportResult } from '../types'

export interface ImportsQuery {
  page?: number
  per_page?: number
  period_id?: number
}

export async function importExcel(
  file: File,
  periodId: number,
  replace: boolean,
): Promise<ImportLog> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('period_id', String(periodId))
  formData.append('replace', replace ? '1' : '0')
  const { data } = await api.post<ImportLog>('/admin/import', formData)
  return data
}

export async function importPetugas(file: File): Promise<PetugasImportResult> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await api.post<PetugasImportResult>('/admin/import/petugas', formData)
  return data
}

export async function importWilayah(file: File): Promise<StrukturImportResult> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await api.post<StrukturImportResult>('/admin/import/wilayah', formData)
  return data
}

export async function getImports(params: ImportsQuery): Promise<Paginated<ImportLog>> {
  const { data } = await api.get<Paginated<ImportLog>>('/admin/imports', { params })
  return data
}

export async function getImport(id: number): Promise<ImportLog> {
  const { data } = await api.get<{ data: ImportLog }>(`/admin/imports/${id}`)
  return data.data
}

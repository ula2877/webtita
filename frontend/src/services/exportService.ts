import { api } from '../lib/api'

export interface ExportQuery {
  period_id?: number
  petugas_id?: number
  status?: string
  hasil_kunjungan?: string
}

export async function exportExcel(params: ExportQuery): Promise<{ blob: Blob; filename: string }> {
  const response = await api.get<Blob>('/admin/export', {
    params,
    responseType: 'blob',
  })
  const header = response.headers['content-disposition'] as string | undefined
  let filename = `hasil_penagihan_${new Date().toISOString().slice(0, 10)}.xlsx`
  const match = header?.match(/filename="?([^";]+)"?/)
  if (match?.[1]) {
    filename = match[1]
  }
  return { blob: response.data, filename }
}

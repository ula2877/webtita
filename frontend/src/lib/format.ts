const MONTHS = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
]

export function formatNumber(value: number): string {
  return new Intl.NumberFormat('id-ID').format(value)
}

export function formatRupiah(value?: number | null): string {
  if (value == null) return '-'
  return `Rp${new Intl.NumberFormat('id-ID').format(value)}`
}

export function formatDate(iso?: string | null): string {
  if (!iso) return '-'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '-'
  return `${String(date.getDate()).padStart(2, '0')} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`
}

export function formatDateTime(iso?: string | null): string {
  if (!iso) return '-'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '-'
  const hh = String(date.getHours()).padStart(2, '0')
  const mm = String(date.getMinutes()).padStart(2, '0')
  return `${formatDate(iso)} ${hh}:${mm}`
}

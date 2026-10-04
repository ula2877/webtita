import type { ArrearStatus, HasilKunjungan, ImportStatus } from '../types'

export const ARREAR_STATUS_LABELS: Record<ArrearStatus, string> = {
  belum_dikunjungi: 'Belum Dikunjungi',
  sudah_dikunjungi: 'Sudah Dikunjungi',
}

export const HASIL_KUNJUNGAN_LABELS: Record<HasilKunjungan, string> = {
  ada_orang: 'Ada Orang',
  rumah_kosong: 'Rumah Kosong',
  tidak_ada_orang: 'Tidak Ada Orang',
  lainnya: 'Lainnya',
}

export const HASIL_KUNJUNGAN_OPTIONS: { value: HasilKunjungan; label: string }[] = [
  { value: 'ada_orang', label: 'Ada Orang' },
  { value: 'rumah_kosong', label: 'Rumah Kosong' },
  { value: 'tidak_ada_orang', label: 'Tidak Ada Orang' },
  { value: 'lainnya', label: 'Lainnya' },
]

export const IMPORT_STATUS_LABELS: Record<ImportStatus, string> = {
  processing: 'Diproses',
  success: 'Berhasil',
  failed: 'Gagal',
  partial: 'Sebagian',
}

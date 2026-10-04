export type Role = 'admin' | 'petugas'

export type ArrearStatus = 'belum_dikunjungi' | 'sudah_dikunjungi'

export type HasilKunjungan = 'ada_orang' | 'rumah_kosong' | 'tidak_ada_orang' | 'lainnya'

export type ImportStatus = 'processing' | 'success' | 'failed' | 'partial'

export interface User {
  id: number
  name: string
  role: Role
  is_active: boolean
}

export interface UserOption {
  id: number
  name: string
  role: Role
  is_active: boolean
}

export interface Petugas extends User {
  total_arrears?: number
  visited_arrears?: number
  progress?: number
}

export interface Period {
  id: number
  year: number
  month: number
  label: string
}

export interface Visit {
  id: number
  arrears_id: number
  petugas_id: number
  petugas_name?: string
  status_kunjungan: HasilKunjungan
  keterangan?: string | null
  foto_bukti?: string | null
  foto_url?: string | null
  visited_at?: string | null
}

export interface Arrear {
  id: number
  no_sambungan: string | null
  nama: string | null
  address?: string | null
  customer_id: number
  jumlah_bulan_tunggakan: number
  jumlah_tagihan?: number | null
  period?: Period | null
  petugas?: Petugas | null
  status: ArrearStatus
  foto_bukti?: string | null
  foto_url?: string | null
  visit?: Visit | null
  wilayah?: Wilayah | null
  created_at?: string | null
  updated_at?: string | null
}

export interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

export interface Paginated<T> {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
    path: string
    links: PaginationLink[]
  }
}

export interface ImportErrorDetail {
  row: number | null
  type: 'error' | 'warning'
  message: string
}

export interface ImportLog {
  id: number
  period?: Period | null
  uploaded_by?: string | null
  file_name: string
  sheet_name?: string | null
  total_rows: number
  success_rows: number
  failed_rows: number
  created_users?: number
  existing_users?: number
  invalid_rows?: number
  status: ImportStatus
  error_details?: ImportErrorDetail[] | null
  created_at?: string | null
}

export interface DashboardResult {
  ada_orang: number
  rumah_kosong: number
  tidak_ada_orang: number
  lainnya: number
}

export interface PetugasProgress {
  petugas: { id: number; name: string }
  total: number
  visited: number
  progress: number
}

export interface AdminDashboard {
  period: Period | null
  total: number
  visited: number
  unvisited: number
  progress: number
  results: DashboardResult
  petugas_progress: PetugasProgress[]
}

export interface WilayahStat {
  id: number
  code: string
  name: string
  customer_count: number
  total_tagihan: number
}

export interface PetugasDashboard {
  period: Period | null
  total: number
  visited: number
  unvisited: number
  progress: number
  recent_arrears: Arrear[]
  total_pelanggan?: number
  total_nominal_tagihan?: number
  wilayah_stats?: WilayahStat[]
}

export interface LoginResponse {
  message: string
  token: string
  user: User
}

export interface AssignResult {
  message: string
  count: number
}

export interface BulkDeleteArrearResult {
  message: string
  deleted_count: number
}

export interface PetugasImportDetail {
  row: number | null
  name: string | null
  status: 'created' | 'existing' | 'invalid'
  note: string
}

export interface PetugasImportResult {
  message: string
  sheet_name: string
  total: number
  created: number
  existing: number
  invalid: number
  details: PetugasImportDetail[]
}

export interface Wilayah {
  id: number
  code: string
  name: string
  petugas?: User | null
}

export interface WilayahImportDetail {
  row: number
  code: string
  nama: string
  penagih: string
  status: 'created' | 'updated' | 'invalid'
  note: string
}

export interface WilayahImportResult {
  message: string
  sheet_name: string
  total: number
  created: number
  updated: number
  invalid: number
  details: WilayahImportDetail[]
}

export interface StrukturImportResult {
  message: string
  petugas: PetugasImportResult
  wilayah: WilayahImportResult
  detail_tagihan: DetailTagihanImportResult
}

export interface DetailTagihanImportDetail {
  row: number
  no_sambungan: string
  nama: string
  wilayah: string
  bulan: number
  tagihan: number
  status: 'created' | 'updated' | 'invalid'
  note: string
}

export interface DetailSheetImportSummary {
  sheet_name: string
  total: number
  pelanggan_created: number
  pelanggan_updated: number
  tagihan_created: number
  tagihan_updated: number
  invalid: number
}

export interface DetailTagihanImportResult {
  message: string
  sheet_name: string
  sheet_count: number
  sheets: DetailSheetImportSummary[]
  total: number
  pelanggan_created: number
  pelanggan_updated: number
  tagihan_created: number
  tagihan_updated: number
  invalid: number
  wilayah_not_found: string[]
  details: DetailTagihanImportDetail[]
}

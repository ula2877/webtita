import type { ReactNode } from 'react'

type Tone = 'gray' | 'green' | 'red' | 'amber' | 'blue'

const toneClasses: Record<Tone, string> = {
  gray: 'bg-slate-100 text-slate-700 border-slate-200',
  green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  red: 'bg-red-50 text-red-700 border-red-200',
  amber: 'bg-amber-50 text-amber-700 border-amber-200',
  blue: 'bg-sky-50 text-sky-700 border-sky-200',
}

export function Badge({ tone = 'gray', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <span
      className={`inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium ${toneClasses[tone]}`}
    >
      {children}
    </span>
  )
}

export function StatusBadge({ status }: { status: string }) {
  if (status === 'sudah_dikunjungi') {
    return <Badge tone="green">Sudah Dikunjungi</Badge>
  }
  return <Badge tone="gray">Belum Dikunjungi</Badge>
}

export function HasilBadge({ hasil }: { hasil: string }) {
  switch (hasil) {
    case 'ada_orang':
      return <Badge tone="green">Ada Orang</Badge>
    case 'rumah_kosong':
      return <Badge tone="amber">Rumah Kosong</Badge>
    case 'tidak_ada_orang':
      return <Badge tone="gray">Tidak Ada Orang</Badge>
    default:
      return <Badge tone="blue">Lainnya</Badge>
  }
}

export function ImportStatusBadge({ status }: { status: string }) {
  switch (status) {
    case 'success':
      return <Badge tone="green">Berhasil</Badge>
    case 'failed':
      return <Badge tone="red">Gagal</Badge>
    case 'partial':
      return <Badge tone="amber">Sebagian</Badge>
    default:
      return <Badge tone="blue">Diproses</Badge>
  }
}

export function ActiveBadge({ active }: { active: boolean }) {
  if (active) {
    return <Badge tone="green">Aktif</Badge>
  }
  return <Badge tone="red">Nonaktif</Badge>
}

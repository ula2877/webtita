import { Link } from 'react-router-dom'
import { Button } from '../components/ui/Button'

export function NotFoundPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-50 px-4 text-center">
      <p className="text-6xl font-bold text-primary-600">404</p>
      <h1 className="text-lg font-semibold text-slate-900">Halaman tidak ditemukan</h1>
      <p className="text-sm text-slate-500">
        Halaman yang Anda cari tidak ada atau telah dipindahkan.
      </p>
      <Link to="/">
        <Button>Kembali ke Beranda</Button>
      </Link>
    </div>
  )
}

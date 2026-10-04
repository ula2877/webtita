import { useEffect, useState } from 'react'
import { api } from '../../lib/api'
import { Modal } from './Modal'

export function usePhotoBlob(path?: string | null): { url: string | null; loading: boolean; error: boolean } {
  const [url, setUrl] = useState<string | null>(null)
  const [loading, setLoading] = useState(!!path)
  const [error, setError] = useState(false)

  useEffect(() => {
    let active = true
    let objectUrl: string | null = null

    async function load() {
      if (!path) {
        setUrl(null)
        setLoading(false)
        return
      }
      setLoading(true)
      setError(false)
      try {
        const response = await api.get<Blob>(`/photos/${path}`, { responseType: 'blob' })
        if (!active) return
        objectUrl = URL.createObjectURL(response.data)
        setUrl(objectUrl)
      } catch {
        if (active) setError(true)
      } finally {
        if (active) setLoading(false)
      }
    }

    load()

    return () => {
      active = false
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }, [path])

  return { url, loading, error }
}

export function ProtectedImage({
  path,
  alt,
  className = '',
  onClick,
}: {
  path?: string | null
  alt: string
  className?: string
  onClick?: () => void
}) {
  const { url, loading, error } = usePhotoBlob(path)

  if (loading) {
    return <div className={`animate-pulse bg-slate-100 ${className}`} />
  }

  if (error || !url) {
    return (
      <div
        className={`flex items-center justify-center rounded-lg bg-slate-100 text-sm text-slate-400 ${className}`}
      >
        Foto tidak tersedia
      </div>
    )
  }

  return <img src={url} alt={alt} className={className} onClick={onClick} />
}

export function PhotoPreview({ path, alt }: { path?: string | null; alt: string }) {
  const [open, setOpen] = useState(false)

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="block w-full max-w-xs overflow-hidden rounded-lg border border-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
      >
        <ProtectedImage path={path} alt={alt} className="h-48 w-full object-cover" />
      </button>
      <Modal open={open} title="Pratinjau Foto" onClose={() => setOpen(false)} size="lg">
        <div className="flex justify-center">
          <ProtectedImage path={path} alt={alt} className="max-h-[70vh] w-auto object-contain" />
        </div>
      </Modal>
    </>
  )
}

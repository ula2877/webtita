import { useEffect, useRef, useState } from 'react'
import type { ChangeEvent, DragEvent, RefObject } from 'react'
import { IconCamera, IconX } from './icons'

const VALID_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg']
const MAX_SIZE = 5 * 1024 * 1024

function validateFile(file: File): string | null {
  if (!VALID_TYPES.includes(file.type)) {
    return 'Format foto harus jpg, jpeg, png, atau webp.'
  }
  if (file.size > MAX_SIZE) {
    return 'Ukuran foto maksimal 5 MB.'
  }
  return null
}

function useFilePreview(file: File | null): string | null {
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  useEffect(() => {
    if (!file) {
      setPreviewUrl(null)
      return
    }
    const url = URL.createObjectURL(file)
    setPreviewUrl(url)
    return () => {
      URL.revokeObjectURL(url)
    }
  }, [file])

  return previewUrl
}

export function FileUpload({
  file,
  onChange,
  inputRef,
  existingPhotoUrl,
}: {
  file: File | null
  onChange: (file: File | null, error: string | null) => void
  inputRef?: RefObject<HTMLInputElement | null>
  existingPhotoUrl?: string | null
}) {
  const internalRef = useRef<HTMLInputElement>(null)
  const [dragging, setDragging] = useState(false)
  const inputElement = inputRef ?? internalRef
  const previewUrl = useFilePreview(file)

  function handleFiles(files: FileList | null) {
    const selected = files?.[0]
    if (!selected) return
    const error = validateFile(selected)
    if (error) {
      onChange(null, error)
      return
    }
    onChange(selected, null)
  }

  function onInputChange(event: ChangeEvent<HTMLInputElement>) {
    handleFiles(event.target.files)
  }

  function onDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault()
    setDragging(false)
    handleFiles(event.dataTransfer.files)
  }

  function clear() {
    if (inputElement.current) {
      inputElement.current.value = ''
    }
    onChange(null, null)
  }

  // Determine what to show: new file preview, existing photo, or empty state
  const hasNewFile = file !== null
  const hasExistingPhoto = !hasNewFile && existingPhotoUrl

  if (hasNewFile) {
    return (
      <div>
        <div className="relative overflow-hidden rounded-lg border border-slate-200">
          {previewUrl ? (
            <img
              src={previewUrl}
              alt="Pratinjau foto bukti"
              className="h-64 w-full object-cover"
            />
          ) : (
            <div className="h-64 w-full animate-pulse bg-slate-100" />
          )}
          <button
            type="button"
            onClick={clear}
            aria-label="Hapus foto"
            className="absolute right-2 top-2 rounded-full bg-slate-900/70 p-1.5 text-white hover:bg-slate-900"
          >
            <IconX className="h-4 w-4" />
          </button>
        </div>
        <button
          type="button"
          onClick={() => inputElement.current?.click()}
          className="mt-2 text-sm font-medium text-primary-600 hover:text-primary-700"
        >
          Ganti Foto
        </button>
        <input
          ref={inputElement}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          capture="environment"
          onChange={onInputChange}
          className="hidden"
        />
      </div>
    )
  }

  if (hasExistingPhoto) {
    return (
      <div>
        <div className="relative overflow-hidden rounded-lg border border-slate-200">
          <img
            src={existingPhotoUrl}
            alt="Foto bukti yang tersimpan"
            className="h-64 w-full object-cover"
          />
          <button
            type="button"
            onClick={clear}
            aria-label="Hapus foto"
            className="absolute right-2 top-2 rounded-full bg-slate-900/70 p-1.5 text-white hover:bg-slate-900"
          >
            <IconX className="h-4 w-4" />
          </button>
        </div>
        <button
          type="button"
          onClick={() => inputElement.current?.click()}
          className="mt-2 text-sm font-medium text-primary-600 hover:text-primary-700"
        >
          Ganti Foto
        </button>
        <input
          ref={inputElement}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          capture="environment"
          onChange={onInputChange}
          className="hidden"
        />
      </div>
    )
  }

  return (
    <div
      onDragOver={(e) => {
        e.preventDefault()
        setDragging(true)
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={onDrop}
      onClick={() => inputElement.current?.click()}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          inputElement.current?.click()
        }
      }}
      className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-10 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 ${
        dragging ? 'border-primary-500 bg-primary-50' : 'border-slate-300 bg-white hover:border-slate-400'
      }`}
    >
      <IconCamera className="h-8 w-8 text-slate-400" />
      <p className="text-sm font-medium text-slate-700">Ambil / Upload Foto</p>
      <p className="text-xs text-slate-500">JPG, PNG, atau WebP · maksimal 5 MB</p>
      <input
        ref={inputElement}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        capture="environment"
        onChange={onInputChange}
        className="hidden"
      />
    </div>
  )
}

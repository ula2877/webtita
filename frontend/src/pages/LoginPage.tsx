import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { getErrorMessage, getFieldErrors } from '../lib/api'
import { getLoginOptions } from '../services/authService'
import { Button } from '../components/ui/Button'
import { Input, Select } from '../components/ui/form'
import { FormError } from '../components/ui/RadioCard'
import type { UserOption } from '../types'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [options, setOptions] = useState<UserOption[]>([])
  const [optionsLoading, setOptionsLoading] = useState(true)
  const [optionsError, setOptionsError] = useState('')
  const [userId, setUserId] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  const loadOptions = useCallback(() => {
    setOptionsLoading(true)
    setOptionsError('')
    getLoginOptions()
      .then(setOptions)
      .catch(() => {
        setOptionsError('Gagal memuat daftar pengguna. Silakan coba lagi.')
      })
      .finally(() => {
        setOptionsLoading(false)
      })
  }, [])

  useEffect(() => {
    loadOptions()
  }, [loadOptions])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setFieldErrors({})
    setSubmitting(true)
    try {
      const user = await login(Number(userId), password)
      navigate(user.role === 'admin' ? '/admin/dashboard' : '/petugas/dashboard', {
        replace: true,
      })
    } catch (err) {
      const fieldError = getFieldErrors(err)
      if (Object.keys(fieldError).length > 0) {
        setFieldErrors(fieldError)
      } else {
        setError(getErrorMessage(err))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-bold text-white">
            T
          </div>
          <h1 className="text-xl font-semibold text-slate-900">Monitoring Penagihan</h1>
          <p className="mt-1 text-sm text-slate-500">Tunggakan PDAM</p>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          {optionsError ? (
            <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              <p>{optionsError}</p>
              <button
                type="button"
                onClick={loadOptions}
                className="mt-2 inline-flex items-center gap-1 font-medium text-red-700 underline hover:text-red-800"
              >
                Coba Lagi
              </button>
            </div>
          ) : (
            <FormError>{error}</FormError>
          )}
          <form onSubmit={handleSubmit} className="mt-2 space-y-4">
            <Select
              name="user_id"
              label="Nama"
              required
              value={userId}
              onChange={(e) => setUserId(e.target.value)}
              error={!!fieldErrors.user_id}
              errorMessage={fieldErrors.user_id}
              disabled={optionsLoading || options.length === 0}
            >
              <option value="">
                {optionsLoading ? 'Memuat daftar pengguna...' : 'Pilih nama Anda'}
              </option>
              {options.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.name}
                </option>
              ))}
            </Select>
            <Input
              name="password"
              label="Password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              error={!!fieldErrors.password}
              errorMessage={fieldErrors.password}
              placeholder="Masukkan password"
            />
            <Button type="submit" loading={submitting} className="w-full" size="lg">
              Login
            </Button>
          </form>
        </div>
      </div>
    </div>
  )
}
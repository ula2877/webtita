import axios, { AxiosError } from 'axios'

const TOKEN_KEY = 'tita_token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      clearToken()
      window.dispatchEvent(new Event('tita:unauthorized'))
    }
    return Promise.reject(error)
  },
)

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}

export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const err = error as AxiosError<ApiErrorPayload>
    if (err.code === 'ECONNABORTED' || err.message.includes('Network Error')) {
      return 'Tidak dapat terhubung ke server. Periksa koneksi Anda.'
    }
    if (err.response) {
      const data = err.response.data
      if (err.response.status === 401) {
        return data?.message || 'Sesi berakhir. Silakan login kembali.'
      }
      if (err.response.status === 403) {
        return data?.message || 'Anda tidak memiliki akses ke halaman ini.'
      }
      if (err.response.status === 422 && data?.errors) {
        const firstKey = Object.keys(data.errors)[0]
        if (firstKey) {
          return data.errors[firstKey][0]
        }
      }
      if (data?.message) {
        return data.message
      }
      if (err.response.status === 500) {
        return 'Terjadi kesalahan pada server. Silakan coba lagi.'
      }
      if (err.response.status === 404) {
        return 'Data tidak ditemukan.'
      }
    }
  }
  return 'Terjadi kesalahan. Silakan coba lagi.'
}

export function getFieldErrors(error: unknown): Record<string, string> {
  if (axios.isAxiosError(error)) {
    const err = error as AxiosError<ApiErrorPayload>
    const data = err.response?.data
    if (data?.errors) {
      const result: Record<string, string> = {}
      for (const [key, messages] of Object.entries(data.errors)) {
        result[key] = messages[0]
      }
      return result
    }
  }
  return {}
}

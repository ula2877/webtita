import { api } from '../lib/api'
import type { LoginResponse, User, UserOption } from '../types'

export async function getLoginOptions(): Promise<UserOption[]> {
  const { data } = await api.get<{ data: UserOption[] }>('/login-options')
  return data.data
}

export async function login(userId: number, password: string): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/login', { user_id: userId, password })
  return data
}

export async function logout(): Promise<void> {
  await api.post('/logout')
}

export async function me(): Promise<User> {
  const { data } = await api.get<{ data: User }>('/me')
  return data.data
}

export async function updateProfile(name: string): Promise<User> {
  const { data } = await api.put<{ message: string; user: User }>('/profile', { name })
  return data.user
}

export async function updatePassword(currentPassword: string, newPassword: string): Promise<{ message: string }> {
  const { data } = await api.put<{ message: string }>('/profile/password', {
    current_password: currentPassword,
    password: newPassword,
    password_confirmation: newPassword,
  })
  return data
}
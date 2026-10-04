import { api } from '../lib/api'
import type { AdminDashboard, Period, PetugasDashboard } from '../types'

export async function getPeriods(): Promise<Period[]> {
  const { data } = await api.get<{ data: Period[] }>('/periods')
  return data.data
}

export function latestPeriod(periods: Period[]): Period | undefined {
  return periods.find((_, index) => index === 0) ?? periods[0]
}

export async function getAdminDashboard(periodId?: number): Promise<AdminDashboard> {
  const { data } = await api.get<AdminDashboard>('/admin/dashboard', {
    params: periodId ? { period_id: periodId } : undefined,
  })
  return data
}

export async function getPetugasDashboard(periodId?: number): Promise<PetugasDashboard> {
  const { data } = await api.get<PetugasDashboard>('/petugas/dashboard', {
    params: periodId ? { period_id: periodId } : undefined,
  })
  return data
}

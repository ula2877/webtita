import { Navigate, useLocation } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from '../context/AuthContext'
import { Spinner } from './ui/feedback'
import type { Role } from '../types'

function roleHome(role: Role): string {
  return role === 'admin' ? '/admin/dashboard' : '/petugas/dashboard'
}

export function ProtectedRoute({
  role,
  children,
}: {
  role: Role
  children: ReactNode
}) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Spinner className="h-7 w-7" />
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />
  }

  if (user.role !== role) {
    return <Navigate to={roleHome(user.role)} replace />
  }

  return children
}

export function PublicOnlyRoute({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Spinner className="h-7 w-7" />
      </div>
    )
  }

  if (user) {
    return <Navigate to={roleHome(user.role)} replace />
  }

  return children
}

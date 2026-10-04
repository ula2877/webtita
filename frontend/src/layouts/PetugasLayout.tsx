import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { getErrorMessage } from '../lib/api'
import {
  IconDashboard,
  IconList,
  IconLogout,
  IconMenu,
  IconUser,
  IconX,
} from '../components/ui/icons'
import type { ReactNode } from 'react'

const NAV_ITEMS = [
  { to: '/petugas/dashboard', label: 'Dashboard', icon: IconDashboard },
  { to: '/petugas/tasks', label: 'Tugas Penagihan', icon: IconList },
]

function NavLinkItem({ to, label, icon, onNavigate }: { to: string; label: string; icon: ReactNode; onNavigate?: () => void }) {
  return (
    <NavLink
      to={to}
      onClick={onNavigate}
      className={({ isActive }) =>
        `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
          isActive
            ? 'bg-primary-50 text-primary-700'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        }`
      }
    >
      {icon}
      {label}
    </NavLink>
  )
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  const { user, logout } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()

  async function handleLogout() {
    try {
      await logout()
      toast.success('Berhasil logout.')
      navigate('/login')
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2 px-5 py-5">
        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 font-bold text-white">
          T
        </div>
        <div>
          <p className="text-sm font-semibold text-slate-900">PDAM Monitoring</p>
          <p className="text-xs text-slate-500">Petugas</p>
        </div>
      </div>
      <nav className="flex-1 space-y-1 px-3 py-2">
        {NAV_ITEMS.map((item) => (
          <NavLinkItem
            key={item.to}
            to={item.to}
            label={item.label}
            icon={<item.icon className="h-5 w-5" />}
            onNavigate={onNavigate}
          />
        ))}
      </nav>
      <div className="space-y-1 border-t border-slate-100 px-3 py-3">
        <NavLinkItem
          to="/petugas/profile"
          label="Profile"
          icon={<IconUser className="h-5 w-5" />}
          onNavigate={onNavigate}
        />
        <button
          type="button"
          onClick={handleLogout}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
        >
          <IconLogout className="h-5 w-5" />
          Logout
        </button>
      </div>
      {user && (
        <div className="border-t border-slate-100 px-5 py-3">
          <p className="truncate text-sm font-medium text-slate-700">{user.name}</p>
        </div>
      )}
    </div>
  )
}

export function PetugasLayout() {
  const [drawerOpen, setDrawerOpen] = useState(false)
  const { user } = useAuth()

  return (
    <div className="min-h-screen bg-slate-50">
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-slate-200 bg-white lg:block">
        <SidebarContent />
      </aside>

      {drawerOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-slate-900/50" onClick={() => setDrawerOpen(false)} />
          <aside className="absolute inset-y-0 left-0 w-64 bg-white shadow-lg">
            <button
              type="button"
              onClick={() => setDrawerOpen(false)}
              aria-label="Tutup menu"
              className="absolute right-3 top-4 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            >
              <IconX className="h-5 w-5" />
            </button>
            <SidebarContent onNavigate={() => setDrawerOpen(false)} />
          </aside>
        </div>
      )}

      <div className="lg:pl-64">
        <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-8">
          <button
            type="button"
            onClick={() => setDrawerOpen(true)}
            aria-label="Buka menu"
            className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
          >
            <IconMenu className="h-5 w-5" />
          </button>
          <div className="hidden text-sm text-slate-500 lg:block">
            Petugas · Monitoring Penagihan Tunggakan
          </div>
          <div className="flex items-center gap-3">
            <div className="hidden text-right sm:block">
              <p className="text-sm font-medium text-slate-700">{user?.name}</p>
              <p className="text-xs text-slate-500">Petugas</p>
            </div>
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
              {user?.name?.charAt(0).toUpperCase()}
            </div>
          </div>
        </header>
        <main className="px-4 py-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

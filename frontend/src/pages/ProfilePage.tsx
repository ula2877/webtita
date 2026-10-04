import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { getErrorMessage, getFieldErrors } from '../lib/api'
import { updateProfile, updatePassword } from '../services/authService'
import { Modal } from '../components/ui/Modal'
import { Button } from '../components/ui/Button'
import { Input, PasswordInput } from '../components/ui/form'
import { Card, PageHeader } from '../components/ui/surfaces'
import { Badge } from '../components/ui/Badge'
import { IconEdit, IconLock } from '../components/ui/icons'

export function ProfilePage() {
  const { user, updateUser } = useAuth()
  const toast = useToast()

  // Edit Name Modal
  const [editNameOpen, setEditNameOpen] = useState(false)
  const [editNameValue, setEditNameValue] = useState('')
  const [editNameError, setEditNameError] = useState('')
  const [editNameLoading, setEditNameLoading] = useState(false)

  // Change Password Modal
  const [changePasswordOpen, setChangePasswordOpen] = useState(false)
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [currentPasswordError, setCurrentPasswordError] = useState('')
  const [newPasswordError, setNewPasswordError] = useState('')
  const [confirmPasswordError, setConfirmPasswordError] = useState('')
  const [changePasswordLoading, setChangePasswordLoading] = useState(false)
  const [showCurrentPassword, setShowCurrentPassword] = useState(false)
  const [showNewPassword, setShowNewPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)

  function openEditName() {
    setEditNameValue(user?.name || '')
    setEditNameError('')
    setEditNameOpen(true)
  }

  function closeEditName() {
    setEditNameOpen(false)
    setEditNameValue('')
    setEditNameError('')
  }

  async function handleEditNameSubmit(e: React.FormEvent) {
    e.preventDefault()
    setEditNameError('')

    const trimmedName = editNameValue.trim()
    if (!trimmedName) {
      setEditNameError('Nama wajib diisi.')
      return
    }

    setEditNameLoading(true)
    try {
      const updatedUser = await updateProfile(trimmedName)
      updateUser(updatedUser)
      toast.success('Nama berhasil diperbarui.')
      closeEditName()
    } catch (error) {
      const fieldErrors = getFieldErrors(error)
      if (fieldErrors.name) {
        setEditNameError(fieldErrors.name)
      } else {
        toast.error(getErrorMessage(error))
      }
    } finally {
      setEditNameLoading(false)
    }
  }

  function openChangePassword() {
    setCurrentPassword('')
    setNewPassword('')
    setConfirmPassword('')
    setCurrentPasswordError('')
    setNewPasswordError('')
    setConfirmPasswordError('')
    setShowCurrentPassword(false)
    setShowNewPassword(false)
    setShowConfirmPassword(false)
    setChangePasswordOpen(true)
  }

  function closeChangePassword() {
    setChangePasswordOpen(false)
    setCurrentPassword('')
    setNewPassword('')
    setConfirmPassword('')
    setCurrentPasswordError('')
    setNewPasswordError('')
    setConfirmPasswordError('')
  }

  async function handleChangePasswordSubmit(e: React.FormEvent) {
    e.preventDefault()
    setCurrentPasswordError('')
    setNewPasswordError('')
    setConfirmPasswordError('')

    if (!currentPassword) {
      setCurrentPasswordError('Password saat ini wajib diisi.')
      return
    }

    if (!newPassword) {
      setNewPasswordError('Password baru wajib diisi.')
      return
    }

    if (newPassword.length < 8) {
      setNewPasswordError('Password baru minimal 8 karakter.')
      return
    }

    if (newPassword !== confirmPassword) {
      setConfirmPasswordError('Konfirmasi password tidak cocok.')
      return
    }

    setChangePasswordLoading(true)
    try {
      await updatePassword(currentPassword, newPassword)
      toast.success('Password berhasil diperbarui.')
      closeChangePassword()
    } catch (error) {
      const fieldErrors = getFieldErrors(error)
      if (fieldErrors.current_password) {
        setCurrentPasswordError(fieldErrors.current_password)
      } else if (fieldErrors.password) {
        setNewPasswordError(fieldErrors.password)
      } else {
        toast.error(getErrorMessage(error))
      }
    } finally {
      setChangePasswordLoading(false)
    }
  }

  return (
    <div>
      <PageHeader title="Profile" description="Informasi akun Anda" />
      <Card className="max-w-xl p-6">
        <div className="mb-5 flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-2xl font-semibold text-primary-700">
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div>
            <h3 className="text-lg font-semibold text-slate-900">{user?.name}</h3>
            <p className="text-sm text-slate-500">{user?.role === 'admin' ? 'Admin' : 'Petugas'}</p>
          </div>
        </div>
        <dl className="divide-y divide-slate-100">
          <div className="grid grid-cols-3 gap-2 py-3">
            <dt className="text-sm text-slate-500">Nama</dt>
            <dd className="col-span-2 text-sm font-medium text-slate-900">{user?.name}</dd>
          </div>
          <div className="grid grid-cols-3 gap-2 py-3">
            <dt className="text-sm text-slate-500">Role</dt>
            <dd className="col-span-2">
              <Badge tone="blue">{user?.role === 'admin' ? 'Admin' : 'Petugas'}</Badge>
            </dd>
          </div>
          <div className="grid grid-cols-3 gap-2 py-3">
            <dt className="text-sm text-slate-500">Status</dt>
            <dd className="col-span-2">
              <Badge tone={user?.is_active ? 'green' : 'red'}>
                {user?.is_active ? 'Aktif' : 'Nonaktif'}
              </Badge>
            </dd>
          </div>
        </dl>

        <div className="mt-6 flex flex-wrap gap-3">
          <Button variant="secondary" size="md" onClick={openEditName}>
            <IconEdit className="h-4 w-4" />
            Ubah Nama
          </Button>
          <Button variant="secondary" size="md" onClick={openChangePassword}>
            <IconLock className="h-4 w-4" />
            Ubah Password
          </Button>
        </div>
      </Card>

      {/* Edit Name Modal */}
      <Modal open={editNameOpen} title="Ubah Nama" onClose={closeEditName} size="sm">
        <form onSubmit={handleEditNameSubmit}>
          <Input
            name="name"
            value={editNameValue}
            onChange={(e) => setEditNameValue(e.target.value)}
            error={!!editNameError}
            errorMessage={editNameError}
            placeholder="Masukkan nama baru"
            autoFocus
          />
          <div className="mt-5 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={closeEditName} disabled={editNameLoading}>
              Batal
            </Button>
            <Button type="submit" loading={editNameLoading}>
              Simpan
            </Button>
          </div>
        </form>
      </Modal>

      {/* Change Password Modal */}
      <Modal open={changePasswordOpen} title="Ubah Password" onClose={closeChangePassword} size="sm">
        <form onSubmit={handleChangePasswordSubmit}>
          <PasswordInput
            name="current_password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            error={!!currentPasswordError}
            errorMessage={currentPasswordError}
            placeholder="Masukkan password saat ini"
            autoComplete="current-password"
            autoFocus
            showPassword={showCurrentPassword}
            onToggleShowPassword={() => setShowCurrentPassword(!showCurrentPassword)}
          />

          <PasswordInput
            name="password"
            value={newPassword}
            onChange={(e) => setNewPassword(e.target.value)}
            error={!!newPasswordError}
            errorMessage={newPasswordError}
            placeholder="Masukkan password baru"
            autoComplete="new-password"
            className="mt-4"
            showPassword={showNewPassword}
            onToggleShowPassword={() => setShowNewPassword(!showNewPassword)}
          />

          <PasswordInput
            name="password_confirmation"
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
            error={!!confirmPasswordError}
            errorMessage={confirmPasswordError}
            placeholder="Konfirmasi password baru"
            autoComplete="new-password"
            className="mt-4"
            showPassword={showConfirmPassword}
            onToggleShowPassword={() => setShowConfirmPassword(!showConfirmPassword)}
          />

          <div className="mt-5 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={closeChangePassword} disabled={changePasswordLoading}>
              Batal
            </Button>
            <Button type="submit" loading={changePasswordLoading}>
              Simpan
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}

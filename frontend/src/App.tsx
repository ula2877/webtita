import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './context/AuthContext'
import { ProtectedRoute, PublicOnlyRoute } from './components/ProtectedRoute'
import { Spinner } from './components/ui/feedback'
import { AdminLayout } from './layouts/AdminLayout'
import { PetugasLayout } from './layouts/PetugasLayout'
import { LoginPage } from './pages/LoginPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { ProfilePage } from './pages/ProfilePage'
import { AdminDashboardPage } from './pages/admin/DashboardPage'
import { ArrearsPage } from './pages/admin/ArrearsPage'
import { ArrearDetailPage } from './pages/admin/ArrearDetailPage'
import { ImportPage } from './pages/admin/ImportPage'
import { ImportHistoryPage } from './pages/admin/ImportHistoryPage'
import { ImportDetailPage } from './pages/admin/ImportDetailPage'
import { PetugasPage } from './pages/admin/PetugasPage'
import { PetugasReviewPage } from './pages/admin/PetugasReviewPage'
import { WilayahPage } from './pages/admin/WilayahPage'
import { PetugasDashboardPage } from './pages/petugas/DashboardPage'
import { TasksPage } from './pages/petugas/TasksPage'
import { TaskDetailPage } from './pages/petugas/TaskDetailPage'
import { VisitPage } from './pages/petugas/VisitPage'

function RootRedirect() {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Spinner className="h-7 w-7" />
      </div>
    )
  }

  if (user) {
    return <Navigate to={user.role === 'admin' ? '/admin/dashboard' : '/petugas/dashboard'} replace />
  }

  return <Navigate to="/login" replace />
}

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route
          path="/login"
          element={
            <PublicOnlyRoute>
              <LoginPage />
            </PublicOnlyRoute>
          }
        />

        <Route
          path="/admin"
          element={
            <ProtectedRoute role="admin">
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="/admin/dashboard" replace />} />
          <Route path="dashboard" element={<AdminDashboardPage />} />
          <Route path="arrears" element={<ArrearsPage />} />
          <Route path="arrears/:id" element={<ArrearDetailPage />} />
          <Route path="import" element={<ImportPage />} />
          <Route path="imports" element={<ImportHistoryPage />} />
          <Route path="imports/:id" element={<ImportDetailPage />} />
          <Route path="petugas" element={<PetugasPage />} />
          <Route path="petugas/:petugasId/review" element={<PetugasReviewPage />} />
          <Route path="wilayah" element={<WilayahPage />} />
          <Route path="profile" element={<ProfilePage />} />
        </Route>

        <Route
          path="/petugas"
          element={
            <ProtectedRoute role="petugas">
              <PetugasLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="/petugas/dashboard" replace />} />
          <Route path="dashboard" element={<PetugasDashboardPage />} />
          <Route path="tasks" element={<TasksPage />} />
          <Route path="tasks/:id" element={<TaskDetailPage />} />
          <Route path="tasks/:id/visit" element={<VisitPage />} />
          <Route path="profile" element={<ProfilePage />} />
        </Route>

        <Route path="/" element={<RootRedirect />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  )
}

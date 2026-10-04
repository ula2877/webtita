import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, getErrorMessage } from '../../lib/api'
import { formatNumber } from '../../lib/format'
import { PageHeader, StatCard, Card } from '../../components/ui/surfaces'
import { Button } from '../../components/ui/Button'
import { ErrorState, LoadingState } from '../../components/ui/feedback'
import {
  IconChevronLeft,
  IconList,
  IconCamera,
  IconCheck,
  IconAlert,
  IconUser,
  IconHome,
  IconChevronDown,
} from '../../components/ui/icons'

interface PetugasReviewKPI {
  label: string
  value: number
  icon: React.ReactNode
  accent: 'blue' | 'green' | 'amber' | 'gray' | 'red'
  description?: string
}

type SortField = 'wilayah_nama' | 'jumlah_surat' | 'bukti_foto' | 'surat_diterima' | 'tidak_ada_orang' | 'rumah_kosong' | 'lainnya' | 'sisa_surat'
type SortDirection = 'asc' | 'desc'

export function PetugasReviewPage() {
  const { petugasId } = useParams<{ petugasId: string }>()

  const [data, setData] = useState<{
    petugas: { id: number; name: string }
    period: { id: number; label: string } | null
    kpi: {
      total_tagihan: number
      dengan_foto: number
      ada_orang: number
      tidak_ada_orang: number
      rumah_kosong: number
      lainnya: number
      belum_dikunjungi: number
    }
    by_area: Array<{
      wilayah_nama: string
      jumlah_surat: number
      bukti_foto: number
      surat_diterima: number
      tidak_ada_orang: number
      rumah_kosong: number
      lainnya: number
      sisa_surat: number
    }>
  } | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [sortField, setSortField] = useState<SortField>('wilayah_nama')
  const [sortDirection, setSortDirection] = useState<SortDirection>('asc')

  const load = useCallback(async () => {
    if (!petugasId) return
    setLoading(true)
    setError('')
    try {
      const response = await api.get(`/admin/dashboard/petugas/${petugasId}/review`)
      setData(response.data)
    } catch (err) {
      setError(getErrorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [petugasId])

  useEffect(() => {
    load()
  }, [load])

  function handleRetry() {
    load()
  }

  function handleSort(field: SortField) {
    setSortField(field)
    setSortDirection(prev => prev === 'asc' ? 'desc' : 'asc')
  }

  const sortedByArea = useMemo(() => {
    if (!data?.by_area) return []
    const sorted = [...data.by_area]
    sorted.sort((a, b) => {
      const aVal = a[sortField]
      const bVal = b[sortField]
      if (typeof aVal === 'string' && typeof bVal === 'string') {
        return sortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal)
      }
      return sortDirection === 'asc' ? (aVal as number) - (bVal as number) : (bVal as number) - (aVal as number)
    })
    return sorted
  }, [data?.by_area, sortField, sortDirection])

  const kpiCards: PetugasReviewKPI[] = [
    {
      label: 'Jumlah Tagihan',
      value: data?.kpi.total_tagihan ?? 0,
      icon: <IconList className="h-5 w-5" />,
      accent: 'blue',
      description: 'Seluruh tugas tagihan',
    },
    {
      label: 'Data dengan Foto',
      value: data?.kpi.dengan_foto ?? 0,
      icon: <IconCamera className="h-5 w-5" />,
      accent: 'amber',
      description: 'Bukti kunjungan',
    },
    {
      label: 'Surat Diterima',
      value: data?.kpi.ada_orang ?? 0,
      icon: <IconCheck className="h-5 w-5" />,
      accent: 'green',
      description: 'Ada Orang',
    },
    {
      label: 'Tidak Ada Orang',
      value: data?.kpi.tidak_ada_orang ?? 0,
      icon: <IconAlert className="h-5 w-5" />,
      accent: 'red',
      description: 'Tidak bertemu',
    },
    {
      label: 'Rumah Kosong',
      value: data?.kpi.rumah_kosong ?? 0,
      icon: <IconHome className="h-5 w-5" />,
      accent: 'amber',
      description: 'Rumah kosong',
    },
    {
      label: 'Keterangan Lainnya',
      value: data?.kpi.lainnya ?? 0,
      icon: <IconAlert className="h-5 w-5" />,
      accent: 'blue',
      description: 'Hasil lainnya',
    },
    {
      label: 'Belum Dikunjungi',
      value: data?.kpi.belum_dikunjungi ?? 0,
      icon: <IconUser className="h-5 w-5" />,
      accent: 'gray',
      description: 'Belum memiliki kunjungan',
    },
  ]

  if (!data && loading) {
    return <LoadingState />
  }

  if (error && !loading) {
    return <ErrorState message={error} onRetry={handleRetry} />
  }

  if (!data) {
    return <ErrorState message="Data tidak ditemukan" onRetry={handleRetry} />
  }

  return (
    <div>
      <PageHeader
        title="Review Pekerjaan Petugas"
        description={data.petugas.name + (data.period ? ` · ${data.period.label}` : '')}
        action={
          <Link to="/admin/dashboard">
            <Button variant="ghost" size="sm">
              <IconChevronLeft className="h-4 w-4" />
              Kembali
            </Button>
          </Link>
        }
      />

      {error && !loading && <ErrorState message={error} onRetry={handleRetry} />}
      {loading && <LoadingState />}

      {data && !loading && (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
            {kpiCards.map((kpi) => (
              <StatCard
                key={kpi.label}
                label={kpi.label}
                value={formatNumber(kpi.value)}
                icon={kpi.icon}
                accent={kpi.accent}
                hint={kpi.description}
              />
            ))}
          </div>

          {/* Detail Pekerjaan per Wilayah */}
          <Card className="p-5">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-sm font-semibold text-slate-900">Detail Pekerjaan per Wilayah</h3>
              <p className="text-xs text-slate-500">Rekap hasil penagihan berdasarkan alamat/wilayah</p>
            </div>
            {data.by_area && data.by_area.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1100px] text-left text-sm border-collapse table-fixed">
                  <thead>
                    <tr className="border-b border-slate-200 text-xs text-slate-500">
                      <th className="px-3 py-3 font-medium cursor-pointer select-none w-[25%]" onClick={() => handleSort('wilayah_nama')}>
                        <div className="flex items-center gap-1 whitespace-normal leading-tight">
                          Wilayah Penagihan
                          {sortField === 'wilayah_nama' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[10%]" onClick={() => handleSort('jumlah_surat')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Jumlah Surat
                          {sortField === 'jumlah_surat' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[9%]" onClick={() => handleSort('bukti_foto')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Bukti Foto
                          {sortField === 'bukti_foto' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[11%]" onClick={() => handleSort('surat_diterima')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Surat Diterima
                          {sortField === 'surat_diterima' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[12%]" onClick={() => handleSort('tidak_ada_orang')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Tidak Ada Orang
                          {sortField === 'tidak_ada_orang' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[11%]" onClick={() => handleSort('rumah_kosong')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Rumah Kosong
                          {sortField === 'rumah_kosong' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[13%]" onClick={() => handleSort('lainnya')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Keterangan Lainnya
                          {sortField === 'lainnya' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                      <th className="px-3 py-3 font-medium text-center cursor-pointer select-none w-[9%]" onClick={() => handleSort('sisa_surat')}>
                        <div className="flex items-center justify-center gap-1 whitespace-normal leading-tight">
                          Sisa Surat
                          {sortField === 'sisa_surat' && (
                            <span className="ml-1">
                              {sortDirection === 'asc' ? (
                                <span className="inline-block transform rotate-180">
                                  <IconChevronDown className="h-3.5 w-3.5" />
                                </span>
                              ) : (
                                <IconChevronDown className="h-3.5 w-3.5" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {sortedByArea.map((item, index) => (
                      <tr key={index} className="hover:bg-slate-50">
                        <td className="px-3 py-3 text-slate-700 font-medium break-words">
                          {item.wilayah_nama}
                        </td>
                        <td className="px-3 py-3 text-center text-slate-700 font-medium">{formatNumber(item.jumlah_surat)}</td>
                        <td className="px-3 py-3 text-center text-slate-700">{formatNumber(item.bukti_foto)}</td>
                        <td className="px-3 py-3 text-center text-slate-700">{formatNumber(item.surat_diterima)}</td>
                        <td className="px-3 py-3 text-center text-slate-700">{formatNumber(item.tidak_ada_orang)}</td>
                        <td className="px-3 py-3 text-center text-slate-700">{formatNumber(item.rumah_kosong)}</td>
                        <td className="px-3 py-3 text-center text-slate-700">{formatNumber(item.lainnya)}</td>
                        <td className="px-3 py-3 text-center text-slate-700 font-medium text-amber-600">{formatNumber(item.sisa_surat)}</td>
                      </tr>
                    ))}
                    <tr className="bg-slate-50 font-semibold">
                      <td className="px-3 py-3 text-slate-900">TOTAL</td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.jumlah_surat, 0))}
                      </td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.bukti_foto, 0))}
                      </td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.surat_diterima, 0))}
                      </td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.tidak_ada_orang, 0))}
                      </td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.rumah_kosong, 0))}
                      </td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.lainnya, 0))}
                      </td>
                      <td className="px-3 py-3 text-center text-slate-900">
                        {formatNumber(sortedByArea.reduce((sum, item) => sum + item.sisa_surat, 0))}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-center text-sm text-slate-500 py-8">Belum ada data pekerjaan untuk periode ini.</p>
            )}
          </Card>
        </div>
      )}
    </div>
  )
}

interface PetugasReviewKPI {
  label: string
  value: number
  icon: React.ReactNode
  accent: 'blue' | 'green' | 'amber' | 'gray' | 'red'
  description?: string
}

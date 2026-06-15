'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { Filter, RefreshCw, Users, FileText } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  fetchStaffList,
  fetchStaffReport,
  type StaffDetailRow,
  type StaffDetailsPagination,
  type StaffListOption,
  type StaffSummaryRow,
} from '@/services/reports/api'

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'Staff Performance Report',
  subtitle: 'Track staff workflow performance and efficiency',
  filters: {
    dateFrom: 'Date From',
    dateTo: 'Date To',
    staffMember: 'Staff Member',
    allStaff: 'All Staff',
    apply: 'Apply Filters',
    refresh: 'Refresh Data',
  },
  summary: {
    title: 'Staff Performance Summary',
    staffName: 'Staff Name',
    username: 'Username',
    itemsProcessed: 'Items Processed',
    contribution: 'Percentage Contribution',
    share: 'Share',
    noData: 'No performance data found for selected period.',
    total: 'Total',
    items: 'items',
  },
  details: {
    title: 'Processing Activity Details',
    staffName: 'Staff Name',
    username: 'Username',
    orderItem: 'Order / Item',
    order: 'Order',
    item: 'Item',
    metaKey: 'Meta Key',
    processedAt: 'Processed At',
    noData: 'No activity details found.',
  },
  loading: 'Loading report data...',
  failedLoadList: 'Failed to load staff list',
  failedLoadReport: 'Failed to load report data',
}

function getDefaultDateRange() {
  const today = new Date()
  const thirtyDaysAgo = new Date()
  thirtyDaysAgo.setDate(today.getDate() - 30)

  return {
    date_from: thirtyDaysAgo.toISOString().split('T')[0] || '',
    date_to: today.toISOString().split('T')[0] || '',
    staff_id: '',
  }
}

function formatDateTime(dateString?: string | null) {
  if (!dateString) return 'N/A'
  try {
    return new Date(dateString).toLocaleString('en-US')
  } catch {
    return 'N/A'
  }
}

function ContributionBar({ value }: { value: number }) {
  const safeValue = Math.max(0, Math.min(100, value))

  return (
    <div className='h-2 overflow-hidden rounded-full bg-muted'>
      <div
        className='h-full rounded-full bg-primary transition-[width]'
        style={{ width: `${safeValue}%` }}
      />
    </div>
  )
}

export function LemiexStaffReportPage() {
  const { messages } = useI18n()
  const m = messages.staffReportPage ?? fallbackMessages
  const [loading, setLoading] = useState(false)
  const [staffList, setStaffList] = useState<StaffListOption[]>([])
  const [summaryData, setSummaryData] = useState<StaffSummaryRow[]>([])
  const [detailsData, setDetailsData] = useState<StaffDetailRow[]>([])
  const [totalProcessed, setTotalProcessed] = useState(0)
  const [filters, setFilters] = useState(getDefaultDateRange)
  const [pagination, setPagination] = useState<StaffDetailsPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
  })

  useEffect(() => {
    let active = true

    async function loadStaff() {
      try {
        const next = await fetchStaffList()
        if (!active) return
        setStaffList(next)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.failedLoadList)
      }
    }

    void loadStaff()

    return () => {
      active = false
    }
  }, [m.failedLoadList])

  const loadReport = useCallback(
    async (
      nextPage = pagination.current_page,
      nextPerPage = pagination.per_page
    ) => {
      setLoading(true)
      try {
        const result = await fetchStaffReport({
          ...filters,
          page: nextPage,
          per_page: nextPerPage,
        })
        setSummaryData(result.summary)
        setDetailsData(result.details)
        setTotalProcessed(result.totalProcessed)
        setPagination(result.pagination)
      } catch (error) {
        toast.error(error instanceof Error ? error.message : m.failedLoadReport)
      } finally {
        setLoading(false)
      }
    },
    [filters, m.failedLoadReport, pagination.current_page, pagination.per_page]
  )

  useEffect(() => {
    void loadReport(pagination.current_page, pagination.per_page)
  }, [loadReport, pagination.current_page, pagination.per_page])

  const detailColumns = useMemo<ColumnDef<StaffDetailRow>[]>(
    () => [
      {
        id: 'staff_name',
        header: m.details.staffName,
        cell: ({ row }) => <strong>{row.original.staff_name || 'N/A'}</strong>,
        meta: {
          thClassName: 'min-w-[160px]',
          tdClassName: 'min-w-[160px]',
        },
      },
      {
        id: 'username',
        header: m.details.username,
        cell: ({ row }) => row.original.username || 'N/A',
        meta: {
          thClassName: 'min-w-[130px]',
          tdClassName: 'min-w-[130px]',
        },
      },
      {
        id: 'order_item',
        header: m.details.orderItem,
        cell: ({ row }) => (
          <div className='space-y-1 text-sm'>
            <div>
              <span className='text-muted-foreground'>{m.details.order}:</span>{' '}
              <span className='font-mono font-semibold'>#{row.original.order_id || 'N/A'}</span>
            </div>
            <div>
              <span className='text-muted-foreground'>{m.details.item}:</span>{' '}
              <span className='font-mono'>#{row.original.item_id || 'N/A'}</span>
            </div>
          </div>
        ),
        meta: {
          thClassName: 'min-w-[170px]',
          tdClassName: 'min-w-[170px]',
        },
      },
      {
        id: 'meta_key',
        header: m.details.metaKey,
        cell: ({ row }) => (
          <span className='rounded-[6px] border bg-muted px-2 py-1 text-xs font-semibold uppercase'>
            {row.original.meta_key || 'N/A'}
          </span>
        ),
        meta: {
          thClassName: 'min-w-[160px]',
          tdClassName: 'min-w-[160px]',
        },
      },
      {
        id: 'processed_at',
        header: m.details.processedAt,
        cell: ({ row }) => formatDateTime(row.original.processed_at),
        meta: {
          thClassName: 'min-w-[190px]',
          tdClassName: 'min-w-[190px]',
        },
      },
    ],
    [
      m.details.item,
      m.details.metaKey,
      m.details.order,
      m.details.orderItem,
      m.details.processedAt,
      m.details.staffName,
      m.details.username,
    ]
  )

  return (
    <>
      <Header fixed>
        <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
      </Header>

      <Main fluid className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
        <div className='space-y-6'>
          <div className='space-y-1'>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            <p className='text-sm text-muted-foreground'>{m.subtitle}</p>
          </div>

          <div className='rounded-[6px] border bg-card p-5 shadow-sm'>
            <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-[220px_220px_minmax(0,1fr)_auto_auto]'>
              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.filters.dateFrom}</label>
                <Input
                  type='date'
                  className='h-10 rounded-[6px]'
                  value={filters.date_from}
                  onChange={(event) =>
                    setFilters((prev) => ({ ...prev, date_from: event.target.value }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.filters.dateTo}</label>
                <Input
                  type='date'
                  className='h-10 rounded-[6px]'
                  value={filters.date_to}
                  onChange={(event) =>
                    setFilters((prev) => ({ ...prev, date_to: event.target.value }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.filters.staffMember}</label>
                <Select
                  value={filters.staff_id || ALL_VALUE}
                  onValueChange={(value) =>
                    setFilters((prev) => ({
                      ...prev,
                      staff_id: value === ALL_VALUE ? '' : value,
                    }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>{m.filters.allStaff}</SelectItem>
                    {staffList.map((staff) => (
                      <SelectItem key={String(staff.id)} value={String(staff.id)}>
                        {staff.name || 'N/A'} ({staff.username || 'N/A'})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className='flex items-end'>
                <Button
                  className='h-10 rounded-[6px]'
                  onClick={() => {
                    setPagination((prev) => ({ ...prev, current_page: 1 }))
                    void loadReport(1, pagination.per_page)
                  }}
                >
                  <Filter className='size-4' />
                  {m.filters.apply}
                </Button>
              </div>

              <div className='flex items-end'>
                <Button
                  variant='outline'
                  className='h-10 rounded-[6px]'
                  onClick={() => void loadReport(pagination.current_page, pagination.per_page)}
                >
                  <RefreshCw className='size-4' />
                  {m.filters.refresh}
                </Button>
              </div>
            </div>
          </div>

          <Card className='rounded-[6px] shadow-sm'>
            <CardHeader className='pb-3'>
              <CardTitle className='flex items-center gap-2 text-lg'>
                <Users className='size-5' />
                {m.summary.title}
                {totalProcessed > 0 ? (
                  <span className='rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary'>
                    {m.summary.total}: <strong>{totalProcessed}</strong> {m.summary.items}
                  </span>
                ) : null}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className='overflow-x-auto rounded-[6px] border bg-card'>
                <table className='min-w-[760px] w-full text-sm'>
                  <thead>
                    <tr className='border-b bg-muted/30 text-left'>
                      <th className='px-4 py-3'>{m.summary.staffName}</th>
                      <th className='px-4 py-3'>{m.summary.username}</th>
                      <th className='px-4 py-3 text-center'>{m.summary.itemsProcessed}</th>
                      <th className='px-4 py-3'>{m.summary.contribution}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {loading && summaryData.length === 0 ? (
                      <tr>
                        <td colSpan={4} className='px-4 py-10 text-center text-muted-foreground'>
                          {m.loading}
                        </td>
                      </tr>
                    ) : summaryData.length > 0 ? (
                      summaryData.map((row, index) => (
                        <tr key={`${row.username || 'staff'}-${index}`} className='border-b'>
                          <td className='px-4 py-4 font-semibold'>{row.staff_name || 'N/A'}</td>
                          <td className='px-4 py-4'>{row.username || 'N/A'}</td>
                          <td className='px-4 py-4 text-center text-base font-bold text-primary'>
                            {row.items_processed || 0}
                          </td>
                          <td className='px-4 py-4'>
                            <div className='space-y-2'>
                              <div className='flex items-center justify-between text-xs'>
                                <span className='text-muted-foreground'>{m.summary.share}</span>
                                <strong>{row.percentage || 0}%</strong>
                              </div>
                              <ContributionBar value={row.percentage || 0} />
                            </div>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={4} className='px-4 py-10 text-center text-muted-foreground'>
                          {m.summary.noData}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>

          <Card className='rounded-[6px] shadow-sm'>
            <CardHeader className='pb-3'>
              <CardTitle className='flex items-center gap-2 text-lg'>
                <FileText className='size-5' />
                {m.details.title}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <LemiexDataTable
                columns={detailColumns}
                data={detailsData}
                page={pagination.current_page}
                pageSize={pagination.per_page}
                total={pagination.total}
                loading={loading}
                loadingText={m.loading}
                emptyText={m.details.noData}
                getRowId={(row, index) =>
                  `${row.order_id || 'order'}-${row.item_id || 'item'}-${index}`
                }
                onPageChange={(page) =>
                  setPagination((prev) => ({ ...prev, current_page: page }))
                }
                onPageSizeChange={(pageSize) =>
                  setPagination((prev) => ({
                    ...prev,
                    per_page: pageSize,
                    current_page: 1,
                  }))
                }
              />
            </CardContent>
          </Card>
        </div>
      </Main>
    </>
  )
}

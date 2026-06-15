'use client'

import { Fragment, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { CalendarDays, ChevronDown, FileUp, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { useI18n } from '@/context/i18n-provider'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  completeMissingAttendanceLog,
  fetchAttendanceLogs,
  fetchAttendances,
  importAttendance,
  type AttendanceFilters,
  type AttendanceLogRow,
  type AttendancePagination,
  type AttendanceRow,
} from '@/services/attendance/api'

const fallbackMessages = {
  title: 'Attendance Management',
  subtitle: 'Track employee work hours and logs',
  importBtn: 'Import .txt File',
  importing: 'Importing...',
  filters: {
    employeeName: 'Employee Name',
    searchPlaceholder: 'Search by name...',
    customRange: 'Custom Range',
    from: 'From',
    to: 'To',
    date: 'Single Date',
    month: 'Month',
    clear: 'Clear Filters',
  },
  columns: {
    id: 'ID',
    employeeName: 'Employee Name',
    totalDays: 'Total Days',
    week: 'Week',
    month: 'Month',
    year: 'Year',
  },
  days: 'days',
  logs: {
    show: 'Show',
    entries: 'entries',
    showing: 'Showing',
    of: 'of',
    records: 'records',
    noRecords: 'No records',
    date: 'Date',
    checkIn: 'Check In',
    checkOut: 'Check Out',
    totalWork: 'Total Work',
    loading: 'Loading...',
    noRecordsFound: 'No records found',
    completeMissing: 'Update',
    previous: 'Previous',
    next: 'Next',
    pageOf: 'Page {current} of {total}',
  },
  editModal: {
    title: 'Complete Missing Attendance',
    employee: 'Employee',
    workDate: 'Work Date',
    existingTime: 'Existing Time',
    missingType: 'Missing Type',
    checkIn: 'Check In',
    checkOut: 'Check Out',
    time: 'Time',
    cancel: 'Cancel',
    save: 'Save',
    saving: 'Saving...',
    validation: {
      timeRequired: 'Please select a time',
    },
  },
  messages: {
    failedLoadData: 'Failed to load attendance data',
    failedLoadLogs: 'Failed to load user logs',
    importSuccess: 'Imported successfully',
    importFailed: 'Import failed',
    noRecords: 'No attendance records found.',
    updateSuccess: 'Attendance updated successfully',
    updateFailed: 'Failed to update attendance',
  },
}

type UserLogState = {
  logs: AttendanceLogRow[]
  loading: boolean
  pagination: AttendancePagination
}

const DEFAULT_PAGINATION: AttendancePagination = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
}

function buildQueryString(
  params: Record<string, string | number | boolean | undefined | null>
) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === '' || value === undefined || value === null) return
    query.set(key, String(value))
  })

  return query.toString()
}

function formatTime(value?: string | null) {
  if (!value) return ''
  try {
    return new Date(value).toLocaleTimeString('en-US', { hour12: false })
  } catch {
    return ''
  }
}

export function LemiexAttendancesPage() {
  const { messages } = useI18n()
  const m = messages.attendancesPage ?? fallbackMessages
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  const [attendances, setAttendances] = useState<AttendanceRow[]>([])
  const [loading, setLoading] = useState(false)
  const [initialLoading, setInitialLoading] = useState(true)
  const [importing, setImporting] = useState(false)
  const [filters, setFilters] = useState<AttendanceFilters>({
    user_name: searchParams.get('user_name') || '',
    date: searchParams.get('date') || '',
    month: searchParams.get('month') || '',
    date_from: searchParams.get('date_from') || '',
    date_to: searchParams.get('date_to') || '',
  })
  const [pagination, setPagination] = useState<AttendancePagination>({
    current_page: Number(searchParams.get('page') || 1),
    last_page: 1,
    per_page: Number(searchParams.get('per_page') || 20),
    total: 0,
  })
  const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({})
  const [userLogs, setUserLogs] = useState<Record<string, UserLogState>>({})
  const [editLogModal, setEditLogModal] = useState({
    open: false,
    userId: '',
    userName: '',
    workDate: '',
    existingTime: '',
    missingType: 'check_out' as 'check_in' | 'check_out',
    time: '',
    submitting: false,
  })

  const syncQuery = useCallback(
    (nextFilters: AttendanceFilters, nextPage: number, nextPerPage: number) => {
      const next = buildQueryString({
        ...nextFilters,
        page: nextPage > 1 ? nextPage : '',
        per_page: nextPerPage !== 20 ? nextPerPage : '',
      })

      router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false })
    },
    [pathname, router]
  )

  const loadAttendances = useCallback(async () => {
    try {
      setLoading(true)
      const response = await fetchAttendances({
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...filters,
      })
      setAttendances(response.data)
      setPagination(response.pagination)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.messages.failedLoadData)
    } finally {
      setLoading(false)
      setInitialLoading(false)
    }
  }, [filters, m.messages.failedLoadData, pagination.current_page, pagination.per_page])

  useEffect(() => {
    void loadAttendances()
  }, [loadAttendances])

  const loadUserLogs = useCallback(
    async (userId: number | string, page = 1, perPageLogs = 10) => {
      try {
        setUserLogs((prev) => ({
          ...prev,
          [String(userId)]: {
            ...(prev[String(userId)] || {
              logs: [],
              loading: false,
              pagination: DEFAULT_PAGINATION,
            }),
            loading: true,
          },
        }))

        const response = await fetchAttendanceLogs(userId, {
          page,
          per_page: perPageLogs,
          ...filters,
        })

        setUserLogs((prev) => ({
          ...prev,
          [String(userId)]: {
            logs: response.data,
            loading: false,
            pagination: response.pagination,
          },
        }))
      } catch (error) {
        toast.error(error instanceof Error ? error.message : m.messages.failedLoadLogs)
        setUserLogs((prev) => ({
          ...prev,
          [String(userId)]: {
            ...(prev[String(userId)] || {
              logs: [],
              pagination: DEFAULT_PAGINATION,
            }),
            loading: false,
          } as UserLogState,
        }))
      }
    },
    [filters, m.messages.failedLoadLogs]
  )

  function handleFilterChange(key: keyof AttendanceFilters, value: string) {
    const nextFilters = { ...filters, [key]: value }

    if (key === 'date' && value) {
      nextFilters.month = ''
      nextFilters.date_from = ''
      nextFilters.date_to = ''
    }

    if (key === 'month' && value) {
      nextFilters.date = ''
      nextFilters.date_from = ''
      nextFilters.date_to = ''
    }

    if ((key === 'date_from' || key === 'date_to') && value) {
      nextFilters.date = ''
      nextFilters.month = ''
    }

    setFilters(nextFilters)
    setPagination((prev) => ({ ...prev, current_page: 1 }))
    setExpandedRows({})
    setUserLogs({})
    syncQuery(nextFilters, 1, pagination.per_page)
  }

  function clearFilters() {
    const nextFilters = {
      user_name: '',
      date: '',
      month: '',
      date_from: '',
      date_to: '',
    }

    setFilters(nextFilters)
    setPagination((prev) => ({ ...prev, current_page: 1 }))
    setExpandedRows({})
    setUserLogs({})
    syncQuery(nextFilters, 1, pagination.per_page)
  }

  function toggleRow(userId: number | string) {
    const key = String(userId)
    const isExpanding = !expandedRows[key]

    setExpandedRows((prev) => ({ ...prev, [key]: isExpanding }))

    if (isExpanding && !userLogs[key]) {
      void loadUserLogs(userId, 1, 10)
    }
  }

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    if (!file) return

    try {
      setImporting(true)
      const response = await importAttendance(file)
      if (response.status || response.success) {
        toast.success(m.messages.importSuccess)
        await loadAttendances()
      } else {
        toast.error(response.message || m.messages.importFailed)
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.messages.importFailed)
    } finally {
      setImporting(false)
      if (event.target) event.target.value = ''
    }
  }

  function openEditLogModal(employee: AttendanceRow, log: AttendanceLogRow) {
    setEditLogModal({
      open: true,
      userId: String(employee.user_id),
      userName: employee.user_name || 'N/A',
      workDate: log.work_date || '',
      existingTime: formatTime(log.check_in),
      missingType: 'check_out',
      time: '',
      submitting: false,
    })
  }

  function closeEditLogModal(force = false) {
    if (editLogModal.submitting && !force) return

    setEditLogModal({
      open: false,
      userId: '',
      userName: '',
      workDate: '',
      existingTime: '',
      missingType: 'check_out',
      time: '',
      submitting: false,
    })
  }

  async function handleCompleteMissingLog() {
    if (!editLogModal.time) {
      toast.error(m.editModal.validation.timeRequired)
      return
    }

    try {
      setEditLogModal((prev) => ({ ...prev, submitting: true }))
      const response = await completeMissingAttendanceLog(editLogModal.userId, {
        work_date: editLogModal.workDate,
        missing_type: editLogModal.missingType,
        time: editLogModal.time,
      })

      if (response.status || response.success) {
        toast.success(m.messages.updateSuccess)
        closeEditLogModal(true)
        await Promise.all([
          loadAttendances(),
          loadUserLogs(
            editLogModal.userId,
            userLogs[editLogModal.userId]?.pagination?.current_page || 1,
            userLogs[editLogModal.userId]?.pagination?.per_page || 10
          ),
        ])
        return
      }

      toast.error(response.message || m.messages.updateFailed)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.messages.updateFailed)
      setEditLogModal((prev) => ({ ...prev, submitting: false }))
    }
  }

  const periodColumnLabel = useMemo(
    () =>
      filters.date_from && filters.date_to ? m.filters.customRange : m.columns.month,
    [filters.date_from, filters.date_to, m.columns.month, m.filters.customRange]
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
          <div className='flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between'>
            <div className='space-y-1'>
              <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
              <p className='text-sm text-muted-foreground'>{m.subtitle}</p>
            </div>

            <div>
              <input
                ref={fileInputRef}
                type='file'
                className='hidden'
                accept='.txt'
                onChange={handleFileChange}
              />
              <Button
                className='h-10 rounded-[6px]'
                disabled={importing}
                onClick={() => fileInputRef.current?.click()}
              >
                {importing ? (
                  <RefreshCw className='size-4 animate-spin' />
                ) : (
                  <FileUp className='size-4' />
                )}
                {importing ? m.importing : m.importBtn}
              </Button>
            </div>
          </div>

          <Card className='rounded-[6px] shadow-sm'>
            <CardContent className='p-5'>
              <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)_220px_200px_auto]'>
                <div className='space-y-2'>
                  <Label>{m.filters.employeeName}</Label>
                  <Input
                    className='h-10 rounded-[6px]'
                    placeholder={m.filters.searchPlaceholder}
                    value={filters.user_name || ''}
                    onChange={(event) => handleFilterChange('user_name', event.target.value)}
                  />
                </div>

                <div className='space-y-2'>
                  <Label>{m.filters.customRange}</Label>
                  <div className='grid grid-cols-[1fr_auto_1fr] items-center gap-2'>
                    <Input
                      type='date'
                      className='h-10 rounded-[6px]'
                      value={filters.date_from || ''}
                      onChange={(event) => handleFilterChange('date_from', event.target.value)}
                    />
                    <span className='text-muted-foreground'>→</span>
                    <Input
                      type='date'
                      className='h-10 rounded-[6px]'
                      value={filters.date_to || ''}
                      onChange={(event) => handleFilterChange('date_to', event.target.value)}
                    />
                  </div>
                </div>

                <div className='space-y-2'>
                  <Label>{m.filters.date}</Label>
                  <Input
                    type='date'
                    className='h-10 rounded-[6px]'
                    value={filters.date || ''}
                    onChange={(event) => handleFilterChange('date', event.target.value)}
                  />
                </div>

                <div className='space-y-2'>
                  <Label>{m.filters.month}</Label>
                  <Input
                    type='month'
                    className='h-10 rounded-[6px]'
                    value={filters.month || ''}
                    onChange={(event) => handleFilterChange('month', event.target.value)}
                  />
                </div>

                <div className='flex items-end'>
                  <Button
                    variant='outline'
                    className='h-10 w-full rounded-[6px] xl:w-auto'
                    onClick={clearFilters}
                  >
                    {m.filters.clear}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className='overflow-x-auto rounded-[6px] border bg-card'>
            <Table className='min-w-[980px]'>
              <TableHeader>
                <TableRow>
                  <TableHead className='w-14 text-center' />
                  <TableHead>{m.columns.id}</TableHead>
                  <TableHead>{m.columns.employeeName}</TableHead>
                  <TableHead className='text-center'>{m.columns.totalDays}</TableHead>
                  <TableHead className='text-center'>{m.columns.week}</TableHead>
                  <TableHead className='text-center'>{periodColumnLabel}</TableHead>
                  <TableHead className='text-center'>{m.columns.year}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {initialLoading || loading ? (
                  <TableRow>
                    <TableCell colSpan={7} className='h-24 text-center text-muted-foreground'>
                      {m.logs.loading}
                    </TableCell>
                  </TableRow>
                ) : attendances.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className='h-24 text-center'>
                      {m.messages.noRecords}
                    </TableCell>
                  </TableRow>
                ) : (
                  attendances.map((row) => {
                    const key = String(row.user_id)
                    const isExpanded = expandedRows[key]
                    const userData = userLogs[key]

                    return (
                      <Fragment key={key}>
                        <TableRow
                          className='cursor-pointer'
                          onClick={() => toggleRow(row.user_id)}
                        >
                          <TableCell className='text-center'>
                            <ChevronDown
                              className={`mx-auto size-4 transition-transform ${
                                isExpanded ? 'rotate-180' : ''
                              }`}
                            />
                          </TableCell>
                          <TableCell>#{row.user_id}</TableCell>
                          <TableCell className='font-semibold'>{row.user_name || 'N/A'}</TableCell>
                          <TableCell className='text-center'>
                            <span className='rounded-full bg-sky-500/10 px-2.5 py-1 text-xs font-semibold text-sky-700'>
                              {row.total_days || 0} {m.days}
                            </span>
                          </TableCell>
                          <TableCell className='text-center'>
                            <span className='rounded-full bg-muted px-2.5 py-1 font-mono text-xs'>
                              {row.total_hours_week || '00:00:00'}
                            </span>
                          </TableCell>
                          <TableCell className='text-center'>
                            <span className='rounded-full bg-amber-500/10 px-2.5 py-1 font-mono text-xs text-amber-700'>
                              {row.total_hours_month || '00:00:00'}
                            </span>
                          </TableCell>
                          <TableCell className='text-center'>
                            <span className='rounded-full bg-emerald-500/10 px-2.5 py-1 font-mono text-xs text-emerald-700'>
                              {row.total_hours_year || '00:00:00'}
                            </span>
                          </TableCell>
                        </TableRow>

                        {isExpanded ? (
                          <TableRow>
                            <TableCell colSpan={7} className='bg-muted/20 p-4'>
                              <div className='space-y-4'>
                                <div className='flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
                                  <div className='text-sm text-muted-foreground'>
                                    {userData?.pagination?.total
                                      ? `${m.logs.showing} ${
                                          (userData.pagination.current_page - 1) *
                                            userData.pagination.per_page +
                                          1
                                        } - ${Math.min(
                                          userData.pagination.current_page *
                                            userData.pagination.per_page,
                                          userData.pagination.total
                                        )} ${m.logs.of} ${userData.pagination.total} ${m.logs.records}`
                                      : m.logs.noRecords}
                                  </div>
                                  <div className='flex items-center gap-2'>
                                    <span className='text-sm text-muted-foreground'>{m.logs.show}</span>
                                    <select
                                      className='h-9 rounded-[6px] border bg-background px-3 text-sm'
                                      value={userData?.pagination?.per_page || 10}
                                      onChange={(event) =>
                                        void loadUserLogs(
                                          row.user_id,
                                          1,
                                          Number(event.target.value)
                                        )
                                      }
                                    >
                                      {[5, 10, 20, 50].map((size) => (
                                        <option key={size} value={size}>
                                          {size}
                                        </option>
                                      ))}
                                    </select>
                                    <span className='text-sm text-muted-foreground'>{m.logs.entries}</span>
                                  </div>
                                </div>

                                <div className='overflow-x-auto rounded-[6px] border bg-background'>
                                  <Table className='min-w-[640px]'>
                                    <TableHeader>
                                      <TableRow>
                                        <TableHead>{m.logs.date}</TableHead>
                                        <TableHead>{m.logs.checkIn}</TableHead>
                                        <TableHead>{m.logs.checkOut}</TableHead>
                                        <TableHead>{m.logs.totalWork}</TableHead>
                                      </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                      {userData?.loading ? (
                                        <TableRow>
                                          <TableCell
                                            colSpan={4}
                                            className='h-20 text-center text-muted-foreground'
                                          >
                                            {m.logs.loading}
                                          </TableCell>
                                        </TableRow>
                                      ) : userData?.logs?.length ? (
                                        userData.logs.map((log, index) => {
                                          const isSameTime =
                                            Boolean(log.check_in) &&
                                            Boolean(log.check_out) &&
                                            log.check_in === log.check_out
                                          const forgotCheckOut = isSameTime
                                          const isInvalidWork = log.total_work === '00:00:00'
                                          const canCompleteLog = (log.scan_count || 0) === 1

                                          return (
                                            <TableRow
                                              key={`${key}-log-${index}`}
                                              className={isInvalidWork ? 'bg-amber-500/5' : ''}
                                            >
                                              <TableCell>{log.work_date || 'N/A'}</TableCell>
                                              <TableCell>{formatTime(log.check_in) || '-'}</TableCell>
                                              <TableCell>
                                                {forgotCheckOut ? '-' : formatTime(log.check_out) || '-'}
                                              </TableCell>
                                              <TableCell>
                                                <div className='flex items-center gap-2'>
                                                  <span
                                                    className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                      isInvalidWork
                                                        ? 'bg-rose-500/10 text-rose-700'
                                                        : 'bg-emerald-500/10 text-emerald-700'
                                                    }`}
                                                  >
                                                    {log.total_work || '00:00:00'}
                                                  </span>
                                                  {canCompleteLog ? (
                                                    <Button
                                                      size='sm'
                                                      variant='outline'
                                                      className='h-8 rounded-[6px]'
                                                      onClick={(event) => {
                                                        event.stopPropagation()
                                                        openEditLogModal(row, log)
                                                      }}
                                                    >
                                                      {m.logs.completeMissing}
                                                    </Button>
                                                  ) : null}
                                                </div>
                                              </TableCell>
                                            </TableRow>
                                          )
                                        })
                                      ) : (
                                        <TableRow>
                                          <TableCell
                                            colSpan={4}
                                            className='h-20 text-center text-muted-foreground'
                                          >
                                            {m.logs.noRecordsFound}
                                          </TableCell>
                                        </TableRow>
                                      )}
                                    </TableBody>
                                  </Table>
                                </div>

                                {userData?.pagination?.last_page > 1 ? (
                                  <div className='flex items-center justify-end gap-3'>
                                    <Button
                                      variant='outline'
                                      className='h-9 rounded-[6px]'
                                      disabled={
                                        userData.loading ||
                                        userData.pagination.current_page === 1
                                      }
                                      onClick={() =>
                                        void loadUserLogs(
                                          row.user_id,
                                          userData.pagination.current_page - 1,
                                          userData.pagination.per_page
                                        )
                                      }
                                    >
                                      {m.logs.previous}
                                    </Button>
                                    <span className='text-sm text-muted-foreground'>
                                      {m.logs.pageOf
                                        .replace('{current}', String(userData.pagination.current_page))
                                        .replace('{total}', String(userData.pagination.last_page))}
                                    </span>
                                    <Button
                                      variant='outline'
                                      className='h-9 rounded-[6px]'
                                      disabled={
                                        userData.loading ||
                                        userData.pagination.current_page ===
                                          userData.pagination.last_page
                                      }
                                      onClick={() =>
                                        void loadUserLogs(
                                          row.user_id,
                                          userData.pagination.current_page + 1,
                                          userData.pagination.per_page
                                        )
                                      }
                                    >
                                      {m.logs.next}
                                    </Button>
                                  </div>
                                ) : null}
                              </div>
                            </TableCell>
                          </TableRow>
                        ) : null}
                      </Fragment>
                    )
                  })
                )}
              </TableBody>
            </Table>
          </div>

          <div className='flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
            <div className='text-sm text-muted-foreground'>
              {pagination.total > 0
                ? `${(pagination.current_page - 1) * pagination.per_page + 1} - ${Math.min(
                    pagination.current_page * pagination.per_page,
                    pagination.total
                  )} ${m.logs.of} ${pagination.total}`
                : m.logs.noRecords}
            </div>
            <div className='flex items-center gap-2'>
              <select
                className='h-10 rounded-[6px] border bg-background px-3 text-sm'
                value={pagination.per_page}
                onChange={(event) => {
                  const nextPerPage = Number(event.target.value)
                  setPagination((prev) => ({ ...prev, current_page: 1, per_page: nextPerPage }))
                  syncQuery(filters, 1, nextPerPage)
                }}
              >
                {[10, 20, 50, 100].map((size) => (
                  <option key={size} value={size}>
                    {size}
                  </option>
                ))}
              </select>
              <Button
                variant='outline'
                className='h-10 rounded-[6px]'
                disabled={pagination.current_page === 1 || loading}
                onClick={() => {
                  const nextPage = pagination.current_page - 1
                  setPagination((prev) => ({ ...prev, current_page: nextPage }))
                  syncQuery(filters, nextPage, pagination.per_page)
                }}
              >
                {m.logs.previous}
              </Button>
              <span className='text-sm text-muted-foreground'>
                {m.logs.pageOf
                  .replace('{current}', String(pagination.current_page))
                  .replace('{total}', String(pagination.last_page))}
              </span>
              <Button
                variant='outline'
                className='h-10 rounded-[6px]'
                disabled={pagination.current_page === pagination.last_page || loading}
                onClick={() => {
                  const nextPage = pagination.current_page + 1
                  setPagination((prev) => ({ ...prev, current_page: nextPage }))
                  syncQuery(filters, nextPage, pagination.per_page)
                }}
              >
                {m.logs.next}
              </Button>
            </div>
          </div>
        </div>
      </Main>

      <Dialog open={editLogModal.open} onOpenChange={closeEditLogModal}>
        <DialogContent className='rounded-[6px] sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>{m.editModal.title}</DialogTitle>
          </DialogHeader>

          <div className='grid gap-4'>
            <div className='grid gap-3 rounded-[6px] border bg-muted/30 p-4 sm:grid-cols-3'>
              <div className='space-y-1'>
                <p className='text-xs font-medium text-muted-foreground'>{m.editModal.employee}</p>
                <p className='text-sm font-semibold'>{editLogModal.userName}</p>
              </div>
              <div className='space-y-1'>
                <p className='text-xs font-medium text-muted-foreground'>{m.editModal.workDate}</p>
                <p className='text-sm font-semibold'>{editLogModal.workDate}</p>
              </div>
              <div className='space-y-1'>
                <p className='text-xs font-medium text-muted-foreground'>
                  {m.editModal.existingTime}
                </p>
                <p className='text-sm font-semibold'>
                  {editLogModal.existingTime || '-'}
                </p>
              </div>
            </div>

            <div className='space-y-2'>
              <Label>{m.editModal.missingType}</Label>
              <div className='grid grid-cols-2 gap-3'>
                {(['check_in', 'check_out'] as const).map((type) => (
                  <button
                    key={type}
                    type='button'
                    className={`rounded-[6px] border px-4 py-3 text-left transition-colors ${
                      editLogModal.missingType === type
                        ? 'border-primary bg-primary/5'
                        : 'bg-background hover:bg-muted/40'
                    }`}
                    onClick={() =>
                      setEditLogModal((prev) => ({ ...prev, missingType: type }))
                    }
                  >
                    <div className='flex items-center gap-3'>
                      <CalendarDays className='size-4 text-muted-foreground' />
                      <span className='font-medium'>
                        {type === 'check_in' ? m.editModal.checkIn : m.editModal.checkOut}
                      </span>
                    </div>
                  </button>
                ))}
              </div>
            </div>

            <div className='space-y-2'>
              <Label>{m.editModal.time}</Label>
              <Input
                type='time'
                className='h-11 rounded-[6px]'
                value={editLogModal.time}
                onChange={(event) =>
                  setEditLogModal((prev) => ({ ...prev, time: event.target.value }))
                }
              />
            </div>
          </div>

          <DialogFooter>
            <Button
              type='button'
              variant='outline'
              className='h-11 rounded-[6px]'
              onClick={() => closeEditLogModal()}
              disabled={editLogModal.submitting}
            >
              {m.editModal.cancel}
            </Button>
            <Button
              type='button'
              className='h-11 rounded-[6px]'
              onClick={() => void handleCompleteMissingLog()}
              disabled={editLogModal.submitting}
            >
              {editLogModal.submitting ? m.editModal.saving : m.editModal.save}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

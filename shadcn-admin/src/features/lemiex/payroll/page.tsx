'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { CircleHelp, Pencil, ReceiptText, Settings2 } from 'lucide-react'
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  createPayrollAdjustment,
  createSalary,
  fetchCurrentSalary,
  fetchPayrollReport,
  fetchPayrollTiers,
  fetchSalaryLog,
  type PayrollAdjustment,
  type PayrollFilters,
  type PayrollRow,
  type PayrollTier,
  type SalaryLogItem,
  updatePayrollNetSalary,
  updateSalary,
} from '@/services/payroll/api'
import { PayrollHelpDialog } from './payroll-help-dialog'

const fallbackMessages = {
  title: 'Payroll Report',
  subtitle: 'Track payroll for {period} with {count} employees',
  setRate: 'Set Rate',
  rewardsPenalties: 'Rewards / Penalties',
  month: 'Month',
  customRange: 'Custom Range',
  from: 'From',
  to: 'To',
  totalHours: 'Total Hours',
  totalSalary: 'Total Salary',
  netTotal: 'Net Salary',
  companyTaxTotal: 'Co. Tax',
  missingRate: 'Missing Rate',
  staffs: 'staffs',
  noEmployees: 'No employees found',
  employee: 'Employee',
  rateHr: 'Rate/Hr',
  hours: 'Hours',
  adjustments: 'Adjustments',
  grossSalary: 'Gross',
  netSalary: 'Net',
  companyTax: 'Co. Tax',
  totalSalaryCol: 'Total',
  actions: 'Actions',
  edit: 'Edit',
  log: 'Log',
  view: 'View',
  clickToEdit: 'Click to edit',
  save: 'Save',
  cancel: 'Cancel',
  close: 'Close',
  loading: 'Loading payroll...',
  selectEmployee: 'Please select at least one employee',
  selectTierOrRate: 'Please select a tier or enter a custom rate',
  fillTypeAmount: 'Please fill in type and amount',
  rateSetSuccess: '{success}/{total} salary rates set successfully',
  failedSetRate: 'Failed to set salary rate',
  rateUpdated: 'Salary rate updated successfully',
  failedUpdateRate: 'Failed to update salary rate',
  adjustmentSuccess: '{success}/{total} adjustments created successfully',
  failedAdjustment: 'Failed to create adjustments',
  failedLoadPayroll: 'Failed to load payroll data',
  fieldUpdated: 'Updated successfully',
  failedUpdate: 'Failed to update',
  setRateModal: {
    title: 'Set Salary Rate',
    selectEmployees: 'Select Employees',
    selectAll: 'Select All',
    selected: 'selected',
    selectTier: 'Select Tier',
    or: 'OR',
    customRate: 'Custom Hourly Rate',
    effectiveFrom: 'Effective From',
    setting: 'Setting...',
    setRateBtn: 'Set Rate',
  },
  editRateModal: {
    title: 'Edit Salary Rate',
    hourlyRate: 'Hourly Rate',
    detachNote: 'Entering a custom rate will detach this employee from the current tier.',
    note: 'Note',
    reasonPlaceholder: 'Reason for salary update',
    saving: 'Saving...',
  },
  salaryLog: {
    title: 'Salary Log',
    noHistory: 'No salary history available',
    custom: 'Custom',
    from: 'From',
    ended: 'Ended',
    current: 'Current',
  },
  adjustmentModal: {
    title: 'Add Reward / Penalty',
    type: 'Type',
    typePlaceholder: 'Ex: Bonus, Late fine...',
    amount: 'Amount',
    action: 'Action',
    addReward: 'Add Reward',
    deductPenalty: 'Deduct Penalty',
    date: 'Date',
    processing: 'Processing...',
    add: 'Add',
    deduct: 'Deduct',
  },
  adjustmentDetail: {
    title: 'Adjustment Details',
    noAdjustments: 'No adjustments available',
    typeReason: 'Type / Reason',
  },
  guide: {
    title: 'Payroll Guide',
    close: 'Close',
    steps: [
      {
        icon: '📊',
        title: 'Review working hours',
        desc: 'Check payroll by month or custom range before making salary decisions.',
      },
      {
        icon: '💰',
        title: 'Assign salary rates',
        desc: 'Set hourly rates by tier or by custom amount for selected employees.',
      },
      {
        icon: '⚖️',
        title: 'Apply rewards and penalties',
        desc: 'Use adjustments to add bonuses or deduct penalties from payroll.',
      },
      {
        icon: '📈',
        title: 'Finalize net salary',
        desc: 'Inline edit net salary and company tax to reflect the final payroll total.',
      },
    ],
  },
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

function getBaseSalaryValue(row: PayrollRow) {
  const baseSalary = Number(row.base_salary)
  return Number.isNaN(baseSalary) ? 0 : baseSalary
}

function getAdjustmentsTotal(row: PayrollRow) {
  if (!Array.isArray(row.adjustments_detail)) return 0

  return row.adjustments_detail.reduce((sum, item) => {
    const amount = Number.parseFloat(String(item?.amount ?? 0))
    return sum + (Number.isNaN(amount) ? 0 : amount)
  }, 0)
}

function getCompanyTaxValue(row: PayrollRow) {
  const companyTax = Number(row.company_tax)
  return Number.isNaN(companyTax) ? 0 : companyTax
}

function getAdjustedGrossSalaryValue(row: PayrollRow) {
  const grossSalary = Number(row.gross_salary)
  if (!Number.isNaN(grossSalary)) return grossSalary

  const finalSalary = Number(row.final_salary)
  if (!Number.isNaN(finalSalary)) return finalSalary

  return getBaseSalaryValue(row) + getAdjustmentsTotal(row)
}

function getPayrollTotalValue(row: PayrollRow) {
  return getAdjustedGrossSalaryValue(row) + getCompanyTaxValue(row)
}

function formatMoney(value: number) {
  return value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

export function LemiexPayrollPage() {
  const { messages } = useI18n()
  const m = messages.payrollPage ?? fallbackMessages
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const defaultMonth = new Date().toISOString().slice(0, 7)

  const [loading, setLoading] = useState(false)
  const [payrollData, setPayrollData] = useState<PayrollRow[]>([])
  const [tiers, setTiers] = useState<PayrollTier[]>([])
  const [showSetRateModal, setShowSetRateModal] = useState(false)
  const [showEditRateModal, setShowEditRateModal] = useState(false)
  const [showLogModal, setShowLogModal] = useState(false)
  const [showAdjustmentModal, setShowAdjustmentModal] = useState(false)
  const [showAdjustmentDetailModal, setShowAdjustmentDetailModal] = useState(false)
  const [showHelpModal, setShowHelpModal] = useState(false)
  const [selectedEmployee, setSelectedEmployee] = useState<PayrollRow | null>(null)
  const [salaryLog, setSalaryLog] = useState<SalaryLogItem[]>([])
  const [selectedAdjustments, setSelectedAdjustments] = useState<PayrollAdjustment[]>([])
  const [saving, setSaving] = useState(false)
  const [savingInline, setSavingInline] = useState(false)
  const [editingCell, setEditingCell] = useState<{
    employee_id: number | string
    field: 'net_salary' | 'company_tax'
    value: string
  } | null>(null)
  const [filters, setFilters] = useState<PayrollFilters>({
    month:
      searchParams.get('month') || (!searchParams.get('date_from') ? defaultMonth : ''),
    date_from: searchParams.get('date_from') || '',
    date_to: searchParams.get('date_to') || '',
  })
  const [rateForm, setRateForm] = useState({
    employee_ids: [] as Array<number | string>,
    salary_tier_id: '',
    custom_hourly_rate: '',
    effective_date: `${filters.month || defaultMonth}-01`,
    note: '',
  })
  const [adjustmentForm, setAdjustmentForm] = useState({
    employee_ids: [] as Array<number | string>,
    name: '',
    category: 'add',
    amount: '',
    date: `${filters.month || defaultMonth}-01`,
    reason: '',
  })

  const syncQuery = useCallback(
    (nextFilters: PayrollFilters) => {
      const next = buildQueryString(nextFilters)
      router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false })
    },
    [pathname, router]
  )

  useEffect(() => {
    let active = true

    async function loadTiers() {
      try {
        const response = await fetchPayrollTiers()
        if (!active) return
        setTiers(response)
      } catch {
        if (!active) return
      }
    }

    void loadTiers()
    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    let active = true

    async function loadPayroll() {
      try {
        setLoading(true)
        const params: PayrollFilters = {}

        if (filters.date_from && filters.date_to) {
          params.date_from = filters.date_from
          params.date_to = filters.date_to
        } else if (filters.month) {
          params.month = filters.month
        } else {
          params.month = defaultMonth
        }

        const response = await fetchPayrollReport(params)
        if (!active) return
        setPayrollData(response)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.failedLoadPayroll)
      } finally {
        if (active) setLoading(false)
      }
    }

    void loadPayroll()
    return () => {
      active = false
    }
  }, [defaultMonth, filters, m.failedLoadPayroll])

  function handleFilterChange(key: keyof PayrollFilters, value: string) {
    const nextFilters = { ...filters, [key]: value }

    if (key === 'month' && value) {
      nextFilters.date_from = ''
      nextFilters.date_to = ''
    }

    if ((key === 'date_from' || key === 'date_to') && value) {
      nextFilters.month = ''
    }

    setFilters(nextFilters)
    syncQuery(nextFilters)
  }

  async function reloadPayroll() {
    const params: PayrollFilters = {}

    if (filters.date_from && filters.date_to) {
      params.date_from = filters.date_from
      params.date_to = filters.date_to
    } else if (filters.month) {
      params.month = filters.month
    } else {
      params.month = defaultMonth
    }

    const response = await fetchPayrollReport(params)
    setPayrollData(response)
  }

  async function handleSaveInlineField(row: PayrollRow) {
    if (!editingCell || editingCell.value === '') {
      setEditingCell(null)
      return
    }

    try {
      setSavingInline(true)
      const fieldValue = Number.parseFloat(editingCell.value)

      await updatePayrollNetSalary({
        employee_id: row.employee_id,
        period: row.month,
        [editingCell.field]: fieldValue,
      })

      setPayrollData((prev) =>
        prev.map((employee) =>
          employee.employee_id === row.employee_id
            ? { ...employee, [editingCell.field]: fieldValue }
            : employee
        )
      )
      setEditingCell(null)
      toast.success(m.fieldUpdated)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedUpdate)
    } finally {
      setSavingInline(false)
    }
  }

  function toggleEmployeeForRate(employeeId: number | string) {
    setRateForm((prev) => ({
      ...prev,
      employee_ids: prev.employee_ids.includes(employeeId)
        ? prev.employee_ids.filter((id) => id !== employeeId)
        : [...prev.employee_ids, employeeId],
    }))
  }

  function toggleEmployeeForAdjustment(employeeId: number | string) {
    setAdjustmentForm((prev) => ({
      ...prev,
      employee_ids: prev.employee_ids.includes(employeeId)
        ? prev.employee_ids.filter((id) => id !== employeeId)
        : [...prev.employee_ids, employeeId],
    }))
  }

  function toggleAllForRate() {
    const allIds = payrollData.map((employee) => employee.employee_id)
    setRateForm((prev) => ({
      ...prev,
      employee_ids: prev.employee_ids.length === allIds.length ? [] : allIds,
    }))
  }

  function toggleAllForAdjustment() {
    const allIds = payrollData.map((employee) => employee.employee_id)
    setAdjustmentForm((prev) => ({
      ...prev,
      employee_ids: prev.employee_ids.length === allIds.length ? [] : allIds,
    }))
  }

  async function handleSetRate() {
    if (rateForm.employee_ids.length === 0) {
      toast.error(m.selectEmployee)
      return
    }

    if (!rateForm.salary_tier_id && !rateForm.custom_hourly_rate) {
      toast.error(m.selectTierOrRate)
      return
    }

    try {
      setSaving(true)
      let successCount = 0

      for (const employeeId of rateForm.employee_ids) {
        try {
          await createSalary(employeeId, {
            ...(rateForm.salary_tier_id
              ? { salary_tier_id: rateForm.salary_tier_id }
              : {
                  custom_hourly_rate: Number.parseFloat(rateForm.custom_hourly_rate),
                }),
            effective_date: rateForm.effective_date,
            note: rateForm.note,
          })
          successCount += 1
        } catch {
          // Keep old behavior: continue processing remaining employees.
        }
      }

      toast.success(
        m.rateSetSuccess
          .replace('{success}', String(successCount))
          .replace('{total}', String(rateForm.employee_ids.length))
      )
      setShowSetRateModal(false)
      await reloadPayroll()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedSetRate)
    } finally {
      setSaving(false)
    }
  }

  async function openEditRateModal(employee: PayrollRow) {
    setSelectedEmployee(employee)

    try {
      const current = await fetchCurrentSalary(employee.employee_id)
      setRateForm({
        employee_ids: [],
        salary_tier_id: current.salary_tier_id ? String(current.salary_tier_id) : '',
        custom_hourly_rate: current.custom_hourly_rate
          ? String(current.custom_hourly_rate)
          : '',
        effective_date: current.effective_date
          ? current.effective_date.split('T')[0] || `${filters.month || defaultMonth}-01`
          : `${filters.month || defaultMonth}-01`,
        note: current.note || '',
      })
    } catch {
      setRateForm({
        employee_ids: [],
        salary_tier_id: '',
        custom_hourly_rate: '',
        effective_date: `${filters.month || defaultMonth}-01`,
        note: '',
      })
    }

    setShowEditRateModal(true)
  }

  async function handleEditRate() {
    if (!selectedEmployee) return

    if (!rateForm.salary_tier_id && !rateForm.custom_hourly_rate) {
      toast.error(m.selectTierOrRate)
      return
    }

    try {
      setSaving(true)
      await updateSalary(selectedEmployee.employee_id, {
        ...(rateForm.salary_tier_id
          ? { salary_tier_id: rateForm.salary_tier_id }
          : {
              custom_hourly_rate: Number.parseFloat(rateForm.custom_hourly_rate),
            }),
        effective_date: rateForm.effective_date,
        note: rateForm.note,
      })
      toast.success(m.rateUpdated)
      setShowEditRateModal(false)
      await reloadPayroll()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedUpdateRate)
    } finally {
      setSaving(false)
    }
  }

  async function openLogModal(employee: PayrollRow) {
    setSelectedEmployee(employee)
    setSalaryLog([])

    try {
      const response = await fetchSalaryLog(employee.employee_id)
      setSalaryLog(response)
    } catch {
      setSalaryLog([])
    }

    setShowLogModal(true)
  }

  async function handleCreateAdjustment() {
    if (adjustmentForm.employee_ids.length === 0) {
      toast.error(m.selectEmployee)
      return
    }

    if (!adjustmentForm.name || !adjustmentForm.amount) {
      toast.error(m.fillTypeAmount)
      return
    }

    try {
      setSaving(true)
      const amountValue = Math.abs(Number.parseFloat(adjustmentForm.amount))
      const finalAmount =
        adjustmentForm.category === 'deduct' ? -amountValue : amountValue

      let successCount = 0

      for (const employeeId of adjustmentForm.employee_ids) {
        try {
          await createPayrollAdjustment({
            employee_id: employeeId,
            type: adjustmentForm.name,
            amount: finalAmount,
            date: adjustmentForm.date,
            reason: adjustmentForm.reason,
          })
          successCount += 1
        } catch {
          // Keep old behavior: continue with remaining employees.
        }
      }

      toast.success(
        m.adjustmentSuccess
          .replace('{success}', String(successCount))
          .replace('{total}', String(adjustmentForm.employee_ids.length))
      )
      setShowAdjustmentModal(false)
      await reloadPayroll()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedAdjustment)
    } finally {
      setSaving(false)
    }
  }

  const totals = useMemo(
    () =>
      payrollData.reduce(
        (acc, row) => ({
          hours: acc.hours + (row.total_hours || 0),
          grossSalary: acc.grossSalary + getBaseSalaryValue(row),
          adjustments: acc.adjustments + getAdjustmentsTotal(row),
          netSalary: acc.netSalary + (row.net_salary || 0),
          companyTax: acc.companyTax + getCompanyTaxValue(row),
          noRate: acc.noRate + ((row.current_rate || 0) > 0 ? 0 : 1),
        }),
        { hours: 0, grossSalary: 0, adjustments: 0, netSalary: 0, companyTax: 0, noRate: 0 }
      ),
    [payrollData]
  )

  const subtitlePeriod =
    filters.month || `${filters.date_from || '...'} -> ${filters.date_to || '...'}`

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
          <div className='flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between'>
            <div className='space-y-1'>
              <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
              <p className='text-sm text-muted-foreground'>
                {m.subtitle
                  .replace('{period}', subtitlePeriod)
                  .replace('{count}', String(payrollData.length))}
              </p>
            </div>

            <div className='flex flex-wrap items-end gap-3'>
              <Button className='h-10 rounded-[6px]' onClick={() => {
                setRateForm({
                  employee_ids: [],
                  salary_tier_id: '',
                  custom_hourly_rate: '',
                  effective_date: `${filters.month || defaultMonth}-01`,
                  note: '',
                })
                setShowSetRateModal(true)
              }}>
                <Settings2 className='size-4' />
                {m.setRate}
              </Button>
              <Button
                variant='outline'
                className='h-10 rounded-[6px]'
                onClick={() => {
                  setAdjustmentForm({
                    employee_ids: [],
                    name: '',
                    category: 'add',
                    amount: '',
                    date: `${filters.month || defaultMonth}-01`,
                    reason: '',
                  })
                  setShowAdjustmentModal(true)
                }}
              >
                <ReceiptText className='size-4' />
                {m.rewardsPenalties}
              </Button>

              <div className='flex items-center gap-2 rounded-[6px] border bg-card px-3 py-2'>
                <span className='text-xs font-semibold text-muted-foreground'>{m.from}</span>
                <Input
                  type='date'
                  className='h-8 w-[150px] rounded-[6px] border-0 px-0 shadow-none'
                  value={filters.date_from || ''}
                  onChange={(event) => handleFilterChange('date_from', event.target.value)}
                />
                <span className='text-muted-foreground'>→</span>
                <span className='text-xs font-semibold text-muted-foreground'>{m.to}</span>
                <Input
                  type='date'
                  className='h-8 w-[150px] rounded-[6px] border-0 px-0 shadow-none'
                  value={filters.date_to || ''}
                  onChange={(event) => handleFilterChange('date_to', event.target.value)}
                />
              </div>

              <div className='space-y-1'>
                <Label>{m.month}</Label>
                <Input
                  type='month'
                  className='h-10 w-[180px] rounded-[6px]'
                  value={filters.month || ''}
                  onChange={(event) => handleFilterChange('month', event.target.value)}
                />
              </div>

              <Button
                variant='outline'
                size='icon'
                className='size-10 rounded-[6px]'
                onClick={() => setShowHelpModal(true)}
              >
                <CircleHelp className='size-4' />
              </Button>
            </div>
          </div>

          <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
            <Card className='rounded-[6px] shadow-sm'>
              <CardContent className='flex items-center gap-4 p-5'>
                <div className='rounded-full bg-primary/10 p-3 text-primary'>
                  <ReceiptText className='size-5' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>{m.totalHours}</p>
                  <p className='text-2xl font-semibold'>{totals.hours.toFixed(2)}h</p>
                </div>
              </CardContent>
            </Card>
            <Card className='rounded-[6px] shadow-sm'>
              <CardContent className='flex items-center gap-4 p-5'>
                <div className='rounded-full bg-primary/10 p-3 text-primary'>
                  <ReceiptText className='size-5' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>{m.totalSalary}</p>
                  <p className='text-2xl font-semibold'>
                    ${formatMoney(totals.grossSalary + totals.adjustments + totals.companyTax)}
                  </p>
                </div>
              </CardContent>
            </Card>
            <Card className='rounded-[6px] shadow-sm'>
              <CardContent className='flex items-center gap-4 p-5'>
                <div className='rounded-full bg-emerald-500/10 p-3 text-emerald-600'>
                  <ReceiptText className='size-5' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>{m.netTotal}</p>
                  <p className='text-2xl font-semibold text-emerald-600'>
                    ${formatMoney(totals.netSalary)}
                  </p>
                </div>
              </CardContent>
            </Card>
            <Card className='rounded-[6px] shadow-sm'>
              <CardContent className='flex items-center gap-4 p-5'>
                <div className='rounded-full bg-amber-500/10 p-3 text-amber-600'>
                  <ReceiptText className='size-5' />
                </div>
                <div>
                  <p className='text-sm text-muted-foreground'>{m.companyTaxTotal}</p>
                  <p className='text-2xl font-semibold text-amber-600'>
                    ${formatMoney(totals.companyTax)}
                  </p>
                  {totals.noRate > 0 ? (
                    <p className='text-xs text-rose-600'>
                      {totals.noRate} {m.staffs} {m.missingRate.toLowerCase()}
                    </p>
                  ) : null}
                </div>
              </CardContent>
            </Card>
          </div>

          <div className='overflow-x-auto rounded-[6px] border bg-card'>
            <Table className='min-w-[1240px]'>
              <TableHeader>
                <TableRow>
                  <TableHead>{m.employee}</TableHead>
                  <TableHead className='text-center'>{m.rateHr}</TableHead>
                  <TableHead className='text-center'>{m.hours}</TableHead>
                  <TableHead className='text-center'>{m.adjustments}</TableHead>
                  <TableHead className='text-right'>{m.grossSalary}</TableHead>
                  <TableHead className='text-right'>{m.netSalary}</TableHead>
                  <TableHead className='text-right'>{m.companyTax}</TableHead>
                  <TableHead className='text-right'>{m.totalSalaryCol}</TableHead>
                  <TableHead className='text-center'>{m.actions}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  <TableRow>
                    <TableCell colSpan={9} className='h-24 text-center text-muted-foreground'>
                      {m.loading}
                    </TableCell>
                  </TableRow>
                ) : payrollData.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={9} className='h-24 text-center'>
                      {m.noEmployees}
                    </TableCell>
                  </TableRow>
                ) : (
                  payrollData.map((row) => {
                    const adjustmentCount = row.adjustments_detail?.length || 0
                    const totalAdj = (row.adjustments_detail || []).reduce(
                      (sum, item) => sum + Number.parseFloat(String(item.amount ?? 0)),
                      0
                    )
                    const isPositive = totalAdj >= 0

                    return (
                      <TableRow key={String(row.employee_id)}>
                        <TableCell className='font-semibold'>{row.user_name || 'N/A'}</TableCell>
                        <TableCell className='text-center'>
                          {(row.current_rate || 0) > 0 ? (
                            <span className='rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary'>
                              ${row.current_rate}/hr
                            </span>
                          ) : (
                            <span className='text-muted-foreground'>-</span>
                          )}
                        </TableCell>
                        <TableCell className='text-center'>{(row.total_hours || 0).toFixed(2)}h</TableCell>
                        <TableCell className='text-center'>
                          {adjustmentCount === 0 ? (
                            <span className='text-muted-foreground'>-</span>
                          ) : (
                            <Button
                              variant='outline'
                              className={`h-9 rounded-[6px] ${
                                isPositive ? 'text-emerald-600' : 'text-rose-600'
                              }`}
                              onClick={() => {
                                setSelectedAdjustments(row.adjustments_detail || [])
                                setSelectedEmployee(row)
                                setShowAdjustmentDetailModal(true)
                              }}
                            >
                              {m.view} ({adjustmentCount})
                            </Button>
                          )}
                        </TableCell>
                        <TableCell className='text-right font-semibold'>
                          ${formatMoney(getBaseSalaryValue(row))}
                        </TableCell>
                        <TableCell className='text-right'>
                          {editingCell?.employee_id === row.employee_id &&
                          editingCell.field === 'net_salary' ? (
                            <div className='ml-auto flex w-fit items-center gap-2'>
                              <Input
                                type='number'
                                className='h-9 w-[110px] rounded-[6px] text-right'
                                value={editingCell.value}
                                onChange={(event) =>
                                  setEditingCell((prev) =>
                                    prev ? { ...prev, value: event.target.value } : prev
                                  )
                                }
                                onKeyDown={(event) => {
                                  if (event.key === 'Enter') {
                                    void handleSaveInlineField(row)
                                  }
                                  if (event.key === 'Escape') setEditingCell(null)
                                }}
                                autoFocus
                              />
                              <Button
                                size='sm'
                                className='h-9 rounded-[6px]'
                                disabled={savingInline}
                                onClick={() => void handleSaveInlineField(row)}
                              >
                                {m.save}
                              </Button>
                            </div>
                          ) : (
                            <button
                              type='button'
                              className='ml-auto inline-flex items-center gap-1 font-semibold'
                              onClick={() =>
                                setEditingCell({
                                  employee_id: row.employee_id,
                                  field: 'net_salary',
                                  value: String(row.net_salary || 0),
                                })
                              }
                              title={m.clickToEdit}
                            >
                              ${formatMoney(row.net_salary || 0)}
                              <Pencil className='size-3 text-muted-foreground' />
                            </button>
                          )}
                        </TableCell>
                        <TableCell className='text-right'>
                          {editingCell?.employee_id === row.employee_id &&
                          editingCell.field === 'company_tax' ? (
                            <div className='ml-auto flex w-fit items-center gap-2'>
                              <Input
                                type='number'
                                className='h-9 w-[110px] rounded-[6px] text-right'
                                value={editingCell.value}
                                onChange={(event) =>
                                  setEditingCell((prev) =>
                                    prev ? { ...prev, value: event.target.value } : prev
                                  )
                                }
                                onKeyDown={(event) => {
                                  if (event.key === 'Enter') {
                                    void handleSaveInlineField(row)
                                  }
                                  if (event.key === 'Escape') setEditingCell(null)
                                }}
                                autoFocus
                              />
                              <Button
                                size='sm'
                                className='h-9 rounded-[6px]'
                                disabled={savingInline}
                                onClick={() => void handleSaveInlineField(row)}
                              >
                                {m.save}
                              </Button>
                            </div>
                          ) : (
                            <button
                              type='button'
                              className='ml-auto inline-flex items-center gap-1 font-semibold'
                              onClick={() =>
                                setEditingCell({
                                  employee_id: row.employee_id,
                                  field: 'company_tax',
                                  value: String(row.company_tax || 0),
                                })
                              }
                              title={m.clickToEdit}
                            >
                              ${formatMoney(row.company_tax || 0)}
                              <Pencil className='size-3 text-muted-foreground' />
                            </button>
                          )}
                        </TableCell>
                        <TableCell className='text-right font-semibold text-primary'>
                          ${formatMoney(getPayrollTotalValue(row))}
                        </TableCell>
                        <TableCell>
                          <div className='flex justify-center gap-2'>
                            <Button
                              variant='outline'
                              className='h-9 rounded-[6px]'
                              onClick={() => void openEditRateModal(row)}
                            >
                              {m.edit}
                            </Button>
                            <Button
                              variant='outline'
                              className='h-9 rounded-[6px]'
                              onClick={() => void openLogModal(row)}
                            >
                              {m.log}
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })
                )}
              </TableBody>
            </Table>
          </div>
        </div>
      </Main>

      <Dialog open={showSetRateModal} onOpenChange={setShowSetRateModal}>
        <DialogContent className='rounded-[6px] sm:max-w-3xl'>
          <DialogHeader>
            <DialogTitle>{m.setRateModal.title}</DialogTitle>
          </DialogHeader>
          <div className='space-y-5'>
            <div className='space-y-2'>
              <Label>{m.setRateModal.selectEmployees}</Label>
              <div className='max-h-72 overflow-y-auto rounded-[6px] border p-4'>
                <label className='mb-3 flex items-center gap-2 border-b pb-3 font-medium'>
                  <input
                    type='checkbox'
                    checked={
                      rateForm.employee_ids.length === payrollData.length &&
                      payrollData.length > 0
                    }
                    onChange={toggleAllForRate}
                  />
                  <span>
                    {m.setRateModal.selectAll} ({payrollData.length})
                  </span>
                </label>
                <div className='grid gap-2 md:grid-cols-2'>
                  {payrollData.map((employee) => (
                    <label
                      key={String(employee.employee_id)}
                      className='flex items-center gap-2 rounded-[6px] border p-3'
                    >
                      <input
                        type='checkbox'
                        checked={rateForm.employee_ids.includes(employee.employee_id)}
                        onChange={() => toggleEmployeeForRate(employee.employee_id)}
                      />
                      <span className='text-sm'>
                        {employee.user_name}
                        {(employee.current_rate || 0) > 0 ? (
                          <small className='ml-1 text-muted-foreground'>
                            (${employee.current_rate}/hr)
                          </small>
                        ) : null}
                      </span>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            <div className='grid gap-4 md:grid-cols-2'>
              <div className='space-y-2'>
                <Label>{m.setRateModal.selectTier}</Label>
                <Select
                  value={rateForm.salary_tier_id || '__empty__'}
                  onValueChange={(value) =>
                    setRateForm((prev) => ({
                      ...prev,
                      salary_tier_id: value === '__empty__' ? '' : value,
                      custom_hourly_rate: '',
                    }))
                  }
                >
                  <SelectTrigger className='h-11 rounded-[6px]'>
                    <SelectValue placeholder={m.setRateModal.selectTier} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='__empty__'>-- Select --</SelectItem>
                    {tiers.map((tier) => (
                      <SelectItem key={String(tier.id)} value={String(tier.id)}>
                        {tier.name} - ${tier.hourly_rate}/hr
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <Label>{m.setRateModal.customRate}</Label>
                <Input
                  type='number'
                  className='h-11 rounded-[6px]'
                  placeholder='15.00'
                  value={rateForm.custom_hourly_rate}
                  onChange={(event) =>
                    setRateForm((prev) => ({
                      ...prev,
                      custom_hourly_rate: event.target.value,
                      salary_tier_id: '',
                    }))
                  }
                />
              </div>
            </div>

            <div className='space-y-2'>
              <Label>{m.setRateModal.effectiveFrom}</Label>
              <Input
                type='date'
                className='h-11 rounded-[6px]'
                value={rateForm.effective_date}
                onChange={(event) =>
                  setRateForm((prev) => ({ ...prev, effective_date: event.target.value }))
                }
              />
            </div>
          </div>

          <DialogFooter>
            <Button
              variant='outline'
              className='h-11 rounded-[6px]'
              onClick={() => setShowSetRateModal(false)}
            >
              {m.cancel}
            </Button>
            <Button className='h-11 rounded-[6px]' onClick={() => void handleSetRate()} disabled={saving}>
              {saving
                ? m.setRateModal.setting
                : `${m.setRateModal.setRateBtn} (${rateForm.employee_ids.length})`}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={showEditRateModal} onOpenChange={setShowEditRateModal}>
        <DialogContent className='rounded-[6px] sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>
              {m.editRateModal.title}
              {selectedEmployee?.user_name ? ` - ${selectedEmployee.user_name}` : ''}
            </DialogTitle>
          </DialogHeader>
          <div className='space-y-4'>
            <div className='space-y-2'>
              <Label>{m.editRateModal.hourlyRate}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                placeholder='15.00'
                value={rateForm.custom_hourly_rate}
                onChange={(event) =>
                  setRateForm((prev) => ({
                    ...prev,
                    custom_hourly_rate: event.target.value,
                    salary_tier_id: '',
                  }))
                }
              />
            </div>

            <div className='space-y-2'>
              <Label>{m.setRateModal.effectiveFrom}</Label>
              <Input
                type='date'
                className='h-11 rounded-[6px]'
                value={rateForm.effective_date}
                onChange={(event) =>
                  setRateForm((prev) => ({ ...prev, effective_date: event.target.value }))
                }
              />
            </div>

            <div className='space-y-2'>
              <Label>{m.editRateModal.note}</Label>
              <textarea
                className='min-h-[92px] w-full rounded-[6px] border bg-background px-3 py-2 text-sm'
                placeholder={m.editRateModal.reasonPlaceholder}
                value={rateForm.note}
                onChange={(event) =>
                  setRateForm((prev) => ({ ...prev, note: event.target.value }))
                }
              />
            </div>
          </div>

          <DialogFooter>
            <Button
              variant='outline'
              className='h-11 rounded-[6px]'
              onClick={() => setShowEditRateModal(false)}
            >
              {m.cancel}
            </Button>
            <Button className='h-11 rounded-[6px]' onClick={() => void handleEditRate()} disabled={saving}>
              {saving ? m.editRateModal.saving : m.save}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={showLogModal} onOpenChange={setShowLogModal}>
        <DialogContent className='rounded-[6px] sm:max-w-2xl'>
          <DialogHeader>
            <DialogTitle>
              {m.salaryLog.title}
              {selectedEmployee?.user_name ? ` - ${selectedEmployee.user_name}` : ''}
            </DialogTitle>
          </DialogHeader>
          {salaryLog.length === 0 ? (
            <div className='py-10 text-center text-muted-foreground'>{m.salaryLog.noHistory}</div>
          ) : (
            <div className='space-y-3'>
              {salaryLog.map((item) => (
                <div
                  key={String(item.id)}
                  className={`rounded-[6px] border p-4 ${
                    item.deleted_at ? 'opacity-70' : ''
                  }`}
                >
                  <div className='font-semibold'>
                    {item.tier
                      ? `${item.tier.name} - $${item.tier.hourly_rate}/hr`
                      : `${m.salaryLog.custom}: $${item.custom_hourly_rate}/hr`}
                  </div>
                  <div className='mt-1 text-sm text-muted-foreground'>
                    {m.salaryLog.from}: {item.effective_date?.split('T')[0] || 'N/A'}
                    {item.deleted_at
                      ? ` • ${m.salaryLog.ended}: ${item.deleted_at.split('T')[0] || 'N/A'}`
                      : ''}
                  </div>
                  {!item.deleted_at ? (
                    <div className='mt-2 inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700'>
                      {m.salaryLog.current}
                    </div>
                  ) : null}
                </div>
              ))}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={showAdjustmentModal} onOpenChange={setShowAdjustmentModal}>
        <DialogContent className='rounded-[6px] sm:max-w-3xl'>
          <DialogHeader>
            <DialogTitle>{m.adjustmentModal.title}</DialogTitle>
          </DialogHeader>
          <div className='space-y-5'>
            <div className='space-y-2'>
              <Label>{m.setRateModal.selectEmployees}</Label>
              <div className='max-h-72 overflow-y-auto rounded-[6px] border p-4'>
                <label className='mb-3 flex items-center gap-2 border-b pb-3 font-medium'>
                  <input
                    type='checkbox'
                    checked={
                      adjustmentForm.employee_ids.length === payrollData.length &&
                      payrollData.length > 0
                    }
                    onChange={toggleAllForAdjustment}
                  />
                  <span>
                    {m.setRateModal.selectAll} ({payrollData.length})
                  </span>
                </label>
                <div className='grid gap-2 md:grid-cols-2'>
                  {payrollData.map((employee) => (
                    <label
                      key={String(employee.employee_id)}
                      className='flex items-center gap-2 rounded-[6px] border p-3'
                    >
                      <input
                        type='checkbox'
                        checked={adjustmentForm.employee_ids.includes(employee.employee_id)}
                        onChange={() => toggleEmployeeForAdjustment(employee.employee_id)}
                      />
                      <span className='text-sm'>{employee.user_name}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            <div className='space-y-2'>
              <Label>{m.adjustmentModal.type}</Label>
              <Input
                className='h-11 rounded-[6px]'
                placeholder={m.adjustmentModal.typePlaceholder}
                value={adjustmentForm.name}
                onChange={(event) =>
                  setAdjustmentForm((prev) => ({ ...prev, name: event.target.value }))
                }
              />
            </div>

            <div className='grid gap-4 md:grid-cols-2'>
              <div className='space-y-2'>
                <Label>{m.adjustmentModal.amount}</Label>
                <Input
                  type='number'
                  className='h-11 rounded-[6px]'
                  placeholder='100'
                  value={adjustmentForm.amount}
                  onChange={(event) =>
                    setAdjustmentForm((prev) => ({ ...prev, amount: event.target.value }))
                  }
                />
              </div>
              <div className='space-y-2'>
                <Label>{m.adjustmentModal.action}</Label>
                <Select
                  value={adjustmentForm.category}
                  onValueChange={(value) =>
                    setAdjustmentForm((prev) => ({ ...prev, category: value }))
                  }
                >
                  <SelectTrigger className='h-11 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='add'>{m.adjustmentModal.addReward}</SelectItem>
                    <SelectItem value='deduct'>{m.adjustmentModal.deductPenalty}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className='space-y-2'>
              <Label>{m.adjustmentModal.date}</Label>
              <Input
                type='date'
                className='h-11 rounded-[6px]'
                value={adjustmentForm.date}
                onChange={(event) =>
                  setAdjustmentForm((prev) => ({ ...prev, date: event.target.value }))
                }
              />
            </div>
          </div>

          <DialogFooter>
            <Button
              variant='outline'
              className='h-11 rounded-[6px]'
              onClick={() => setShowAdjustmentModal(false)}
            >
              {m.cancel}
            </Button>
            <Button
              className='h-11 rounded-[6px]'
              onClick={() => void handleCreateAdjustment()}
              disabled={saving}
            >
              {saving
                ? m.adjustmentModal.processing
                : `${
                    adjustmentForm.category === 'deduct'
                      ? m.adjustmentModal.deduct
                      : m.adjustmentModal.add
                  } (${adjustmentForm.employee_ids.length})`}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={showAdjustmentDetailModal} onOpenChange={setShowAdjustmentDetailModal}>
        <DialogContent className='rounded-[6px] sm:max-w-2xl'>
          <DialogHeader>
            <DialogTitle>
              {m.adjustmentDetail.title}
              {selectedEmployee?.user_name ? ` - ${selectedEmployee.user_name}` : ''}
            </DialogTitle>
          </DialogHeader>
          {selectedAdjustments.length === 0 ? (
            <div className='py-10 text-center text-muted-foreground'>
              {m.adjustmentDetail.noAdjustments}
            </div>
          ) : (
            <div className='overflow-x-auto rounded-[6px] border'>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{m.adjustmentModal.date}</TableHead>
                    <TableHead>{m.adjustmentDetail.typeReason}</TableHead>
                    <TableHead className='text-right'>{m.adjustmentModal.amount}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {selectedAdjustments.map((item, index) => (
                    <TableRow key={`${item.type || 'adjustment'}-${index}`}>
                      <TableCell>{item.date || 'N/A'}</TableCell>
                      <TableCell>
                        <div className='font-semibold'>{item.type || 'N/A'}</div>
                        {item.reason ? (
                          <div className='text-sm text-muted-foreground'>{item.reason}</div>
                        ) : null}
                      </TableCell>
                      <TableCell
                        className={`text-right font-semibold ${
                          Number(item.amount || 0) > 0 ? 'text-emerald-600' : 'text-rose-600'
                        }`}
                      >
                        {Number(item.amount || 0) > 0 ? '+' : ''}
                        {formatMoney(Number(item.amount || 0))}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
          <DialogFooter>
            <Button
              variant='outline'
              className='h-11 rounded-[6px]'
              onClick={() => setShowAdjustmentDetailModal(false)}
            >
              {m.close}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PayrollHelpDialog
        open={showHelpModal}
        onOpenChange={setShowHelpModal}
        title={m.guide.title}
        closeLabel={m.guide.close}
        steps={m.guide.steps}
      />
    </>
  )
}

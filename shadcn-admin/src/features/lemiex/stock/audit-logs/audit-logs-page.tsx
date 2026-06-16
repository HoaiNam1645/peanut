'use client'

import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  checkVariantProductions,
  fetchStockAuditLogFilterOptions,
  fetchStockAuditLogs,
  type StockAuditLogFilters,
  type StockAuditLogFilterOptions,
  type StockAuditLogPagination,
  type StockAuditLogRecord,
  type VariantProductionRecord,
} from '@/services/stock-audit-log/api'
import { cn } from '@/lib/utils'
import { VariantProductionsDialog } from './variant-productions-dialog'

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'Stock Audit Logs',
  subtitle: 'Track all stock changes and history',
  loading: 'Loading audit logs...',
  noLogs: 'No audit logs found',
  failedToLoadLogs: 'Failed to load audit logs',
  failedToLoadOptions: 'Failed to load filter options',
  failedToCheckProductions: 'Failed to check variant productions',
  searchVariant: 'Variant ID',
  enterVariantId: 'Enter variant ID...',
  style: 'Style',
  allStyles: 'All Styles',
  color: 'Color',
  allColors: 'All Colors',
  size: 'Size',
  allSizes: 'All Sizes',
  action: 'Action',
  allActions: 'All Actions',
  orderId: 'Order ID',
  enterOrderId: 'Enter order ID...',
  dateFrom: 'Date From',
  dateTo: 'Date To',
  dateTime: 'Date/Time',
  user: 'User',
  product: 'Product',
  before: 'Before',
  after: 'After',
  change: 'Change',
  reason: 'Reason',
  stockIncrease: 'Stock Increase',
  stockDecrease: 'Stock Decrease',
  stockAdjustment: 'Stock Adjustment',
  stockMapped: 'Stock Mapped',
  stockRestored: 'Stock Restored',
  manualAdjustment: 'Manual Adjustment',
  system: 'System',
  na: 'N/A',
  clickToCheckProductions: 'Click to check productions',
  variantProductions: {
    title: 'Productions for Variant',
    variantId: 'Variant ID',
    productionId: 'Production #',
    orderId: 'Order ID',
    orderRef: 'Order Ref',
    quantity: 'Quantity',
    units: 'units',
    noProductions: 'No productions found for this variant',
    close: 'Close',
    status: {
      pending: 'Pending',
      pickup: 'Pickup',
      mapped: 'Mapped',
      completed: 'Completed',
      cancelled: 'Cancelled',
      unknown: 'Unknown',
    },
  },
}

function parseState(searchParams: URLSearchParams) {
  return {
    page: Number(searchParams.get('page') || 1),
    perPage: Number(searchParams.get('per_page') || 20),
    filters: {
      variant_id: searchParams.get('variant_id') || '',
      style: searchParams.get('style') || '',
      color: searchParams.get('color') || '',
      size: searchParams.get('size') || '',
      action: searchParams.get('action') || '',
      order_id: searchParams.get('order_id') || '',
      date_from: searchParams.get('date_from') || '',
      date_to: searchParams.get('date_to') || '',
    } satisfies StockAuditLogFilters,
  }
}

function buildSearchParams(state: {
  page: number
  perPage: number
  filters: StockAuditLogFilters
}) {
  const params = new URLSearchParams()

  if (state.page > 1) params.set('page', String(state.page))
  if (state.perPage !== 20) params.set('per_page', String(state.perPage))

  Object.entries(state.filters).forEach(([key, value]) => {
    if (!value) return
    params.set(key, value)
  })

  return params
}

function formatDateTime(dateString?: string | null, empty = 'N/A') {
  if (!dateString) return empty

  try {
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    })
  } catch {
    return empty
  }
}

function getActionTone(action?: string | null) {
  switch (action) {
    case 'increase':
      return 'bg-emerald-500/10 text-emerald-700 border-emerald-200'
    case 'decrease':
      return 'bg-rose-500/10 text-rose-700 border-rose-200'
    case 'adjust':
      return 'bg-amber-500/10 text-amber-700 border-amber-200'
    case 'map':
      return 'bg-blue-500/10 text-blue-700 border-blue-200'
    case 'restore':
      return 'bg-violet-500/10 text-violet-700 border-violet-200'
    default:
      return 'bg-muted text-muted-foreground'
  }
}

function getActionLabel(
  action: string | null | undefined,
  m: typeof fallbackMessages
) {
  switch (action) {
    case 'increase':
      return m.stockIncrease
    case 'decrease':
      return m.stockDecrease
    case 'adjust':
      return m.stockAdjustment
    case 'map':
      return m.stockMapped
    case 'restore':
      return m.stockRestored
    case 'manual':
      return m.manualAdjustment
    default:
      return action || m.na
  }
}

export function LemiexStockAuditLogsPage() {
  const { messages } = useI18n()
  const m = messages.stock?.auditLogs || fallbackMessages

  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const queryKey = searchParams.toString()
  const state = useMemo(
    () => parseState(new URLSearchParams(queryKey)),
    [queryKey]
  )

  const [logs, setLogs] = useState<StockAuditLogRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [pagination, setPagination] = useState<StockAuditLogPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
  })
  const [filterOptions, setFilterOptions] = useState<StockAuditLogFilterOptions>({
    styles: [],
    colors: [],
    sizes: [],
    actions: [],
  })
  const [productionsOpen, setProductionsOpen] = useState(false)
  const [selectedVariantId, setSelectedVariantId] = useState<string | null>(null)
  const [variantProductions, setVariantProductions] = useState<
    VariantProductionRecord[]
  >([])

  function updateUrl(nextState: {
    page: number
    perPage: number
    filters: StockAuditLogFilters
  }) {
    const params = buildSearchParams(nextState)
    const next = params.toString()
    router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false })
  }

  function updateFilters(
    updater: (filters: StockAuditLogFilters) => StockAuditLogFilters
  ) {
    updateUrl({
      page: 1,
      perPage: state.perPage,
      filters: updater(state.filters),
    })
  }

  useEffect(() => {
    let active = true

    async function loadFilterOptions() {
      try {
        const next = await fetchStockAuditLogFilterOptions()
        if (!active) return
        setFilterOptions(next)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.failedToLoadOptions)
      }
    }

    void loadFilterOptions()

    return () => {
      active = false
    }
  }, [m.failedToLoadOptions])

  useEffect(() => {
    let active = true

    async function loadLogs() {
      setLoading(true)

      try {
        const result = await fetchStockAuditLogs({
          page: state.page,
          per_page: state.perPage,
          ...state.filters,
        })

        if (!active) return
        setLogs(result.logs)
        setPagination(result.pagination)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.failedToLoadLogs)
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadLogs()

    return () => {
      active = false
    }
  }, [m.failedToLoadLogs, state])

  const columns = useMemo<ColumnDef<StockAuditLogRecord>[]>(
    () => [
      {
        id: 'created_at',
        header: m.dateTime,
        cell: ({ row }) => (
          <div className='text-sm'>{formatDateTime(row.original.created_at, m.na)}</div>
        ),
        meta: {
          thClassName: 'min-w-[170px]',
          tdClassName: 'min-w-[170px]',
        },
      },
      {
        id: 'user',
        header: m.user,
        cell: ({ row }) => (
          <span className='text-sm'>{row.original.user?.username || m.system}</span>
        ),
        meta: {
          thClassName: 'min-w-[130px]',
          tdClassName: 'min-w-[130px]',
        },
      },
      {
        id: 'variant_id',
        header: m.searchVariant,
        cell: ({ row }) => (
          <button
            type='button'
            className='text-sm font-semibold text-primary hover:underline'
            title={m.clickToCheckProductions}
            onClick={async () => {
              const variantId = row.original.variant_id
              if (!variantId) return

              try {
                const productions = await checkVariantProductions(variantId)
                setSelectedVariantId(variantId)
                setVariantProductions(productions)
                setProductionsOpen(true)
              } catch (error) {
                toast.error(
                  error instanceof Error
                    ? error.message
                    : m.failedToCheckProductions
                )
              }
            }}
          >
            {row.original.variant_id || m.na}
          </button>
        ),
        meta: {
          thClassName: 'min-w-[140px]',
          tdClassName: 'min-w-[140px]',
        },
      },
      {
        id: 'product',
        header: m.product,
        cell: ({ row }) => (
          <div className='space-y-1'>
            <div className='text-sm font-semibold'>
              {row.original.product?.name || m.na}
            </div>
            <div className='text-xs text-muted-foreground'>
              {row.original.product?.brand || ''}
            </div>
          </div>
        ),
        meta: {
          thClassName: 'min-w-[220px]',
          tdClassName: 'min-w-[220px]',
        },
      },
      {
        id: 'style',
        header: m.style,
        cell: ({ row }) => (
          <span className='text-sm text-muted-foreground'>
            {row.original.product?.style || m.na}
          </span>
        ),
        meta: {
          thClassName: 'min-w-[100px] text-center',
          tdClassName: 'min-w-[100px] text-center',
        },
      },
      {
        id: 'color',
        header: m.color,
        cell: ({ row }) => (
          <span className='text-sm'>{row.original.variant?.color || m.na}</span>
        ),
        meta: {
          thClassName: 'min-w-[110px] text-center',
          tdClassName: 'min-w-[110px] text-center',
        },
      },
      {
        id: 'size',
        header: m.size,
        cell: ({ row }) => (
          <Badge variant='outline'>{row.original.variant?.size || m.na}</Badge>
        ),
        meta: {
          thClassName: 'min-w-[90px] text-center',
          tdClassName: 'min-w-[90px] text-center',
        },
      },
      {
        id: 'action',
        header: m.action,
        cell: ({ row }) => (
          <Badge variant='outline' className={cn(getActionTone(row.original.action))}>
            {getActionLabel(row.original.action, m)}
          </Badge>
        ),
        meta: {
          thClassName: 'min-w-[150px] text-center',
          tdClassName: 'min-w-[150px] text-center',
        },
      },
      {
        id: 'before_quantity',
        header: m.before,
        cell: ({ row }) => (
          <span className='font-medium'>{row.original.before_quantity ?? m.na}</span>
        ),
        meta: {
          thClassName: 'min-w-[90px] text-center',
          tdClassName: 'min-w-[90px] text-center',
        },
      },
      {
        id: 'after_quantity',
        header: m.after,
        cell: ({ row }) => (
          <span className='font-medium'>{row.original.after_quantity ?? m.na}</span>
        ),
        meta: {
          thClassName: 'min-w-[90px] text-center',
          tdClassName: 'min-w-[90px] text-center',
        },
      },
      {
        id: 'change',
        header: m.change,
        cell: ({ row }) => {
          const change = row.original.change

          return (
            <span
              className={cn(
                'font-semibold',
                typeof change !== 'number'
                  ? 'text-muted-foreground'
                  : change > 0
                    ? 'text-emerald-600'
                    : change < 0
                      ? 'text-rose-600'
                      : 'text-muted-foreground'
              )}
            >
              {typeof change === 'number' ? `${change > 0 ? '+' : ''}${change}` : m.na}
            </span>
          )
        },
        meta: {
          thClassName: 'min-w-[100px] text-center',
          tdClassName: 'min-w-[100px] text-center',
        },
      },
      {
        id: 'reason',
        header: m.reason,
        cell: ({ row }) => (
          <div className='max-w-[320px] text-sm text-muted-foreground'>
            {row.original.reason || m.na}
          </div>
        ),
        meta: {
          thClassName: 'min-w-[260px]',
          tdClassName: 'min-w-[260px]',
        },
      },
    ],
    [m]
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

      <Main
        fluid
        className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'
      >
        <div className='space-y-6'>
          <div className='space-y-1'>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            <p className='text-sm text-muted-foreground'>{m.subtitle}</p>
          </div>

          <div className='rounded-[6px] border bg-card p-5 shadow-sm'>
            <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.searchVariant}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  value={state.filters.variant_id}
                  placeholder={m.enterVariantId}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      variant_id: event.target.value,
                    }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.style}</label>
                <Select
                  value={state.filters.style || ALL_VALUE}
                  onValueChange={(value) =>
                    updateFilters((prev) => ({
                      ...prev,
                      style: value === ALL_VALUE ? '' : value,
                    }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>{m.allStyles}</SelectItem>
                    {filterOptions.styles.filter(Boolean).map((style) => (
                      <SelectItem key={style} value={style}>
                        {style}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.color}</label>
                <Select
                  value={state.filters.color || ALL_VALUE}
                  onValueChange={(value) =>
                    updateFilters((prev) => ({
                      ...prev,
                      color: value === ALL_VALUE ? '' : value,
                    }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>{m.allColors}</SelectItem>
                    {filterOptions.colors.filter(Boolean).map((color) => (
                      <SelectItem key={color} value={color}>
                        {color}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.size}</label>
                <Select
                  value={state.filters.size || ALL_VALUE}
                  onValueChange={(value) =>
                    updateFilters((prev) => ({
                      ...prev,
                      size: value === ALL_VALUE ? '' : value,
                    }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>{m.allSizes}</SelectItem>
                    {filterOptions.sizes.filter(Boolean).map((size) => (
                      <SelectItem key={size} value={size}>
                        {size}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.action}</label>
                <Select
                  value={state.filters.action || ALL_VALUE}
                  onValueChange={(value) =>
                    updateFilters((prev) => ({
                      ...prev,
                      action: value === ALL_VALUE ? '' : value,
                    }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>{m.allActions}</SelectItem>
                    {filterOptions.actions.map((action) => (
                      <SelectItem key={action} value={action}>
                        {getActionLabel(action, m)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2 xl:col-span-2'>
                <label className='text-sm font-medium'>{m.orderId}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  value={state.filters.order_id}
                  placeholder={m.enterOrderId}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      order_id: event.target.value,
                    }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.dateFrom}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  type='date'
                  value={state.filters.date_from}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      date_from: event.target.value,
                    }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.dateTo}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  type='date'
                  value={state.filters.date_to}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      date_to: event.target.value,
                    }))
                  }
                />
              </div>
            </div>
          </div>

          <LemiexDataTable
            columns={columns}
            data={logs}
            page={state.page}
            pageSize={state.perPage}
            total={pagination.total}
            loading={loading}
            loadingText={m.loading}
            emptyText={m.noLogs}
            getRowId={(row) => String(row.id)}
            onPageChange={(page) =>
              updateUrl({
                page,
                perPage: state.perPage,
                filters: state.filters,
              })
            }
            onPageSizeChange={(perPage) =>
              updateUrl({
                page: 1,
                perPage,
                filters: state.filters,
              })
            }
          />
        </div>
      </Main>

      <VariantProductionsDialog
        open={productionsOpen}
        variantId={selectedVariantId}
        productions={variantProductions}
        messages={m.variantProductions}
        onOpenChange={(open) => {
          setProductionsOpen(open)
          if (!open) {
            setSelectedVariantId(null)
            setVariantProductions([])
          }
        }}
      />
    </>
  )
}

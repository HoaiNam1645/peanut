'use client'

import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import {
  AlertTriangle,
  ArrowRight,
  ChevronRight,
  Download,
  Layers3,
  PackageCheck,
  Tags,
} from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  exportShortageReport,
  fetchShortageReport,
  type ShortageFilters,
  type ShortageOrder,
  type ShortagePagination,
  type ShortageSummary,
  type ShortageVariant,
} from '@/services/shortage/api'
import { useI18n } from '@/context/i18n-provider'
import { cn } from '@/lib/utils'

const ALL_VALUE = '__all__'

const DEFAULT_FILTERS: ShortageFilters = {
  pending_reason: '',
  order_id: '',
  variant_id: '',
  date_from: '',
  date_to: '',
  sort_by: 'seller_username',
  sort_order: 'asc',
}

const fallbackMessages = {
  title: 'Shortage Report',
  subtitleWithCount: '{count} orders pending fulfillment',
  subtitleAllGood: 'No pending orders',
  viewByVariant: 'View by Variant',
  exportCsv: 'Export CSV',
  exporting: 'Exporting shortage report...',
  exportSuccess: 'Report exported successfully!',
  exportFailed: 'Failed to export report',
  failedToLoadReport: 'Failed to load shortage report',
  loading: 'Loading pending orders...',
  noPendingOrders: 'No pending orders found',
  noPendingOrdersDesc:
    'All orders have been processed or have sufficient stock.',
  totalPendingOrders: 'Total Pending Orders',
  ordersWithShortage: 'Orders With Shortage',
  variantsAffected: 'Variants Affected',
  totalShortage: 'Total Shortage',
  searchOrder: 'Search Order',
  orderIdRefIdPlaceholder: 'Order ID / Ref ID...',
  searchVariant: 'Search Variant',
  variantIdPlaceholder: 'Variant ID...',
  pendingReason: 'Pending Reason',
  fromDate: 'From Date',
  toDate: 'To Date',
  sortBy: 'Sort By',
  clearFilters: 'Clear Filters',
  orderId: 'Order ID',
  refId: 'Ref ID',
  seller: 'Seller',
  items: 'Items',
  shortage: 'Shortage',
  daysPending: 'Days Pending',
  action: 'Action',
  view: 'View',
  day: 'day',
  days: 'days',
  awaitingProcessing: 'Awaiting Processing',
  awaitingProcessingDesc:
    'Stock is available for this order. The system is processing it and will allocate stock shortly.',
  missingFiles: 'Missing Files',
  noItems: 'No Items',
  noItemsDesc:
    'This order has no items. Please check the order details.',
  unknownReason: 'Unknown Reason',
  unknownReasonDesc:
    'This order is pending for an unknown reason. It may be on manual hold or waiting for other business logic.',
  variantTable: {
    title: 'Shortage Variants',
    noVariants: 'No shortage variants',
    variantId: 'Variant ID',
    style: 'Style',
    color: 'Color',
    size: 'Size',
    stock: 'Stock',
    demand: 'Demand',
    shortage: 'Shortage',
  },
  status: {
    shortage: 'Stock Shortage',
    missing_files: 'Missing Files',
    awaiting_allocation: 'Awaiting Allocation',
    no_items: 'No Items',
    unknown: 'Unknown',
  },
  sortOptions: {
    seller_username: 'Seller',
    days_pending: 'Days Pending',
    shortage: 'Shortage Amount',
    created_at: 'Created Date',
  },
}

type ShortagePageMessages = typeof fallbackMessages

function parseState(searchParams: URLSearchParams) {
  return {
    page: Number(searchParams.get('page') || 1),
    perPage: Number(searchParams.get('per_page') || 50),
    filters: {
      pending_reason: searchParams.get('pending_reason') || '',
      order_id: searchParams.get('order_id') || '',
      variant_id: searchParams.get('variant_id') || '',
      date_from: searchParams.get('date_from') || '',
      date_to: searchParams.get('date_to') || '',
      sort_by: searchParams.get('sort_by') || DEFAULT_FILTERS.sort_by,
      sort_order:
        (searchParams.get('sort_order') as 'asc' | 'desc' | null) ||
        DEFAULT_FILTERS.sort_order,
    } satisfies ShortageFilters,
  }
}

function buildSearchParams(state: {
  page: number
  perPage: number
  filters: ShortageFilters
}) {
  const params = new URLSearchParams()

  if (state.page > 1) params.set('page', String(state.page))
  if (state.perPage !== 50) params.set('per_page', String(state.perPage))

  Object.entries(state.filters).forEach(([key, value]) => {
    if (!value) return
    params.set(key, value)
  })

  return params
}

function formatMessage(template: string, values: Record<string, string | number>) {
  return Object.entries(values).reduce(
    (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
    template
  )
}

function getPendingReasonTone(reason: string) {
  switch (reason) {
    case 'shortage':
      return 'bg-rose-500/10 text-rose-700 border-rose-200'
    case 'missing_files':
      return 'bg-amber-500/10 text-amber-700 border-amber-200'
    case 'awaiting_allocation':
      return 'bg-blue-500/10 text-blue-700 border-blue-200'
    case 'no_items':
      return 'bg-orange-500/10 text-orange-700 border-orange-200'
    default:
      return 'bg-muted text-muted-foreground'
  }
}

function getDayBadgeTone(daysPending: number) {
  if (daysPending >= 14) return 'bg-rose-500/10 text-rose-700 border-rose-200'
  if (daysPending >= 7) return 'bg-amber-500/10 text-amber-700 border-amber-200'
  return 'bg-muted text-muted-foreground'
}

function renderExpandedContent(
  order: ShortageOrder,
  m: typeof fallbackMessages
) {
  if (order.pending_reason === 'shortage' && order.shortage_variants?.length) {
    return (
      <div className='space-y-3'>
        <div className='text-sm font-medium'>
          {m.variantTable.title} ({order.shortage_variants.length})
        </div>
        <div className='overflow-x-auto rounded-xl border'>
          <Table className='min-w-[760px]'>
            <TableHeader>
              <TableRow>
                <TableHead>{m.variantTable.variantId}</TableHead>
                <TableHead>{m.variantTable.style}</TableHead>
                <TableHead>{m.variantTable.color}</TableHead>
                <TableHead>{m.variantTable.size}</TableHead>
                <TableHead className='text-right'>{m.variantTable.stock}</TableHead>
                <TableHead className='text-right'>{m.variantTable.demand}</TableHead>
                <TableHead className='text-right'>{m.variantTable.shortage}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {order.shortage_variants.map((variant: ShortageVariant) => (
                <TableRow key={variant.variant_id}>
                  <TableCell>
                    <code className='rounded-md bg-muted px-2 py-1 text-xs font-semibold'>
                      {variant.variant_id}
                    </code>
                  </TableCell>
                  <TableCell>{variant.style || '-'}</TableCell>
                  <TableCell>
                    <span className='inline-flex items-center gap-2'>
                      <span className='size-2.5 rounded-full bg-foreground' />
                      {variant.color || '-'}
                    </span>
                  </TableCell>
                  <TableCell>
                    <Badge variant='outline'>{variant.size || '-'}</Badge>
                  </TableCell>
                  <TableCell className='text-right'>
                    <span
                      className={cn(
                        'font-medium',
                        (variant.stock || 0) === 0
                          ? 'text-rose-600'
                          : 'text-muted-foreground'
                      )}
                    >
                      {variant.stock || 0}
                    </span>
                  </TableCell>
                  <TableCell className='text-right text-amber-600'>
                    {variant.pending_demand || 0}
                  </TableCell>
                  <TableCell className='text-right text-rose-600'>
                    -{variant.shortage || 0}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>
    )
  }

  if (order.pending_reason === 'missing_files' && order.missing_files?.length) {
    return (
      <div className='space-y-3'>
        <div className='text-sm font-medium'>{m.missingFiles}</div>
        <div className='flex flex-wrap gap-2'>
          {order.missing_files.map((file) => (
            <Badge key={file} variant='outline'>
              {file}
            </Badge>
          ))}
        </div>
      </div>
    )
  }

  if (order.pending_reason === 'awaiting_allocation') {
    return (
      <div className='rounded-xl border border-blue-200 bg-blue-500/5 p-4 text-sm text-blue-700'>
        <div className='font-medium'>{m.awaitingProcessing}</div>
        <p className='mt-1'>{m.awaitingProcessingDesc}</p>
      </div>
    )
  }

  if (order.pending_reason === 'no_items') {
    return (
      <div className='rounded-xl border border-orange-200 bg-orange-500/5 p-4 text-sm text-orange-700'>
        <div className='font-medium'>{m.noItems}</div>
        <p className='mt-1'>{m.noItemsDesc}</p>
      </div>
    )
  }

  return (
    <div className='rounded-xl border p-4 text-sm text-muted-foreground'>
      <div className='font-medium'>{m.unknownReason}</div>
      <p className='mt-1'>{m.unknownReasonDesc}</p>
    </div>
  )
}

export function LemiexShortageReportPage() {
  const { messages } = useI18n()
  const m = (messages.stock?.shortage || fallbackMessages) as ShortagePageMessages
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const queryKey = searchParams.toString()
  const state = useMemo(
    () => parseState(new URLSearchParams(queryKey)),
    [queryKey]
  )

  const [orders, setOrders] = useState<ShortageOrder[]>([])
  const [summary, setSummary] = useState<ShortageSummary>({
    total_pending_orders: 0,
    orders_with_shortage: 0,
    total_variants_shortage: 0,
    total_quantity_shortage: 0,
  })
  const [pagination, setPagination] = useState<ShortagePagination>({
    current_page: state.page,
    last_page: 1,
    per_page: state.perPage,
    total: 0,
  })
  const [expandedOrderId, setExpandedOrderId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)

  function updateUrl(nextState: {
    page: number
    perPage: number
    filters: ShortageFilters
  }) {
    const params = buildSearchParams(nextState)
    const next = params.toString()
    router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false })
  }

  function updateFilters(
    updater: (filters: ShortageFilters) => ShortageFilters
  ) {
    updateUrl({
      page: 1,
      perPage: state.perPage,
      filters: updater(state.filters),
    })
  }

  useEffect(() => {
    let active = true

    async function loadReport() {
      setLoading(true)
      try {
        const result = await fetchShortageReport({
          page: state.page,
          per_page: state.perPage,
          ...state.filters,
        })

        if (!active) return
        setOrders(result.orders)
        setSummary(result.summary)
        setPagination(result.pagination)
      } catch (error) {
        if (!active) return
        toast.error(
          error instanceof Error ? error.message : m.failedToLoadReport
        )
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadReport()

    return () => {
      active = false
    }
  }, [m.failedToLoadReport, state])

  const summaryItems = [
    {
      key: 'pending',
      title: m.totalPendingOrders,
      value: summary.total_pending_orders,
      icon: PackageCheck,
    },
    {
      key: 'shortage-orders',
      title: m.ordersWithShortage,
      value: summary.orders_with_shortage,
      icon: AlertTriangle,
    },
    {
      key: 'variants',
      title: m.variantsAffected,
      value: summary.total_variants_shortage,
      icon: Tags,
    },
    {
      key: 'quantity',
      title: m.totalShortage,
      value: summary.total_quantity_shortage,
      icon: Layers3,
    },
  ]

  const expandedOrder = useMemo(
    () => orders.find((order) => order.id === expandedOrderId) || null,
    [expandedOrderId, orders]
  )

  const columns = useMemo<ColumnDef<ShortageOrder>[]>(
    () => [
      {
        id: 'expand',
        header: '',
        cell: ({ row }) => {
          const isExpanded = expandedOrderId === row.original.id
          return (
            <Button
              variant='ghost'
              size='icon'
              onClick={() =>
                setExpandedOrderId((prev) =>
                  prev === row.original.id ? null : row.original.id
                )
              }
            >
              <ChevronRight
                className={cn('size-4 transition-transform', isExpanded && 'rotate-90')}
              />
            </Button>
          )
        },
        meta: {
          thClassName: 'w-14',
          tdClassName: 'w-14',
        },
      },
      {
        id: 'id',
        header: m.orderId,
        cell: ({ row }) => (
          <button
            type='button'
            className='font-semibold text-primary'
            onClick={() => router.push(`/lemiex/orders/${row.original.id}`)}
          >
            #{row.original.id}
          </button>
        ),
      },
      {
        id: 'ref_id',
        header: m.refId,
        cell: ({ row }) => (
          <code className='rounded-md bg-muted px-2 py-1 text-xs'>
            {row.original.ref_id || 'N/A'}
          </code>
        ),
      },
      {
        id: 'seller_username',
        header: m.seller,
        cell: ({ row }) => row.original.seller_username || 'N/A',
      },
      {
        id: 'total_items',
        header: m.items,
        cell: ({ row }) => row.original.total_items || 0,
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'pending_reason',
        header: m.pendingReason,
        cell: ({ row }) => (
          <Badge
            variant='outline'
            className={getPendingReasonTone(row.original.pending_reason || 'unknown')}
          >
            {m.status[(row.original.pending_reason || 'unknown') as keyof typeof m.status]}
          </Badge>
        ),
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'total_shortage',
        header: m.shortage,
        cell: ({ row }) =>
          row.original.total_shortage ? (
            <span className='font-medium text-rose-600'>
              -{row.original.total_shortage}
            </span>
          ) : (
            <span className='text-muted-foreground'>-</span>
          ),
        meta: {
          thClassName: 'text-right',
          tdClassName: 'text-right',
        },
      },
      {
        id: 'days_pending',
        header: m.daysPending,
        cell: ({ row }) => {
          const daysPending = Math.round(row.original.days_pending || 0)
          return (
            <Badge variant='outline' className={getDayBadgeTone(daysPending)}>
              {daysPending} {daysPending === 1 ? m.day : m.days}
            </Badge>
          )
        },
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'action',
        header: m.action,
        cell: ({ row }) => (
          <div className='flex justify-end'>
            <Button
              variant='outline'
              onClick={() => router.push(`/lemiex/orders/${row.original.id}`)}
            >
              {m.view}
            </Button>
          </div>
        ),
        meta: {
          thClassName: 'text-right',
          tdClassName: 'text-right',
        },
      },
    ],
    [expandedOrderId, m, router]
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
              <p className='text-sm text-muted-foreground'>
                {summary.total_pending_orders > 0
                  ? formatMessage(m.subtitleWithCount, {
                      count: summary.total_pending_orders,
                    })
                  : m.subtitleAllGood}
              </p>
            </div>

            <div className='flex flex-wrap gap-2'>
              <Button
                variant='outline'
                onClick={() => router.push('/lemiex/stock/shortage-by-variant')}
              >
                <ArrowRight className='mr-2 size-4' />
                {m.viewByVariant}
              </Button>
              <Button
                onClick={async () => {
                  try {
                    toast.info(m.exporting)
                    await exportShortageReport(state.filters)
                    toast.success(m.exportSuccess)
                  } catch (error) {
                    toast.error(
                      error instanceof Error ? error.message : m.exportFailed
                    )
                  }
                }}
                disabled={orders.length === 0}
              >
                <Download className='mr-2 size-4' />
                {m.exportCsv}
              </Button>
            </div>
          </div>

          <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
            {summaryItems.map((item) => {
              const Icon = item.icon
              return (
                <Card key={item.key} className='rounded-[6px] shadow-sm'>
                  <CardContent className='flex items-center gap-4 px-5 py-4'>
                    <div className='rounded-xl bg-primary/10 p-3 text-primary'>
                      <Icon className='size-5' />
                    </div>
                    <div>
                      <div className='text-sm text-muted-foreground'>
                        {item.title}
                      </div>
                      <div className='mt-1 text-2xl font-semibold tracking-tight'>
                        {item.value}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )
            })}
          </div>

          <div className='rounded-[6px] border bg-card p-5 shadow-sm'>
            <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-6'>
              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.searchOrder}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  value={state.filters.order_id}
                  placeholder={m.orderIdRefIdPlaceholder}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      order_id: event.target.value,
                    }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.searchVariant}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  value={state.filters.variant_id}
                  placeholder={m.variantIdPlaceholder}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      variant_id: event.target.value,
                    }))
                  }
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.pendingReason}</label>
                <Select
                  value={state.filters.pending_reason || ALL_VALUE}
                  onValueChange={(value) =>
                    updateFilters((prev) => ({
                      ...prev,
                      pending_reason: value === ALL_VALUE ? '' : value,
                    }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>All</SelectItem>
                    <SelectItem value='shortage'>{m.status.shortage}</SelectItem>
                    <SelectItem value='missing_files'>
                      {m.status.missing_files}
                    </SelectItem>
                    <SelectItem value='unknown'>{m.status.unknown}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.fromDate}</label>
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
                <label className='text-sm font-medium'>{m.toDate}</label>
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

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.sortBy}</label>
                <Select
                  value={state.filters.sort_by}
                  onValueChange={(value) =>
                    updateFilters((prev) => ({ ...prev, sort_by: value }))
                  }
                >
                  <SelectTrigger className='h-10 rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='seller_username'>
                      {m.sortOptions.seller_username}
                    </SelectItem>
                    <SelectItem value='days_pending'>
                      {m.sortOptions.days_pending}
                    </SelectItem>
                    <SelectItem value='shortage'>
                      {m.sortOptions.shortage}
                    </SelectItem>
                    <SelectItem value='created_at'>
                      {m.sortOptions.created_at}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <div className='space-y-4'>
            <LemiexDataTable
              columns={columns}
              data={orders}
              page={state.page}
              pageSize={state.perPage}
              total={pagination.total}
              loading={loading}
              loadingText={m.loading}
              emptyText={m.noPendingOrders}
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
              getRowId={(row) => String(row.id)}
            />

            {expandedOrder ? (
              <Card className='rounded-[6px] shadow-sm'>
                <CardHeader className='pb-3'>
                  <CardTitle className='text-base font-semibold'>
                    {m.orderId} #{expandedOrder.id}
                  </CardTitle>
                </CardHeader>
                <CardContent className='pt-0'>
                  {renderExpandedContent(expandedOrder, m)}
                </CardContent>
              </Card>
            ) : null}
          </div>
        </div>
      </Main>
    </>
  )
}

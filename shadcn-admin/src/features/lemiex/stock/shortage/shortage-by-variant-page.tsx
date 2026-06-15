'use client'

import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { ArrowRight, ChevronRight, Layers3, Tags, TriangleAlert } from 'lucide-react'
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
  fetchShortageByVariant,
  type ShortageByVariantRecord,
  type ShortageByVariantSummary,
  type ShortageFilters,
  type ShortagePagination,
} from '@/services/shortage/api'
import { cn } from '@/lib/utils'

const SORT_OPTIONS = ['shortage', 'orders_count', 'demand', 'variant_id'] as const

const fallbackMessages = {
  title: 'Shortage by Variant',
  subtitleWithCount: '{count} variants with shortage',
  subtitleAllGood: 'No shortage variants',
  viewByOrder: 'View by Order',
  failedToLoad: 'Failed to load shortage report',
  totalVariants: 'Variants with Shortage',
  totalShortage: 'Total Shortage',
  ordersAffected: 'Orders Affected',
  searchVariant: 'Variant ID',
  variantIdPlaceholder: 'Variant ID...',
  style: 'Style',
  stylePlaceholder: 'Style...',
  fromDate: 'From Date',
  toDate: 'To Date',
  sortBy: 'Sort By',
  clearFilters: 'Clear Filters',
  loading: 'Loading shortage variants...',
  noShortage: 'No shortage variants found',
  noShortageDesc: 'All variants have sufficient stock.',
  variantId: 'Variant ID',
  color: 'Color',
  size: 'Size',
  stock: 'Stock',
  demand: 'Demand',
  shortage: 'Shortage',
  orders: 'Orders',
  day: 'day',
  days: 'days',
  sortOptions: {
    shortage: 'Shortage Amount',
    orders_count: 'Orders Count',
    demand: 'Demand',
    variant_id: 'Variant ID',
  },
  ordersTable: {
    title: 'Affected Orders',
    noOrders: 'No affected orders',
    orderId: 'Order ID',
    refId: 'Ref ID',
    seller: 'Seller',
    quantity: 'Qty',
    shortage: 'Shortage',
    daysPending: 'Days',
    action: 'Action',
    view: 'View',
  },
}

function formatMessage(template: string, values: Record<string, string | number>) {
  return Object.entries(values).reduce(
    (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
    template
  )
}

function parseState(searchParams: URLSearchParams) {
  return {
    page: Number(searchParams.get('page') || 1),
    perPage: Number(searchParams.get('per_page') || 50),
    filters: {
      variant_id: searchParams.get('variant_id') || '',
      style: searchParams.get('style') || '',
      date_from: searchParams.get('date_from') || '',
      date_to: searchParams.get('date_to') || '',
      sort_by: searchParams.get('sort_by') || 'shortage',
      sort_order:
        (searchParams.get('sort_order') as 'asc' | 'desc' | null) || 'desc',
      pending_reason: '',
      order_id: '',
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
    if (!value || key === 'pending_reason' || key === 'order_id') return
    params.set(key, value)
  })

  return params
}

function getDayTone(daysPending: number) {
  if (daysPending >= 14) return 'bg-rose-500/10 text-rose-700 border-rose-200'
  if (daysPending >= 7) return 'bg-amber-500/10 text-amber-700 border-amber-200'
  return 'bg-muted text-muted-foreground'
}

export function LemiexShortageByVariantPage() {
  const { messages } = useI18n()
  const m = messages.stock?.shortageByVariant || fallbackMessages

  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const queryKey = searchParams.toString()
  const state = useMemo(
    () => parseState(new URLSearchParams(queryKey)),
    [queryKey]
  )

  const [variants, setVariants] = useState<ShortageByVariantRecord[]>([])
  const [summary, setSummary] = useState<ShortageByVariantSummary>({
    total_variants: 0,
    total_shortage: 0,
    total_orders_affected: 0,
  })
  const [pagination, setPagination] = useState<ShortagePagination>({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
  })
  const [expandedVariantId, setExpandedVariantId] = useState<string | null>(null)
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
        const result = await fetchShortageByVariant({
          page: state.page,
          per_page: state.perPage,
          variant_id: state.filters.variant_id,
          style: state.filters.style,
          date_from: state.filters.date_from,
          date_to: state.filters.date_to,
          sort_by: state.filters.sort_by,
          sort_order: state.filters.sort_order,
        })

        if (!active) return
        setVariants(result.variants)
        setSummary(result.summary)
        setPagination(result.pagination)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.failedToLoad)
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
  }, [m.failedToLoad, state])

  const expandedVariant = useMemo(
    () => variants.find((variant) => variant.variant_id === expandedVariantId) || null,
    [expandedVariantId, variants]
  )

  const summaryItems = [
    {
      key: 'variants',
      title: m.totalVariants,
      value: summary.total_variants,
      icon: Tags,
    },
    {
      key: 'shortage',
      title: m.totalShortage,
      value: summary.total_shortage,
      icon: TriangleAlert,
    },
    {
      key: 'orders',
      title: m.ordersAffected,
      value: summary.total_orders_affected,
      icon: Layers3,
    },
  ]

  const columns = useMemo<ColumnDef<ShortageByVariantRecord>[]>(
    () => [
      {
        id: 'expand',
        header: '',
        cell: ({ row }) => {
          const isExpanded = expandedVariantId === row.original.variant_id
          return (
            <Button
              variant='ghost'
              size='icon'
              onClick={() =>
                setExpandedVariantId((prev) =>
                  prev === row.original.variant_id ? null : row.original.variant_id
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
        id: 'variant_id',
        header: m.variantId,
        cell: ({ row }) => (
          <code className='rounded-md bg-muted px-2 py-1 text-xs font-semibold'>
            {row.original.variant_id}
          </code>
        ),
        meta: {
          thClassName: 'min-w-[180px]',
          tdClassName: 'min-w-[180px]',
        },
      },
      {
        id: 'style',
        header: m.style,
        cell: ({ row }) => row.original.style || '-',
        meta: {
          thClassName: 'min-w-[140px]',
          tdClassName: 'min-w-[140px]',
        },
      },
      {
        id: 'color',
        header: m.color,
        cell: ({ row }) => row.original.color || '-',
        meta: {
          thClassName: 'min-w-[110px]',
          tdClassName: 'min-w-[110px]',
        },
      },
      {
        id: 'size',
        header: m.size,
        cell: ({ row }) => <Badge variant='outline'>{row.original.size || '-'}</Badge>,
        meta: {
          thClassName: 'min-w-[90px] text-center',
          tdClassName: 'min-w-[90px] text-center',
        },
      },
      {
        id: 'stock',
        header: m.stock,
        cell: ({ row }) => (
          <span
            className={cn(
              'font-medium',
              (row.original.stock || 0) === 0 ? 'text-rose-600' : 'text-muted-foreground'
            )}
          >
            {row.original.stock || 0}
          </span>
        ),
        meta: {
          thClassName: 'min-w-[90px] text-right',
          tdClassName: 'min-w-[90px] text-right',
        },
      },
      {
        id: 'total_demand',
        header: m.demand,
        cell: ({ row }) => (
          <span className='font-medium text-amber-600'>
            {row.original.total_demand || 0}
          </span>
        ),
        meta: {
          thClassName: 'min-w-[90px] text-right',
          tdClassName: 'min-w-[90px] text-right',
        },
      },
      {
        id: 'total_shortage',
        header: m.shortage,
        cell: ({ row }) => (
          <span className='font-medium text-rose-600'>
            -{row.original.total_shortage || 0}
          </span>
        ),
        meta: {
          thClassName: 'min-w-[100px] text-right',
          tdClassName: 'min-w-[100px] text-right',
        },
      },
      {
        id: 'orders_count',
        header: m.orders,
        cell: ({ row }) => (
          <Badge variant='secondary'>{row.original.orders_count || 0}</Badge>
        ),
        meta: {
          thClassName: 'min-w-[100px] text-center',
          tdClassName: 'min-w-[100px] text-center',
        },
      },
    ],
    [expandedVariantId, m]
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
                {summary.total_variants > 0
                  ? formatMessage(m.subtitleWithCount, {
                      count: summary.total_variants,
                    })
                  : m.subtitleAllGood}
              </p>
            </div>

            <Button
              variant='outline'
              onClick={() => router.push('/lemiex/stock/shortage')}
            >
              <ArrowRight className='mr-2 size-4' />
              {m.viewByOrder}
            </Button>
          </div>

          <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-3'>
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
            <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-5'>
              <div className='space-y-2 xl:col-span-2'>
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
                <label className='text-sm font-medium'>{m.style}</label>
                <Input
                  className='h-10 rounded-[6px]'
                  value={state.filters.style}
                  placeholder={m.stylePlaceholder}
                  onChange={(event) =>
                    updateFilters((prev) => ({
                      ...prev,
                      style: event.target.value,
                    }))
                  }
                />
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
                    {SORT_OPTIONS.map((option) => (
                      <SelectItem key={option} value={option}>
                        {m.sortOptions[option]}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <div className='space-y-4'>
            <LemiexDataTable
              columns={columns}
              data={variants}
              page={state.page}
              pageSize={state.perPage}
              total={pagination.total}
              loading={loading}
              loadingText={m.loading}
              emptyText={m.noShortage}
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
              getRowId={(row) => row.variant_id}
            />

            {expandedVariant ? (
              <Card className='rounded-[6px] shadow-sm'>
                <CardHeader className='pb-3'>
                  <CardTitle className='text-base font-semibold'>
                    {m.ordersTable.title} ({expandedVariant.orders?.length || 0})
                  </CardTitle>
                </CardHeader>
                <CardContent className='pt-0'>
                  <div className='overflow-x-auto rounded-[6px] border bg-card'>
                    <Table className='min-w-[900px]'>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{m.ordersTable.orderId}</TableHead>
                          <TableHead>{m.ordersTable.refId}</TableHead>
                          <TableHead>{m.ordersTable.seller}</TableHead>
                          <TableHead className='text-center'>
                            {m.ordersTable.quantity}
                          </TableHead>
                          <TableHead className='text-right'>
                            {m.ordersTable.shortage}
                          </TableHead>
                          <TableHead className='text-center'>
                            {m.ordersTable.daysPending}
                          </TableHead>
                          <TableHead className='text-right'>
                            {m.ordersTable.action}
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {expandedVariant.orders?.length ? (
                          expandedVariant.orders.map((order) => {
                            const daysPending = Math.round(order.days_pending || 0)

                            return (
                              <TableRow key={`${expandedVariant.variant_id}-${order.order_id}`}>
                                <TableCell>
                                  <button
                                    type='button'
                                    className='font-semibold text-primary'
                                    onClick={() =>
                                      router.push(`/lemiex/orders/${order.order_id}`)
                                    }
                                  >
                                    #{order.order_id}
                                  </button>
                                </TableCell>
                                <TableCell>
                                  <code className='rounded-md bg-muted px-2 py-1 text-xs'>
                                    {order.ref_id || 'N/A'}
                                  </code>
                                </TableCell>
                                <TableCell>{order.seller || 'N/A'}</TableCell>
                                <TableCell className='text-center'>
                                  {order.quantity || 0}
                                </TableCell>
                                <TableCell className='text-right font-medium text-rose-600'>
                                  -{order.shortage || 0}
                                </TableCell>
                                <TableCell className='text-center'>
                                  <Badge
                                    variant='outline'
                                    className={getDayTone(daysPending)}
                                  >
                                    {daysPending} {daysPending === 1 ? m.day : m.days}
                                  </Badge>
                                </TableCell>
                                <TableCell className='text-right'>
                                  <Button
                                    variant='outline'
                                    onClick={() =>
                                      router.push(`/lemiex/orders/${order.order_id}`)
                                    }
                                  >
                                    {m.ordersTable.view}
                                  </Button>
                                </TableCell>
                              </TableRow>
                            )
                          })
                        ) : (
                          <TableRow>
                            <TableCell colSpan={7} className='h-24 text-center'>
                              {m.ordersTable.noOrders}
                            </TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  </div>
                </CardContent>
              </Card>
            ) : null}
          </div>
        </div>
      </Main>
    </>
  )
}

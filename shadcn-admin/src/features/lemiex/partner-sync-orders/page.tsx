'use client'
/* eslint-disable @next/next/no-img-element */

import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import type { ColumnDef } from '@tanstack/react-table'
import { Search as SearchIcon, X } from 'lucide-react'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useI18n } from '@/context/i18n-provider'
import {
  fetchPartnerStores,
  type PartnerStoreRecord,
} from '@/services/partner-stores/api'
import {
  getPartnerSyncedOrders,
  type PartnerSyncedOrder,
} from '@/features/lemiex/partner-stores/fake-sync-orders'

const fallbackMessages = {
  title: 'Synced Orders',
  subtitle: 'Review the latest synced partner orders before moving into the main flow.',
  filters: {
    store: 'Store',
    orderNo: 'Partner Order',
    status: 'Status',
    fulfillment: 'Fulfillment',
    allStores: 'All Stores',
    allStatuses: 'All Statuses',
    allFulfillment: 'All Fulfillment',
    orderNoPlaceholder: 'Search partner order...',
    search: 'Search',
    clearAll: 'Clear All',
    pending: 'Pending',
    paid: 'Paid',
    cancelled: 'Cancelled',
    noFulfillment: 'No fulfillment',
    ready: 'Ready',
    shipped: 'Shipped',
  },
  loading: 'Loading synced orders...',
  empty: 'No synced orders yet. Run sync from Partner Stores first.',
  columns: {
    id: 'ID',
    store: 'Store',
    customer: 'Customer',
    user: 'User',
    partnerOrder: 'TikTok Order',
    tracking: 'Tracking',
    items: 'Items',
    discount: 'Discount',
    total: 'Total',
    status: 'Status',
    fulfillment: 'Fulfillment',
    note: 'Note',
    actions: 'Actions',
  },
  labels: {
    sku: 'SKU',
    qty: 'QTY',
    buyLabel: 'Buy Label',
    buyLabels: 'Buy Labels',
    edit: 'Edit',
    ship: 'Ship',
    delete: 'Delete',
  },
}

function money(value: number) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}

function statusTone(value: PartnerSyncedOrder['status']) {
  if (value === 'Paid') return 'bg-emerald-500/10 text-emerald-700 border-emerald-200'
  if (value === 'Cancelled') return 'bg-rose-500/10 text-rose-700 border-rose-200'
  return 'bg-amber-500/10 text-amber-700 border-amber-200'
}

function fulfillmentTone(value: PartnerSyncedOrder['fulfillment']) {
  if (value === 'Shipped') return 'bg-sky-500/10 text-sky-700 border-sky-200'
  if (value === 'Ready') return 'bg-violet-500/10 text-violet-700 border-violet-200'
  return 'bg-slate-500/10 text-slate-700 border-slate-200'
}

function getStatusLabel(
  value: PartnerSyncedOrder['status'],
  ui: typeof fallbackMessages
) {
  if (value === 'Pending') return ui.filters.pending
  if (value === 'Paid') return ui.filters.paid
  if (value === 'Cancelled') return ui.filters.cancelled
  return value
}

function getFulfillmentLabel(
  value: PartnerSyncedOrder['fulfillment'],
  ui: typeof fallbackMessages
) {
  if (value === 'No fulfillment') return ui.filters.noFulfillment
  if (value === 'Ready') return ui.filters.ready
  if (value === 'Shipped') return ui.filters.shipped
  return value
}

export function LemiexPartnerSyncOrdersPage() {
  const { messages } = useI18n()
  const ui = useMemo(
    () =>
      ((messages as { partnerSyncOrdersPage?: typeof fallbackMessages })
        .partnerSyncOrdersPage ?? fallbackMessages),
    [messages]
  )
  const [orders] = useState<PartnerSyncedOrder[]>(() => getPartnerSyncedOrders())
  const [stores, setStores] = useState<PartnerStoreRecord[]>([])
  const searchParams = useSearchParams()
  const storeIdFilter = searchParams.get('store_id')
  const [filters, setFilters] = useState({
    store: storeIdFilter || 'all',
    orderNo: '',
    status: 'all',
    fulfillment: 'all',
  })

  useEffect(() => {
    let active = true

    void fetchPartnerStores({ page: 1, per_page: 200 })
      .then((response) => {
        if (!active) return
        setStores(response.stores)
      })
      .catch(() => {
        if (!active) return
        setStores([])
      })

    return () => {
      active = false
    }
  }, [])

  const storeNameById = useMemo(
    () =>
      new Map(
        stores.map((store) => [
          String(store.id),
          (store.name || '').trim() || `Store #${store.id}`,
        ])
      ),
    [stores]
  )

  const storeOptions = useMemo(
    () =>
      Array.from(
        new Map(
          orders.map((order) => [
            String(order.store_id),
            storeNameById.get(String(order.store_id)) || order.store_name,
          ])
        ).entries()
      ),
    [orders, storeNameById]
  )

  const filteredOrders = useMemo(() => {
    return orders.filter((order) => {
      const matchesStore =
        filters.store === 'all' ? true : String(order.store_id) === filters.store
      const matchesOrderNo = filters.orderNo
        ? order.partner_order_no.toLowerCase().includes(filters.orderNo.toLowerCase())
        : true
      const matchesStatus = filters.status === 'all' ? true : order.status === filters.status
      const matchesFulfillment =
        filters.fulfillment === 'all' ? true : order.fulfillment === filters.fulfillment

      return (
        matchesStore &&
        matchesOrderNo &&
        matchesStatus &&
        matchesFulfillment
      )
    })
  }, [filters, orders])

  const columns = useMemo<ColumnDef<PartnerSyncedOrder>[]>(
    () => [
      {
        id: 'id',
        header: ui.columns.id,
        cell: ({ row }) => (
          <Badge variant='secondary' className='rounded-[6px] px-2 py-1 font-semibold'>
            #{row.index + 1}
          </Badge>
        ),
      },
      {
        id: 'store',
        header: ui.columns.store,
        cell: ({ row }) => (
          <span className='font-semibold'>
            {storeNameById.get(String(row.original.store_id)) || row.original.store_name}
          </span>
        ),
      },
      {
        id: 'customer',
        header: ui.columns.customer,
        cell: ({ row }) => (
          <div className='space-y-2'>
            <div className='font-semibold'>{row.original.customer_name}</div>
            <div className='space-y-1 text-xs text-muted-foreground'>
              <div>{row.original.customer_address}</div>
              <div>{row.original.customer_phone}</div>
            </div>
          </div>
        ),
      },
      {
        id: 'user',
        header: ui.columns.user,
        cell: ({ row }) => <span className='font-medium'>{row.original.user_name}</span>,
      },
      {
        id: 'partner_order_no',
        header: ui.columns.partnerOrder,
        cell: ({ row }) => (
          <Badge className='rounded-[6px] bg-sky-500/10 px-2 py-1 text-sky-700 hover:bg-sky-500/10'>
            {row.original.partner_order_no}
          </Badge>
        ),
      },
      {
        id: 'tracking',
        header: ui.columns.tracking,
        cell: ({ row }) => (
          <Badge className='rounded-[6px] bg-amber-500/10 px-2 py-1 text-amber-700 hover:bg-amber-500/10'>
            {row.original.tracking_label === 'Buy Labels'
              ? ui.labels.buyLabels
              : row.original.tracking_label === 'Buy Label'
                ? ui.labels.buyLabel
                : row.original.tracking_label}
          </Badge>
        ),
      },
      {
        id: 'items',
        header: ui.columns.items,
        cell: ({ row }) => (
          <div className='flex min-w-[280px] items-center gap-3 rounded-[6px] border bg-muted/20 p-3'>
            <img
              src={row.original.item_image}
              alt={row.original.item_name}
              className='h-14 w-14 rounded-[6px] object-cover'
            />
            <div className='min-w-0 flex-1 space-y-1'>
              <div className='truncate text-sm font-semibold'>{row.original.item_name}</div>
              <div className='text-xs text-muted-foreground'>
                {ui.labels.sku}: {row.original.item_sku}
              </div>
            </div>
            <Badge className='rounded-[6px] bg-amber-500/10 px-2 py-1 text-amber-700 hover:bg-amber-500/10'>
              {ui.labels.qty}: {row.original.quantity}
            </Badge>
          </div>
        ),
      },
      {
        id: 'discount',
        header: ui.columns.discount,
        cell: ({ row }) => (
          <span className='font-medium text-amber-600'>-{money(row.original.discount)}</span>
        ),
      },
      {
        id: 'total',
        header: ui.columns.total,
        cell: ({ row }) => (
          <span className='font-semibold text-emerald-600'>{money(row.original.total)}</span>
        ),
      },
      {
        id: 'status',
        header: ui.columns.status,
        cell: ({ row }) => (
          <Badge variant='outline' className={statusTone(row.original.status)}>
            {getStatusLabel(row.original.status, ui)}
          </Badge>
        ),
      },
      {
        id: 'fulfillment',
        header: ui.columns.fulfillment,
        cell: ({ row }) => (
          <Badge variant='outline' className={fulfillmentTone(row.original.fulfillment)}>
            {getFulfillmentLabel(row.original.fulfillment, ui)}
          </Badge>
        ),
      },
      {
        id: 'note',
        header: ui.columns.note,
        meta: {
          thClassName: 'w-[140px] min-w-[140px]',
          tdClassName: 'w-[140px] min-w-[140px]',
        },
        cell: ({ row }) => <span className='text-sm text-muted-foreground'>{row.original.note}</span>,
      },
      {
        id: 'actions',
        header: ui.columns.actions,
        meta: {
          thClassName:
            'sticky right-0 z-20 w-[148px] min-w-[148px] bg-background text-center',
          tdClassName:
            'sticky right-0 z-10 w-[148px] min-w-[148px] bg-background text-center',
        },
        cell: () => (
          <div className='flex w-[124px] flex-col gap-2'>
            <Button variant='outline' className='h-8 rounded-[8px] px-3 text-xs'>
              {ui.labels.edit}
            </Button>
            <Button variant='outline' className='h-8 rounded-[8px] px-3 text-xs'>
              {ui.labels.ship}
            </Button>
            <Button
              variant='outline'
              className='h-8 rounded-[8px] border-rose-200 px-3 text-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700'
            >
              {ui.labels.delete}
            </Button>
          </div>
        ),
      },
    ],
    [storeNameById, ui]
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
            <h1 className='text-3xl font-semibold tracking-tight'>{ui.title}</h1>
            <p className='text-sm text-muted-foreground'>{ui.subtitle}</p>
          </div>

          <Card className='mx-auto w-full max-w-[1355px] rounded-[6px]'>
            <CardContent className='p-5'>
              <div className='flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between'>
                <div className='grid flex-1 gap-x-6 gap-y-4 md:grid-cols-2 xl:[grid-template-columns:213.4px_minmax(0,426.8px)_213.4px_213.4px]'>
                  <div className='space-y-2.5'>
                    <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground'>
                      {ui.filters.store}
                    </div>
                    <Select
                      value={filters.store}
                      onValueChange={(value) => setFilters((prev) => ({ ...prev, store: value }))}
                    >
                      <SelectTrigger className='h-[35px] w-[213.4px] rounded-[10px] border-border bg-background px-4 text-[13px] shadow-none'>
                        <SelectValue placeholder={ui.filters.allStores} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='all'>{ui.filters.allStores}</SelectItem>
                        {storeOptions.map(([value, label]) => (
                          <SelectItem key={value} value={value}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className='space-y-2.5'>
                    <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground'>
                      {ui.filters.orderNo}
                    </div>
                    <Input
                      className='h-[35px] w-full rounded-[10px] border-border bg-background px-4 text-[13px] shadow-none'
                      placeholder={ui.filters.orderNoPlaceholder}
                      value={filters.orderNo}
                      onChange={(event) =>
                        setFilters((prev) => ({ ...prev, orderNo: event.target.value }))
                      }
                    />
                  </div>

                  <div className='space-y-2.5'>
                    <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground'>
                      {ui.filters.status}
                    </div>
                    <Select
                      value={filters.status}
                      onValueChange={(value) => setFilters((prev) => ({ ...prev, status: value }))}
                    >
                      <SelectTrigger className='h-[35px] w-full rounded-[10px] border-border bg-background px-4 text-[13px] shadow-none'>
                        <SelectValue placeholder={ui.filters.allStatuses} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='all'>{ui.filters.allStatuses}</SelectItem>
                        <SelectItem value='Pending'>{ui.filters.pending}</SelectItem>
                        <SelectItem value='Paid'>{ui.filters.paid}</SelectItem>
                        <SelectItem value='Cancelled'>{ui.filters.cancelled}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className='space-y-2.5'>
                    <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground'>
                      {ui.filters.fulfillment}
                    </div>
                    <Select
                      value={filters.fulfillment}
                      onValueChange={(value) =>
                        setFilters((prev) => ({ ...prev, fulfillment: value }))
                      }
                    >
                      <SelectTrigger className='h-[35px] w-full rounded-[10px] border-border bg-background px-4 text-[13px] shadow-none'>
                        <SelectValue placeholder={ui.filters.allFulfillment} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='all'>{ui.filters.allFulfillment}</SelectItem>
                        <SelectItem value='No fulfillment'>{ui.filters.noFulfillment}</SelectItem>
                        <SelectItem value='Ready'>{ui.filters.ready}</SelectItem>
                        <SelectItem value='Shipped'>{ui.filters.shipped}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className='flex flex-col gap-3 sm:flex-row xl:flex-none xl:pb-0.5'>
                  <Button
                    type='button'
                    className='h-[35px] w-[91.65px] rounded-[10px] px-3 text-[13px]'
                  >
                    <SearchIcon className='size-4' />
                    {ui.filters.search}
                  </Button>
                  <Button
                    type='button'
                    variant='outline'
                    className='h-[35px] w-[91.65px] rounded-[10px] px-3 text-[13px]'
                    onClick={() =>
                      setFilters({
                        store: storeIdFilter || 'all',
                        orderNo: '',
                        status: 'all',
                        fulfillment: 'all',
                      })
                    }
                  >
                    <X className='size-4' />
                    {ui.filters.clearAll}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          <LemiexDataTable
            columns={columns}
            data={filteredOrders}
            page={1}
            pageSize={Math.max(10, filteredOrders.length || 10)}
            total={filteredOrders.length}
            loading={false}
            loadingText={ui.loading}
            emptyText={ui.empty}
            getRowId={(row) => row.id}
            onPageChange={() => undefined}
            onPageSizeChange={() => undefined}
          />
        </div>
      </Main>
    </>
  )
}

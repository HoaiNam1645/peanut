'use client'

import { type ComponentType, Fragment, useEffect, useMemo, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { Plus, Tags, Truck } from 'lucide-react'
import { toast } from 'sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { DataTableBulkActions } from '@/components/data-table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { useI18n } from '@/context/i18n-provider'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { DEFAULT_ORDERS_FILTERS } from '@/features/lemiex/orders/constants'
import { OrderDailyLimitCard } from '@/features/lemiex/orders/components/order-daily-limit-card'
import { OrdersFilters } from '@/features/lemiex/orders/components/orders-filters'
import { OrdersSelectionProvider } from '@/features/lemiex/orders/components/orders-selection-context'
import { getOrdersTableColumns } from '@/features/lemiex/orders/components/orders-table-columns'
import {
  type LemiexOrdersFilters,
  type LemiexOrdersPageState,
} from '@/features/lemiex/orders/types'
import {
  buyLabelBatch,
  buyLabelSingle,
  previewShippingPrices,
  type ShipDvxPricePreview,
  fetchOrderIds,
  fetchOrderFulfillStatusOptions,
  fetchOrders,
  type SelectOption,
  type OrderListResult,
} from '@/services/orders/api'
import { fetchCurrentUser, getUserRoleName } from '@/services/auth/api'
import { useAuthStore } from '@/stores/auth-store'

function parseArrayParam(value: string | null) {
  if (!value) return []
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
}

function parseSearchParams(searchParams: URLSearchParams): LemiexOrdersPageState {
  return {
    page: Number(searchParams.get('page') || 1),
    perPage: Number(searchParams.get('per_page') || 50),
    filters: {
      order_id: searchParams.get('order_id') || '',
      ref_id: searchParams.get('ref_id') || '',
      tracking_number: searchParams.get('tracking_number') || '',
      product_name: searchParams.get('product_name') || '',
      variant_id: searchParams.get('variant_id') || '',
      style: searchParams.get('style') || '',
      color: searchParams.get('color') || '',
      size: searchParams.get('size') || '',
      seller_id: searchParams.get('seller_id') || '',
      fulfill_status: parseArrayParam(searchParams.get('fulfill_status')),
      payment_status: parseArrayParam(searchParams.get('payment_status')),
      exclude_status: parseArrayParam(searchParams.get('exclude_status')),
      label_status: parseArrayParam(searchParams.get('label_status')),
      date_from: searchParams.get('date_from') || '',
      date_to: searchParams.get('date_to') || '',
      shipped_date_from: searchParams.get('shipped_date_from') || '',
      shipped_date_to: searchParams.get('shipped_date_to') || '',
      sort_by: searchParams.get('sort_by') || 'created_at',
      sort_order:
        (searchParams.get('sort_order') as 'asc' | 'desc' | null) || 'asc',
      missing_shipping_info:
        searchParams.get('missing_shipping_info') === 'true',
    },
  }
}

function buildSearchParams(state: LemiexOrdersPageState) {
  const params = new URLSearchParams()

  if (state.page > 1) params.set('page', String(state.page))
  if (state.perPage !== 50) params.set('per_page', String(state.perPage))

  Object.entries(state.filters).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      if (value.length > 0) params.set(key, value.join(','))
      return
    }

    if (typeof value === 'boolean') {
      if (value) params.set(key, 'true')
      return
    }

    if (value) params.set(key, value)
  })

  return params
}

function hasActiveFilter(filters: LemiexOrdersFilters) {
  return Object.entries(filters).some(([key, value]) => {
    if (key === 'sort_by' || key === 'sort_order' || key === 'exclude_status') {
      return false
    }

    if (Array.isArray(value)) return value.length > 0
    if (typeof value === 'boolean') return value

    return value !== ''
  })
}

function buildOrdersRequest(state: LemiexOrdersPageState) {
  const params: Record<string, string | number | boolean | string[]> = {
    page: state.page,
    per_page: state.perPage,
    ...state.filters,
  }

  const defaultExclusions = [
    'cancelled',
    'shipped',
    'test_order',
    'cancelled_refund_shipping',
    'closed',
  ]

  const excludeStatus = [...state.filters.exclude_status]

  if (!hasActiveFilter(state.filters)) {
    excludeStatus.unshift(...defaultExclusions)
  }

  const uniqueExcludeStatus = Array.from(new Set(excludeStatus))
  if (uniqueExcludeStatus.length > 0) {
    params.exclude_status = uniqueExcludeStatus
  }

  return params
}

type CreateOrderType = {
  id: string
  titleKey: 'tumblerLabelShipTitle' | 'tumblerSellerShipTitle'
  descriptionKey: 'tumblerLabelShipDesc' | 'tumblerSellerShipDesc'
  icon: ComponentType<{ className?: string }>
}

const WOOD_ORDER_TYPES: CreateOrderType[] = [
  {
    id: 'tumbler_label_ship',
    titleKey: 'tumblerLabelShipTitle',
    descriptionKey: 'tumblerLabelShipDesc',
    icon: Tags,
  },
  {
    id: 'tumbler_seller_ship',
    titleKey: 'tumblerSellerShipTitle',
    descriptionKey: 'tumblerSellerShipDesc',
    icon: Truck,
  },
]

function getCreateOrderPath(type: string) {
  switch (type) {
    case 'no_design':
      return '/lemiex/orders/create/no-design'
    case 'label_ship':
      return '/lemiex/orders/create/label-ship'
    case 'seller_ship':
      return '/lemiex/orders/create/seller-ship'
    case 'tumbler_label_ship':
      return '/lemiex/orders/create/tumbler-label-ship'
    case 'tumbler_seller_ship':
      return '/lemiex/orders/create/tumbler-seller-ship'
    default:
      return '/lemiex/orders'
  }
}

export function LemiexOrders() {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const currentUser = useAuthStore((state) => state.auth.user)
  const setUser = useAuthStore((state) => state.auth.setUser)
  const { messages } = useI18n()
  const ordersMessages = messages.orders
  const createOrderMessages = ordersMessages.createOrderDialog

  const queryKey = searchParams.toString()
  const state = useMemo(
    () => parseSearchParams(new URLSearchParams(queryKey)),
    [queryKey]
  )

  const [result, setResult] = useState<OrderListResult>({
    orders: [],
    pagination: {
      currentPage: state.page,
      lastPage: 1,
      perPage: state.perPage,
      total: 0,
    },
    summary: null,
  })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)
  const [fulfillStatusOptions, setFulfillStatusOptions] = useState<SelectOption[]>([])
  const [selectedOrderIds, setSelectedOrderIds] = useState<Array<number | string>>([])
  const [buyingLabel, setBuyingLabel] = useState(false)
  const [buyLabelConfirmOpen, setBuyLabelConfirmOpen] = useState(false)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [previewResult, setPreviewResult] = useState<ShipDvxPricePreview | null>(null)
  const [pendingBuyIds, setPendingBuyIds] = useState<Array<number | string>>([])
  // User-edited weight (g) per ITEM (keyed by item_id), applied in the buy preview + checkout.
  const [itemWeights, setItemWeights] = useState<Record<string, number>>({})
  const [repricing, setRepricing] = useState(false)
  const [storeRequiredOpen, setStoreRequiredOpen] = useState(false)
  const [typeDialogOpen, setTypeDialogOpen] = useState(false)
  const [createShipConfirmOpen, setCreateShipConfirmOpen] = useState(false)
  const [forwardIds, setForwardIds] = useState<Array<number | string>>([])

  const selectedOrders = useMemo(
    () =>
      result.orders.filter((order) => selectedOrderIds.includes(String(order.id))),
    [result.orders, selectedOrderIds]
  )
  // Two distinct operations (per ShipDVX docs):
  // - "Tạo vận chuyển" (forward): order already carries a label (tracking + label_url,
  //   e.g. TikTok) → forwarded as-is, NO price to preview.
  // - "Mua label" (buy): order has no label → ShipDVX buys one → needs a price preview.
  const forwardEligibleIds = useMemo(
    () =>
      selectedOrders
        .filter((o) => Boolean(o.shipping?.tracking_id && o.shipping?.label_url))
        .map((o) => String(o.id)),
    [selectedOrders]
  )
  const buyEligibleIds = useMemo(
    () =>
      selectedOrders
        .filter((o) => !(o.shipping?.tracking_id && o.shipping?.label_url))
        .map((o) => String(o.id)),
    [selectedOrders]
  )
  const role = getUserRoleName(currentUser)
  const canCreateOrder =
    role === 'Seller' || role === 'Admin' || role === 'Support'
  const orderTypes = WOOD_ORDER_TYPES

  const columns = useMemo(
    () =>
      getOrdersTableColumns(
        currentUser,
        ordersMessages,
        fulfillStatusOptions,
        () => setRefreshKey((value) => value + 1)
      ),
    [currentUser, ordersMessages, fulfillStatusOptions]
  )
  const currentOrderIds = useMemo(
    () => result.orders.map((order) => String(order.id)),
    [result.orders]
  )

  const pushState = (nextState: LemiexOrdersPageState) => {
    const nextParams = buildSearchParams(nextState)
    const nextQuery = nextParams.toString()
    router.replace(nextQuery ? `${pathname}?${nextQuery}` : pathname, {
      scroll: false,
    })
  }

  useEffect(() => {
    let cancelled = false

    async function loadOrders() {
      setLoading(true)
      setError(null)

      try {
        const response = await fetchOrders(buildOrdersRequest(state))
        if (cancelled) return
        setResult(role === 'Staff' ? { ...response, summary: null } : response)
      } catch (loadError) {
        if (cancelled) return
        setError(
          loadError instanceof Error
            ? loadError.message
            : ordersMessages.loadErrorTitle
        )
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void loadOrders()

    return () => {
      cancelled = true
    }
  }, [role, state, refreshKey, ordersMessages.loadErrorTitle])

  useEffect(() => {
    let cancelled = false

    fetchOrderFulfillStatusOptions()
      .then((options) => {
        if (!cancelled) {
          setFulfillStatusOptions(options.filter(Boolean) as SelectOption[])
        }
      })
      .catch(() => {
        if (!cancelled) {
          setFulfillStatusOptions([])
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    const currentIds = new Set(result.orders.map((order) => String(order.id)))
    setSelectedOrderIds((prev) => prev.filter((id) => currentIds.has(String(id))))
  }, [result.orders])

  const handleApplyFilters = (filters: LemiexOrdersFilters) => {
    pushState({
      ...state,
      page: 1,
      filters,
    })
  }

  const handleResetFilters = () => {
    pushState({
      ...state,
      page: 1,
      filters: DEFAULT_ORDERS_FILTERS,
    })
  }

  const handleGetIds = async (filters: LemiexOrdersFilters) => {
    try {
      const ids = await fetchOrderIds(
        buildOrdersRequest({
          ...state,
          page: 1,
          filters,
        })
      )

      if (ids.length === 0) {
        toast.info(ordersMessages.noOrderIds)
        return
      }

      await navigator.clipboard.writeText(ids.join('\n'))
      toast.success(ordersMessages.copiedOrderIds.replace('{count}', String(ids.length)))
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : 'Không thể lấy danh sách IDs'
      )
    }
  }

  const handleToggleOrder = (orderId: number | string) => {
    const normalizedOrderId = String(orderId)
    setSelectedOrderIds((prev) =>
      prev.includes(normalizedOrderId)
        ? prev.filter((id) => id !== normalizedOrderId)
        : [...prev, normalizedOrderId]
    )
  }

  const handleToggleAllOrders = (checked: boolean) => {
    setSelectedOrderIds((prev) => {
      if (checked) {
        return Array.from(new Set([...prev, ...currentOrderIds]))
      }

      return prev.filter((id) => !currentOrderIds.includes(String(id)))
    })
  }

  const handleCopyTracking = async () => {
    const trackingNumbers = selectedOrders
      .map((order) => order.shipping?.tracking_id?.trim())
      .filter((tracking): tracking is string => Boolean(tracking))

    if (trackingNumbers.length === 0) {
      toast.warning(ordersMessages.noTrackingNumbers)
      return
    }

    try {
      await navigator.clipboard.writeText(trackingNumbers.join('\n'))
      toast.success(
        ordersMessages.copiedTrackingNumbers.replace(
          '{count}',
          String(trackingNumbers.length)
        )
      )
    } catch {
      toast.error(ordersMessages.copyTrackingFailed)
    }
  }

  const handleOpenPreview = async (ids: Array<number | string>) => {
    if (ids.length === 0) {
      toast.error(ordersMessages.selectAtLeastOneOrder)
      return
    }
    setPendingBuyIds(ids)
    setPreviewResult(null)
    setItemWeights({})
    setPreviewLoading(true)
    setBuyLabelConfirmOpen(true)
    try {
      const res = await previewShippingPrices(ids)
      setPreviewResult(res)
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : 'Không tính được giá vận chuyển'
      )
      setBuyLabelConfirmOpen(false)
    } finally {
      setPreviewLoading(false)
    }
  }

  // Shared create step for BOTH operations: forward (has label) and buy (no label).
  // The backend buyLabelViaShipDvx builds the right payload per order (HAS_LABEL forward
  // vs NO_LABEL buy); we just pass the correctly-filtered ids.
  const runCreateShipping = async (ids: Array<number | string>) => {
    if (ids.length === 0) {
      toast.error(ordersMessages.selectAtLeastOneOrder)
      return
    }

    setBuyingLabel(true)

    try {
      const response =
        ids.length === 1
          ? await buyLabelSingle(ids[0], itemWeights)
          : await buyLabelBatch(ids, itemWeights)

      // ShipDVX create-orders is async for both single and batch — no tracking number
      // is returned (it arrives later via webhook), so report the dispatch, not tracking.
      toast.success(
        ordersMessages.labelJobsDispatched.replace(
          '{count}',
          String(response.data?.dispatched || ids.length)
        )
      )

      setBuyLabelConfirmOpen(false)
      setCreateShipConfirmOpen(false)
      setSelectedOrderIds([])
      setPendingBuyIds([])
      setForwardIds([])
      setItemWeights({})
      setRefreshKey((value) => value + 1)
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : ordersMessages.buyLabelFailed
      )
    } finally {
      setBuyingLabel(false)
    }
  }

  // Re-price (debounced) when the user edits a weight in the buy preview dialog.
  useEffect(() => {
    if (!buyLabelConfirmOpen || Object.keys(itemWeights).length === 0) return
    const timer = setTimeout(() => {
      setRepricing(true)
      void previewShippingPrices(pendingBuyIds, itemWeights)
        .then(setPreviewResult)
        .catch(() => {})
        .finally(() => setRepricing(false))
    }, 700)
    return () => clearTimeout(timer)
  }, [itemWeights, buyLabelConfirmOpen, pendingBuyIds])

  // "Mua label" → only orders WITHOUT a label, with a price preview first.
  const handleOpenBuyLabel = () => {
    if (buyEligibleIds.length === 0) {
      toast.error('Không có đơn nào (chưa có label) để mua label trong các đơn đã chọn')
      return
    }
    if (forwardEligibleIds.length > 0) {
      toast.info(
        `${forwardEligibleIds.length} đơn đã có label sẽ không mua — dùng "Tạo vận chuyển"`
      )
    }
    void handleOpenPreview(buyEligibleIds)
  }

  // "Tạo vận chuyển" → only orders WITH a label, forwarded directly (no price preview).
  const handleOpenForward = () => {
    if (forwardEligibleIds.length === 0) {
      toast.error('Không có đơn nào (đã có label) để tạo vận chuyển trong các đơn đã chọn')
      return
    }
    if (buyEligibleIds.length > 0) {
      toast.info(
        `${buyEligibleIds.length} đơn chưa có label sẽ không forward — dùng "Mua label"`
      )
    }
    setForwardIds(forwardEligibleIds)
    setCreateShipConfirmOpen(true)
  }

  const handleCreateOrderClick = async () => {
    const result = await fetchCurrentUser()
    const latestUser = result.success ? result.user : currentUser

    if (result.success) {
      setUser(result.user)
    }

    const stores = Array.isArray(
      (latestUser as { stores?: unknown[] } | null)?.stores
    )
      ? (((latestUser as { stores?: unknown[] } | null)?.stores as unknown[]) ?? [])
      : []

    if (stores.length === 0) {
      setStoreRequiredOpen(true)
      return
    }

    setTypeDialogOpen(true)
  }

  const handleCreateOrderType = (type: string) => {
    setTypeDialogOpen(false)
    router.push(getCreateOrderPath(type))
  }

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

      <Main fluid className='flex flex-1 flex-col gap-3 px-4 sm:px-5 lg:px-6 xl:px-7'>
        <div className='flex flex-wrap items-center justify-between gap-3'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>{ordersMessages.title}</h2>
            <p className='text-sm text-muted-foreground'>
              {result.pagination.total.toLocaleString('en-US')} {ordersMessages.count}
            </p>
          </div>

          <div className='flex flex-wrap items-center gap-2'>
            <OrderDailyLimitCard />
            {canCreateOrder ? (
              <Button
                type='button'
                className='rounded-[6px]'
                onClick={() => void handleCreateOrderClick()}
              >
                <Plus className='size-4' />
                {ordersMessages.createOrder}
              </Button>
            ) : null}
          </div>
        </div>

        <div className='max-w-[1520px]'>
        <OrdersFilters
          filters={state.filters}
          user={currentUser}
          onApply={handleApplyFilters}
          onReset={handleResetFilters}
          onGetIds={handleGetIds}
          />
        </div>

        {error ? (
          <Alert variant='destructive'>
            <AlertTitle>{ordersMessages.loadErrorTitle}</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        <OrdersSelectionProvider
          value={{
            selectedOrderIds,
            currentOrderIds,
            onToggleOrder: handleToggleOrder,
            onToggleAllOrders: handleToggleAllOrders,
          }}
        >
          <DataTableBulkActions
            selectedCount={selectedOrderIds.length}
            entityName='order'
            onClearSelection={() => setSelectedOrderIds([])}
          >
            <Button
              type='button'
              size='sm'
              variant='outline'
              className='h-8 rounded-[6px] text-[12px]'
              onClick={() => {
                void handleCopyTracking()
              }}
              >
              {ordersMessages.copyTracking}
            </Button>

            {/* Show only the button that matches the selected orders:
                has label+tracking → "Tạo vận chuyển"; no label → "Mua label".
                If a mix is selected, both show (each handles its own subset). */}
            {forwardEligibleIds.length > 0 ? (
              <Button
                type='button'
                size='sm'
                variant='outline'
                className='h-8 rounded-[6px] text-[12px]'
                onClick={handleOpenForward}
              >
                Tạo vận chuyển
              </Button>
            ) : null}

            {buyEligibleIds.length > 0 ? (
              <Button
                type='button'
                size='sm'
                className='h-8 rounded-[6px] text-[12px]'
                onClick={handleOpenBuyLabel}
              >
                Mua label
              </Button>
            ) : null}
          </DataTableBulkActions>

          <LemiexDataTable
            columns={columns}
            data={result.orders}
            page={result.pagination.currentPage}
            pageSize={result.pagination.perPage}
            total={result.pagination.total}
            loading={loading}
            emptyText={ordersMessages.empty}
            getRowId={(row) => String(row.id)}
            onPageChange={(page) =>
              pushState({
                ...state,
                page,
              })
            }
            onPageSizeChange={(pageSize) =>
              pushState({
                ...state,
                page: 1,
                perPage: pageSize,
              })
            }
            pageSizeOptions={[50, 100, 150, 200]}
            paginationPosition='top'
          />
        </OrdersSelectionProvider>
      </Main>

      <AlertDialog open={buyLabelConfirmOpen} onOpenChange={setBuyLabelConfirmOpen}>
        <AlertDialogContent className='rounded-[6px] sm:max-w-2xl'>
          <AlertDialogHeader>
            <AlertDialogTitle>{ordersMessages.confirmBuyLabel}</AlertDialogTitle>
            <AlertDialogDescription>
              Xem trước cước vận chuyển cho {pendingBuyIds.length} đơn (chưa có label) trước khi mua:
            </AlertDialogDescription>
          </AlertDialogHeader>

          {previewLoading ? (
            <div className='py-4 text-center text-sm text-muted-foreground'>
              Đang tính giá…
            </div>
          ) : previewResult ? (
            <div className='space-y-2 text-sm'>
              <div className='max-h-56 overflow-x-auto overflow-y-auto rounded-[6px] border'>
                <table className='w-full'>
                  <thead className='sticky top-0 bg-muted/40 text-xs uppercase text-muted-foreground'>
                    <tr>
                      <th className='px-3 py-1.5 text-left'>Đơn / SP</th>
                      <th className='px-3 py-1.5 text-right'>Cân (g)</th>
                      <th className='px-3 py-1.5 text-right'>Cước</th>
                    </tr>
                  </thead>
                  <tbody>
                    {previewResult.items.map((it) => (
                      <Fragment key={it.order_id}>
                        {/* Order header: ref + total price */}
                        <tr className='border-t bg-muted/30'>
                          <td
                            className='whitespace-nowrap px-3 py-1.5 font-medium'
                            colSpan={2}
                          >
                            {it.ref_id ?? `#${it.order_id}`}
                          </td>
                          <td className='px-3 py-1.5 text-right font-semibold'>
                            {it.calculated_price != null
                              ? `$${it.calculated_price.toFixed(2)}`
                              : '—'}
                          </td>
                        </tr>
                        {/* One row per item — editable weight */}
                        {(it.line_items ?? []).map((li) => (
                          <tr key={li.item_id} className='border-t'>
                            <td className='px-3 py-1 pl-6'>
                              <span
                                className='block max-w-[320px] truncate text-[12px] text-muted-foreground'
                                title={li.name ?? ''}
                              >
                                {li.name || `#${li.item_id}`}
                                {li.quantity && li.quantity > 1
                                  ? ` ×${li.quantity}`
                                  : ''}
                              </span>
                            </td>
                            <td className='px-3 py-1 text-right'>
                              <input
                                type='number'
                                min={1}
                                value={
                                  itemWeights[String(li.item_id)] ??
                                  (li.weight ?? '')
                                }
                                onChange={(e) => {
                                  const raw = e.target.value
                                  setItemWeights((prev) => {
                                    const next = { ...prev }
                                    const n = Math.round(Number(raw))
                                    if (!raw || !Number.isFinite(n) || n <= 0) {
                                      delete next[String(li.item_id)]
                                    } else {
                                      next[String(li.item_id)] = n
                                    }
                                    return next
                                  })
                                }}
                                className='h-7 w-20 rounded-[4px] border border-input bg-background px-2 text-right text-[12px] outline-none focus-visible:ring-1 focus-visible:ring-ring'
                              />
                            </td>
                            <td />
                          </tr>
                        ))}
                      </Fragment>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className='flex items-center justify-between font-semibold'>
                <span>
                  Tổng ({previewResult.count} đơn)
                  {repricing ? (
                    <span className='ml-2 text-[11px] font-normal text-muted-foreground'>
                      đang tính lại…
                    </span>
                  ) : null}
                </span>
                <span>${previewResult.total.toFixed(2)}</span>
              </div>
              {previewResult.ineligible.length > 0 ? (
                <div className='rounded-[6px] bg-amber-50 p-2 text-xs text-amber-700'>
                  <div className='font-medium'>
                    {previewResult.ineligible.length} đơn sẽ bị bỏ qua:
                  </div>
                  <ul className='mt-1 list-disc space-y-0.5 pl-4'>
                    {previewResult.ineligible.map((i) => (
                      <li key={i.order_id}>
                        <span className='font-mono'>
                          {i.ref_id ?? `#${i.order_id}`}
                        </span>
                        {i.reasons?.length
                          ? ` — ${i.reasons.join('; ')}`
                          : null}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ) : null}

          <AlertDialogFooter>
            <AlertDialogCancel className='rounded-[6px]'>
              {messages.profile.cancel}
            </AlertDialogCancel>
            <AlertDialogAction
              className='rounded-[6px]'
              onClick={(event) => {
                event.preventDefault()
                void runCreateShipping(pendingBuyIds)
              }}
              disabled={
                buyingLabel ||
                previewLoading ||
                (previewResult != null && previewResult.count === 0)
              }
            >
              {buyingLabel ? ordersMessages.processing : ordersMessages.confirmPurchase}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog
        open={createShipConfirmOpen}
        onOpenChange={setCreateShipConfirmOpen}
      >
        <AlertDialogContent className='rounded-[6px]'>
          <AlertDialogHeader>
            <AlertDialogTitle>Tạo vận chuyển (forward label)</AlertDialogTitle>
            <AlertDialogDescription>
              {forwardIds.length} đơn đã có label sẵn sẽ được forward sang ShipDVX.
              Đây là đơn đã mua label trên sàn (vd TikTok) nên không tính cước.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className='rounded-[6px]'>
              {messages.profile.cancel}
            </AlertDialogCancel>
            <AlertDialogAction
              className='rounded-[6px]'
              onClick={(event) => {
                event.preventDefault()
                void runCreateShipping(forwardIds)
              }}
              disabled={buyingLabel || forwardIds.length === 0}
            >
              {buyingLabel ? ordersMessages.processing : 'Tạo vận chuyển'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={storeRequiredOpen} onOpenChange={setStoreRequiredOpen}>
        <AlertDialogContent className='rounded-[6px]'>
          <AlertDialogHeader>
            <AlertDialogTitle>{createOrderMessages.storeRequiredTitle}</AlertDialogTitle>
            <AlertDialogDescription>
              {createOrderMessages.storeRequiredDesc}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className='rounded-[6px]'>
              {messages.profile.cancel}
            </AlertDialogCancel>
            <AlertDialogAction
              className='rounded-[6px]'
              onClick={() => router.push('/lemiex/stores')}
            >
              {ordersMessages.actions.goToStores}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <Dialog open={typeDialogOpen} onOpenChange={setTypeDialogOpen}>
        <DialogContent className='rounded-[6px] sm:max-w-2xl'>
          <DialogHeader>
            <DialogTitle>{createOrderMessages.typeTitle}</DialogTitle>
            <DialogDescription>
              {createOrderMessages.typeDescTumbler}
            </DialogDescription>
          </DialogHeader>

          <div className='grid grid-cols-2 gap-4'>
            {orderTypes.map((type) => {
              const Icon = type.icon

              return (
                <button
                  key={type.id}
                  type='button'
                  className='flex flex-col rounded-[6px] border p-6 text-left transition-colors hover:bg-muted'
                  onClick={() => handleCreateOrderType(type.id)}
                >
                  <div className='mb-4 inline-flex rounded-[6px] bg-primary/10 p-3 text-primary'>
                    <Icon className='size-6' />
                  </div>
                  <div className='text-base font-semibold'>
                    {createOrderMessages[type.titleKey]}
                  </div>
                  <div className='mt-1.5 text-sm text-muted-foreground'>
                    {createOrderMessages[type.descriptionKey]}
                  </div>
                </button>
              )
            })}
          </div>

          <DialogFooter>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => setTypeDialogOpen(false)}
            >
              {messages.profile.cancel}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

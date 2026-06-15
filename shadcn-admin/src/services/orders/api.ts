import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { type AuthUser } from '@/stores/auth-store'
import { apiRequest } from '@/lib/client'

type Primitive = string | number | boolean | null | undefined

export type OrdersQueryParams = Record<
  string,
  Primitive | Primitive[] | undefined
>

export type OrderListItem = {
  id: number
  order_stt?: string | number | null
  order_type?: string | null
  ref_id?: string | null
  seller_ref?: string | null
  shipping_method?: string | null
  shipping_service?: string | null
  shipping_label?: string | null
  note?: string | null
  address_1?: string | null
  address_2?: string | null
  city?: string | null
  state?: string | null
  postcode?: string | null
  country?: string | null
  phone?: string | null
  first_name?: string | null
  last_name?: string | null
  fulfill_status?: string | null
  payment_status?: string | null
  fulfillment_priority?: string | null
  created_at?: string | null
  timestamps?: {
    created_at?: string | null
    updated_at?: string | null
    shipped_at?: string | null
  } | null
  pricing?: {
    print_cost?: number | null
    shipping_cost?: number | null
    total_cost?: number | null
    extra_fee?: number | null
    refund_fee?: number | null
    profit_margin?: number | null
  } | null
  total_cost?: number | null
  has_ticket?: boolean
  convert_label?: string | null
  support_ticket?: { id?: number | string | null } | null
  shipping?: {
    tracking_id?: string | null
    label_url?: string | null
    service?: string | null
    method?: string | null
    address?: {
      name?: string | null
      phone?: string | null
      street1?: string | null
      street2?: string | null
      city?: string | null
      state?: string | null
      zip?: string | null
      country?: string | null
    } | null
  } | null
  seller?: {
    id?: number | string | null
    username?: string | null
    name?: string | null
    email?: string | null
    tier?: string | null
    store_name?: string | null
  } | null
  store?: {
    id?: number | string | null
    name?: string | null
    api_key?: string | null
  } | null
  items?: Array<{
    id?: number | string
    product_name?: string | null
    variant_id?: string | null
    color?: string | null
    size?: string | null
    quantity?: number | null
    mockup?: string | null
    mockup_back?: string | null
    qr_codes?: string[] | null
    merge_images?: string[] | null
    variant?: {
      style?: string | null
      color?: string | null
      size?: string | null
      sku?: string | null
    } | null
    designs?: Array<{
      meta_id?: number | string | null
      embroidery_type?: string | null
      position?: string | null
      stitch_count?: number | null
      dst_url?: string | null
      emb_url?: string | null
      pes_url?: string | null
      pdf_url?: string | null
    }> | null
  }> | null
}

export type OrderDetail = OrderListItem

export type OrdersResponse = {
  success?: boolean
  status?: boolean
  data?: {
    orders?: OrderListItem[]
    pagination?: {
      current_page?: number
      last_page?: number
      per_page?: number
      total?: number
    }
    summary?: Record<string, unknown> | null
  }
  message?: string
}

export type OrderListResult = {
  orders: OrderListItem[]
  pagination: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
  }
  summary: Record<string, unknown> | null
}

export type SelectOption = {
  label: string
  value: string
  count?: number
}

export type OrderTimelineEvent = {
  id?: number | string | null
  action?: string | null
  note?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export type StoreOption = {
  id: number | string
  name: string
  api_key?: string | null
}

export type FulfillmentPriorityOption = {
  value: string
  label: string
  description?: string
}

export type ShippingMethodOption = {
  value: string
  label: string
  description?: string
}

export type ProductOption = {
  id: number | string
  name: string
}

export type ProductVariantDetail = {
  variant_id: string
  full_name?: string | null
  product_name?: string | null
  name?: string | null
}

export type CreateOrderPrintFilePayload = {
  key: string
  url: string
  url_emb: string
  url_pes: string
  embroidery_type: string
}

export type CreateOrderLineItemPayload = {
  variant_id: string
  product_name: string
  quantity: number
  mockup: string
  mockup_back: string
  mockup_sleeve_left: string
  mockup_sleeve_right: string
  print_files: CreateOrderPrintFilePayload[]
}

export type CreateLabelShipPayload = {
  order_type: 'label_ship'
  product_type: 'Print'
  ref_id: string
  api_key: string
  seller_ref: string
  order_status: string
  shipping_method: string
  shipping_service: string
  shipping_label: string
  fulfillment_priority: string
  note: string
  line_items: CreateOrderLineItemPayload[]
}

export type CreateSellerShipPayload = {
  order_type: 'seller_ship'
  product_type: 'Print'
  ref_id: string
  api_key: string
  seller_ref: string
  order_status: string
  shipping_method: string
  shipping_service: string
  fulfillment_priority: string
  note: string
  address: {
    name: string
    phone: string
    street1: string
    street2: string
    city: string
    state: string
    zip: string
    country: string
  }
  line_items: CreateOrderLineItemPayload[]
}

export type CreateOrderResponse = {
  status?: boolean
  success?: boolean
  message?: string
  data?: {
    order_id?: number | string | null
    id?: number | string | null
  } | null
}

export type UpdateOrderPrintFilePayload = {
  key: string
  url: string | null
  url_emb?: string | null
  url_pes?: string | null
}

export type UpdateOrderPayload = {
  id: number | string
  order_type: string
  ref_id: string | null | undefined
  api_key: string
  order_status: string | null | undefined
  shipping_method: string | null
  shipping_service: string | null
  note: string | null
  shipping_label: string | null
  address: {
    name: string | null
    street1: string | null
    street2: string | null
    city: string | null
    state: string | null
    zip: string | null
    country: string | null
    phone: string | null
  }
  line_items: Array<{
    // Existing order item id — lets the backend resolve the item even when the
    // variant_id is changed.
    id?: number | string | null
    variant_id: string | null | undefined
    product_name: string | null | undefined
    quantity: number
    mockup: string | null
    mockup_back: string | null
    print_files: UpdateOrderPrintFilePayload[]
  }>
}

export type UpdateOrderResponse = {
  status?: boolean
  success?: boolean
  message?: string
  data?: OrderDetail | null
}

function toQueryString(params: OrdersQueryParams) {
  const searchParams = new URLSearchParams()

  Object.entries(params).forEach(([key, rawValue]) => {
    if (rawValue === undefined || rawValue === null || rawValue === '') return

    if (Array.isArray(rawValue)) {
      const filteredValues = rawValue
        .map((value) => String(value).trim())
        .filter(Boolean)

      if (filteredValues.length > 0) {
        searchParams.set(key, filteredValues.join(','))
      }
      return
    }

    if (typeof rawValue === 'boolean') {
      if (rawValue) searchParams.set(key, 'true')
      return
    }

    searchParams.set(key, String(rawValue))
  })

  return searchParams.toString()
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  return apiRequest<T>(`${API_BASE_URL}${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...init?.headers,
    },
  })
}

export async function fetchOrders(params: OrdersQueryParams) {
  const queryString = toQueryString(params)
  const payload = await request<OrdersResponse>(
    `${API_ENDPOINTS.ORDERS}${queryString ? `?${queryString}` : ''}`
  )

  if (payload.success === false || payload.status === false) {
    throw new Error(payload.message || 'Không thể tải danh sách orders')
  }

  const orders = payload.data?.orders || []
  const pagination = payload.data?.pagination

  return {
    orders,
    pagination: {
      currentPage: pagination?.current_page || 1,
      lastPage:
        pagination?.last_page ||
        Math.max(1, Math.ceil(orders.length / Number(params.per_page || 20))),
      perPage: pagination?.per_page || Number(params.per_page || 20),
      total: pagination?.total || orders.length,
    },
    summary: payload.data?.summary || null,
  } satisfies OrderListResult
}

function mapListOptions(
  input: unknown,
  fallbackLabelKey?: string
): SelectOption[] {
  if (!Array.isArray(input)) return []

  return input
    .map((item) => {
      if (typeof item === 'string') {
        return { label: item, value: item }
      }

      if (item && typeof item === 'object') {
        const record = item as Record<string, unknown>
        const value = String(
          record.value ||
            record.name ||
            record.id ||
            record[fallbackLabelKey || 'label'] ||
            ''
        )
        const label = String(
          record.label ||
            record.display_name ||
            record.name ||
            record.username ||
            record.value ||
            ''
        )

        if (value) {
          return {
            value,
            label: label || value,
          }
        }
      }

      return null
    })
    .filter(Boolean) as SelectOption[]
}

export async function fetchOrderFulfillStatusOptions(params?: {
  date_from?: string
  date_to?: string
}) {
  const search = new URLSearchParams()
  if (params?.date_from) search.set('date_from', params.date_from)
  if (params?.date_to) search.set('date_to', params.date_to)
  const queryString = search.toString()

  const payload = await request<{ data?: unknown; status?: boolean }>(
    `${API_ENDPOINTS.ORDER_FULFILL_STATUSES}${queryString ? `?${queryString}` : ''}`
  )
  if (!Array.isArray(payload.data)) return []

  return payload.data
    .map((item) => {
      if (!item || typeof item !== 'object') return null
      const record = item as Record<string, unknown>
      const value = String(record.value || '')
      const label = String(record.label || record.name || value)

      if (!value) return null

      return {
        value,
        label,
        count:
          typeof record.count === 'number'
            ? record.count
            : Number(record.count || 0),
      }
    })
    .filter(Boolean) as SelectOption[]
}

export async function fetchOrderEmbroideryTypeOptions() {
  const payload = await request<{ data?: unknown; success?: boolean }>(
    API_ENDPOINTS.ORDER_EMBROIDERY_TYPES
  )
  return mapListOptions(payload.data)
}

export async function fetchSellerOptions() {
  const payload = await request<{
    data?: { data?: AuthUser[] } | AuthUser[]
    status?: boolean
  }>(`${API_ENDPOINTS.USERS}?${toQueryString({ role_id: 2, per_page: 100 })}`)

  const sellers = Array.isArray(payload.data)
    ? payload.data
    : payload.data?.data || []

  return sellers
    .map((seller) => {
      const id = seller.id
      const username =
        seller.username || seller.name || seller.full_name || seller.email
      if (!id || !username) return null

      return {
        value: String(id),
        label: String(username),
      }
    })
    .filter((item): item is SelectOption => Boolean(item))
}

async function fetchVariantOptions(
  endpoint: string,
  params?: OrdersQueryParams
) {
  const queryString = toQueryString(params || {})
  const payload = await request<{ data?: unknown; status?: boolean }>(
    `${endpoint}${queryString ? `?${queryString}` : ''}`
  )
  return mapListOptions(payload.data)
}

export async function fetchStyleOptions() {
  return fetchVariantOptions(API_ENDPOINTS.STYLES)
}

export async function fetchStores() {
  const payload = await request<{ data?: unknown; status?: boolean }>(
    API_ENDPOINTS.STORES
  )

  if (!Array.isArray(payload.data)) return []

  return payload.data
    .map((item) => {
      if (!item || typeof item !== 'object') return null
      const record = item as Record<string, unknown>
      const id = record.id
      const name = record.name

      if (!id || typeof name !== 'string') return null

      return {
        id: id as number | string,
        name,
        api_key:
          typeof record.api_key === 'string' ? record.api_key : null,
      } satisfies StoreOption
    })
    .filter(Boolean) as StoreOption[]
}

export async function fetchFulfillmentPriorities() {
  const payload = await request<{
    status?: boolean
    data?: { priorities?: unknown[] } | unknown
  }>(API_ENDPOINTS.FULFILLMENT_PRIORITIES)

  const priorities = Array.isArray(payload.data)
    ? payload.data
    : Array.isArray((payload.data as { priorities?: unknown[] } | undefined)?.priorities)
      ? ((payload.data as { priorities?: unknown[] }).priorities ?? [])
      : []

  return priorities
    .map((item) => {
      if (!item || typeof item !== 'object') return null
      const record = item as Record<string, unknown>
      const value = typeof record.name === 'string' ? record.name : ''
      const label =
        typeof record.display_name === 'string'
          ? record.display_name
          : value
      if (!value) return null

      return {
        value,
        label,
        description:
          typeof record.description === 'string' ? record.description : '',
      } satisfies FulfillmentPriorityOption
    })
    .filter(Boolean) as FulfillmentPriorityOption[]
}

export async function fetchEmbroideryTypesMetadata() {
  const payload = await request<{ status?: boolean; data?: unknown[] }>(
    API_ENDPOINTS.EMBROIDERY_TYPES
  )

  const standardOption: SelectOption = {
    value: 'standard',
    label: 'Standard',
  }

  if (!Array.isArray(payload.data)) {
    return [standardOption]
  }

  const apiTypes = payload.data
    .map((item) => {
      if (!item || typeof item !== 'object') return null
      const record = item as Record<string, unknown>
      const value = typeof record.value === 'string' ? record.value : ''
      const label = typeof record.label === 'string' ? record.label : value
      if (!value || value === 'standard') return null
      return { value, label } satisfies SelectOption
    })
    .filter(Boolean) as SelectOption[]

  return [standardOption, ...apiTypes]
}

export async function fetchShippingMethods() {
  const payload = await request<{ status?: boolean; data?: unknown[] }>(
    API_ENDPOINTS.METADATA_SHIPPING_METHODS
  )

  if (!Array.isArray(payload.data)) return []

  return payload.data
    .map((item) => {
      if (!item || typeof item !== 'object') return null
      const record = item as Record<string, unknown>
      const value = typeof record.value === 'string' ? record.value : ''
      const label = typeof record.label === 'string' ? record.label : value
      if (!value) return null

      return {
        value,
        label,
        description:
          typeof record.description === 'string' ? record.description : '',
      } satisfies ShippingMethodOption
    })
    .filter(Boolean) as ShippingMethodOption[]
}

export async function fetchProducts() {
  const payload = await request<{ data?: unknown }>(API_ENDPOINTS.PRODUCTS)
  const products = Array.isArray(payload.data) ? payload.data : []
  return products
    .map((item) => {
      if (!item || typeof item !== 'object') return null
      const record = item as Record<string, unknown>
      const id = record.id
      const name = typeof record.name === 'string' ? record.name : ''
      if (!id || !name) return null
      return { id: String(id), name }
    })
    .filter(Boolean) as { id: string; name: string }[]
}

export async function fetchProductColors(productId?: string) {
  if (!productId) return []
  const payload = await request<{ data?: unknown }>(
    `${API_ENDPOINTS.COLORS}?${toQueryString({ product_id: productId })}`
  )
  return mapListOptions(payload.data)
}

export async function fetchProductSizes(productId?: string) {
  if (!productId) return []
  const payload = await request<{ data?: unknown }>(
    `${API_ENDPOINTS.SIZES}?${toQueryString({ product_id: productId })}`
  )
  return mapListOptions(payload.data)
}

export async function fetchProductVariant(
  productId?: string,
  size?: string
) {
  if (!productId || !size) return null

  const payload = await request<{ data?: unknown }>(
    `${API_ENDPOINTS.PRODUCT_VARIANTS}?${toQueryString({
      product_id: productId,
      size,
      per_page: 1,
    })}`
  )

  const records =
    payload.data && typeof payload.data === 'object'
      ? Array.isArray((payload.data as Record<string, unknown>).data)
        ? ((payload.data as Record<string, unknown>).data as unknown[])
        : payload.data &&
            typeof (payload.data as Record<string, unknown>).data === 'object' &&
            Array.isArray(
              ((payload.data as Record<string, unknown>).data as Record<
                string,
                unknown
              >).data
            )
          ? ((((payload.data as Record<string, unknown>).data as Record<
              string,
              unknown
            >).data as unknown[]) ?? [])
          : []
      : []

  const firstRecord = records[0]
  if (!firstRecord || typeof firstRecord !== 'object') return null

  const record = firstRecord as Record<string, unknown>
  const variantId =
    typeof record.variant_id === 'string' ? record.variant_id : ''

  if (!variantId) return null

  return fetchVariantById(variantId)
}

export async function fetchVariantById(variantId: string) {
  const payload = await request<{ data?: unknown }>(
    `${API_ENDPOINTS.PRODUCT_VARIANTS}/${variantId}`
  )

  if (!payload.data || typeof payload.data !== 'object') return null
  const record = payload.data as Record<string, unknown>
  const id = typeof record.variant_id === 'string' ? record.variant_id : ''
  if (!id) return null

  return {
    variant_id: id,
    full_name:
      typeof record.full_name === 'string' ? record.full_name : null,
    product_name:
      typeof record.product_name === 'string' ? record.product_name : null,
    name: typeof record.name === 'string' ? record.name : null,
  } satisfies ProductVariantDetail
}

export async function fetchColorOptions(style?: string) {
  return fetchVariantOptions(API_ENDPOINTS.COLORS, { style })
}

export async function fetchSizeOptions(style?: string, color?: string) {
  return fetchVariantOptions(API_ENDPOINTS.SIZES, { style, color })
}

export async function fetchOrderIds(params: OrdersQueryParams) {
  const queryString = toQueryString(params)
  const payload = await request<{
    success?: boolean
    status?: boolean
    message?: string
    data?: unknown
  }>(`${API_ENDPOINTS.ORDER_IDS}${queryString ? `?${queryString}` : ''}`)

  if (payload.success === false || payload.status === false) {
    throw new Error(payload.message || 'Không thể lấy danh sách IDs')
  }

  if (Array.isArray(payload.data)) {
    return payload.data.map((item) => String(item))
  }

  if (payload.data && typeof payload.data === 'object') {
    const record = payload.data as Record<string, unknown>
    const ids = Array.isArray(record.ids)
      ? record.ids
      : Array.isArray(record.data)
        ? record.data
        : []

    return ids.map((item) => String(item))
  }

  return []
}

export async function fetchOrderTimeline(orderId: number | string) {
  const payload = await request<{
    success?: boolean
    status?: boolean
    message?: string
    data?: {
      timeline?: OrderTimelineEvent[] | null
    } | null
  }>(`${API_ENDPOINTS.ORDER_TIMELINE}/${orderId}/timeline`)

  if (payload.success === false || payload.status === false) {
    throw new Error(payload.message || 'Không thể tải timeline đơn hàng')
  }

  return payload.data?.timeline || []
}

export async function fetchOrderById(orderId: number | string) {
  const payload = await request<{
    success?: boolean
    status?: boolean
    message?: string
    data?: OrderDetail | null
  }>(`${API_ENDPOINTS.ORDERS}/${orderId}`)

  if (payload.success === false || payload.status === false) {
    throw new Error(payload.message || 'Không thể tải chi tiết đơn hàng')
  }

  if (!payload.data) {
    throw new Error('Không tìm thấy đơn hàng')
  }

  return payload.data
}

export async function createOrder(
  payload: CreateLabelShipPayload | CreateSellerShipPayload
) {
  const response = await request<CreateOrderResponse>(API_ENDPOINTS.ORDER_CREATE, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })

  if (response.status === false || response.success === false) {
    throw new Error(response.message || 'Không thể tạo đơn hàng')
  }

  return response
}

export async function updateOrder(payload: UpdateOrderPayload) {
  const response = await request<UpdateOrderResponse>('/orders/update', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })

  if (response.status === false || response.success === false) {
    throw new Error(response.message || 'Không thể cập nhật đơn hàng')
  }

  return response
}

type BuyLabelResponse = {
  status?: boolean
  success?: boolean
  message?: string
  data?: {
    tracking_number?: string | null
    dispatched?: number | null
  } | null
}

export async function buyLabelSingle(orderId: number | string) {
  const payload = await request<BuyLabelResponse>(API_ENDPOINTS.BUY_LABEL_SINGLE, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_id: orderId,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Failed to buy label')
  }

  return payload
}

export async function buyLabelBatch(orderIds: Array<number | string>) {
  const payload = await request<BuyLabelResponse>(API_ENDPOINTS.BUY_LABEL_BATCH, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_ids: orderIds,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Failed to buy label')
  }

  return payload
}

export async function remakeDesignFiles(orderItemMetaIds: Array<number | string>) {
  const payload = await request<{
    status?: boolean
    success?: boolean
    message?: string
  }>('/orders/remake/file', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_item_meta_ids: orderItemMetaIds,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Không thể remake design files')
  }

  return payload
}

export async function remakeOrderQr(orderItemIds: Array<number | string>) {
  const payload = await request<{
    status?: boolean
    success?: boolean
    message?: string
  }>('/orders/remake/qr', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_item_ids: orderItemIds,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Không thể remake QR')
  }

  return payload
}

export async function changeOrderFulfillStatus(
  orderId: number | string,
  fulfillStatus: string
) {
  const payload = await request<{
    status?: boolean
    success?: boolean
    message?: string
    data?: unknown
  }>('/orders/change-fulfill-status', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_id: orderId,
      fulfill_status: fulfillStatus,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Không thể đổi trạng thái đơn hàng')
  }

  return payload
}

export interface BatchChangeStatusResultItem {
  order_id: number
  success: boolean
  code?: number
  message?: string
  data?: unknown
}

export interface BatchChangeStatusResponse {
  target_status: string
  total: number
  success_count: number
  fail_count: number
  results: BatchChangeStatusResultItem[]
}

export async function batchChangeOrderFulfillStatus(
  orderIds: Array<number | string>,
  fulfillStatus: string
) {
  const payload = await request<{
    status?: boolean
    success?: boolean
    message?: string
    data?: BatchChangeStatusResponse
  }>('/orders/batch-change-fulfill-status', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_ids: orderIds.map((id) => Number(id)).filter((n) => !Number.isNaN(n)),
      fulfill_status: fulfillStatus,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Batch change failed')
  }

  return payload.data as BatchChangeStatusResponse
}

export async function sellerCancelOrder(
  orderId: number | string,
  reason = 'Cancelled by seller'
) {
  const payload = await request<{
    status?: boolean
    success?: boolean
    message?: string
    data?: unknown
  }>('/orders/seller-cancel', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      order_id: orderId,
      reason,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Không thể hủy đơn hàng')
  }

  return payload
}

export async function postOrderLabel(orderId: number | string) {
  const payload = await request<{
    status?: boolean
    success?: boolean
    message?: string
    data?: unknown
  }>('/orders/post-label', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id: orderId,
    }),
  })

  if (payload.status === false || payload.success === false) {
    throw new Error(payload.message || 'Không thể cập nhật label')
  }

  return payload
}

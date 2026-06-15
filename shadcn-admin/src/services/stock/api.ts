import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type StockSummary = {
  total_stock: number
  reserved: number
  available: number
  low_stock_items: number
}

export type StockFilterOptions = {
  styles: string[]
  colors: string[]
  sizes: string[]
}

export type StockFilters = {
  variant_id: string
  sku: string
  style: string
  color: string
  size: string
  stock_level: string
  active_status: string
}

export type StockVariant = {
  id: number
  variant_id: string
  sku?: string | null
  style?: string | null
  color?: string | null
  size?: string | null
  stock?: number | null
  reserved?: number | null
  available?: number | null
  active?: boolean | null
}

export type StockProduct = {
  id: number
  name: string
  style?: string | null
  variants: StockVariant[]
}

export type StockHistoryRecord = {
  id: number
  action: string
  before_quantity?: number | null
  after_quantity?: number | null
  reason?: string | null
  created_at?: string | null
  metadata?: {
    field?: string
    old_value?: string | number | boolean | null
    new_value?: string | number | boolean | null
    bulk_action?: boolean
    operation?: string
    amount_added?: number
    amount_subtracted?: number
    ip?: string
    timestamp?: string
  } | null
  user?: {
    username?: string | null
    email?: string | null
  } | null
}

export type ImportStockResult = {
  success_count?: number
  failed_count?: number
  errors?: string[]
}

type BaseResponse<T> = {
  code?: number
  status?: boolean
  message?: string
  data?: T
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

function readStoredToken() {
  if (typeof window === 'undefined') return ''
  return window.localStorage.getItem('lemiex_access_token') || ''
}

export async function fetchStockFilterOptions(): Promise<StockFilterOptions> {
  const response = await apiRequest<BaseResponse<StockFilterOptions>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_FILTER_OPTIONS}`,
    { method: 'GET' }
  )

  if (response.status && response.data) {
    return response.data
  }

  throw new Error(response.message || 'Failed to get filter options')
}

export async function fetchStockList(
  filters: Partial<StockFilters> = {}
): Promise<StockProduct[]> {
  const query = buildQueryString(filters)
  const response = await apiRequest<BaseResponse<StockProduct[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_LIST}${query ? `?${query}` : ''}`,
    { method: 'GET' }
  )

  if (response.status && response.data) {
    return response.data
  }

  throw new Error(response.message || 'Failed to get stock list')
}

export async function fetchStockSummary(
  productId?: number | null
): Promise<StockSummary> {
  const query = buildQueryString({ product_id: productId ?? undefined })
  const response = await apiRequest<BaseResponse<StockSummary>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_SUMMARY}${query ? `?${query}` : ''}`,
    { method: 'GET' }
  )

  if (response.status && response.data) {
    return response.data
  }

  throw new Error(response.message || 'Failed to get summary')
}

export async function updateStockVariant(
  variantId: number,
  data: Partial<{
    sku: string
    style: string
    stock: number
    active: boolean
  }>
): Promise<StockVariant> {
  const response = await apiRequest<BaseResponse<StockVariant>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_VARIANT_DETAIL}/${variantId}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    }
  )

  if (response.status && response.data) {
    return response.data
  }

  throw new Error(response.message || 'Failed to update variant')
}

export async function fetchStockVariantHistory(
  variantId: number
): Promise<StockHistoryRecord[]> {
  const response = await apiRequest<BaseResponse<StockHistoryRecord[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_VARIANT_DETAIL}/${variantId}/history`,
    { method: 'GET' }
  )

  if (response.status && response.data) {
    return response.data
  }

  throw new Error(response.message || 'Failed to get variant history')
}

export async function bulkUpdateStockVariants(params: {
  variantIds: number[]
  action: string
  stockValue?: number | null
  reason?: string | null
}) {
  const response = await apiRequest<BaseResponse<unknown>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_BULK_UPDATE}`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variant_ids: params.variantIds,
        action: params.action,
        stock_value: params.stockValue ?? null,
        reason: params.reason ?? null,
      }),
    }
  )

  if (response.status) {
    return response
  }

  throw new Error(response.message || 'Failed to bulk update variants')
}

export async function exportStock(filters: Partial<StockFilters> = {}) {
  const query = buildQueryString(filters)
  const token = readStoredToken()
  const response = await fetch(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_EXPORT}${query ? `?${query}` : ''}`,
    {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'text/csv',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    }
  )

  if (!response.ok) {
    throw new Error('Export failed')
  }

  const contentDisposition = response.headers.get('Content-Disposition')
  let filename = 'stock_export.csv'

  if (contentDisposition) {
    const match = contentDisposition.match(/filename="(.+)"/)
    if (match?.[1]) {
      filename = match[1]
    }
  }

  const blob = await response.blob()
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  window.URL.revokeObjectURL(url)
}

export async function importStock(
  file: File,
  stockType: 'set' | 'add_stock' | 'subtract_stock'
) {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('stock_type', stockType)

  try {
    const token = readStoredToken()
    const response = await fetch(`${API_BASE_URL}${API_ENDPOINTS.STOCK_IMPORT}`, {
      method: 'POST',
      credentials: 'include',
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
      body: formData,
    })

    const data = (await response.json()) as BaseResponse<ImportStockResult> & {
      errors?: Record<string, string[]>
    }

    if (!response.ok) {
      const errorMessages = data.errors
        ? Object.values(data.errors).flat()
        : data.message
          ? [data.message]
          : ['Import failed']

      return {
        success: false,
        message: data.message || 'Import failed',
        data: {
          success_count: 0,
          failed_count: 0,
          errors: errorMessages,
        } satisfies ImportStockResult,
      }
    }

    return {
      success: Boolean(data.status),
      message: data.message || '',
      data: data.data,
    }
  } catch (error) {
    if (error instanceof Error) {
      return {
        success: false,
        message: error.message,
        data: {
          success_count: 0,
          failed_count: 0,
          errors: [error.message],
        } satisfies ImportStockResult,
      }
    }

    throw error
  }
}

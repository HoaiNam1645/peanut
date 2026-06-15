import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type ShortageFilters = {
  pending_reason: string
  order_id: string
  variant_id: string
  style?: string
  date_from: string
  date_to: string
  sort_by: string
  sort_order: 'asc' | 'desc'
}

export type ShortageVariant = {
  variant_id: string
  style?: string | null
  color?: string | null
  size?: string | null
  stock?: number | null
  pending_demand?: number | null
  shortage?: number | null
}

export type ShortageVariantOrder = {
  order_id: number
  ref_id?: string | null
  seller?: string | null
  quantity?: number | null
  shortage?: number | null
  days_pending?: number | null
}

export type ShortageByVariantRecord = {
  variant_id: string
  style?: string | null
  color?: string | null
  size?: string | null
  stock?: number | null
  total_demand?: number | null
  total_shortage?: number | null
  orders_count?: number | null
  orders?: ShortageVariantOrder[]
}

export type ShortageByVariantSummary = {
  total_variants: number
  total_shortage: number
  total_orders_affected: number
}

export type ShortageOrder = {
  id: number
  ref_id?: string | null
  seller_username?: string | null
  total_items?: number | null
  pending_reason?: string | null
  total_shortage?: number | null
  days_pending?: number | null
  shortage_variants?: ShortageVariant[]
  missing_files?: string[]
}

export type ShortageSummary = {
  total_pending_orders: number
  orders_with_shortage: number
  total_variants_shortage: number
  total_quantity_shortage: number
}

export type ShortagePagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

type BaseResponse<T> = {
  code?: number
  status?: boolean
  message?: string
  data?: T
}

type ShortageReportPayload = {
  orders?: ShortageOrder[]
  summary?: Partial<ShortageSummary>
  pagination?: Partial<ShortagePagination>
}

type ShortageExportPayload = {
  csv?: string
  filename?: string
}

type ShortageByVariantPayload = {
  variants?: ShortageByVariantRecord[]
  summary?: Partial<ShortageByVariantSummary>
  pagination?: Partial<ShortagePagination>
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

export async function fetchShortageReport(params: {
  page: number
  per_page: number
} & Partial<ShortageFilters>) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<ShortageReportPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_SHORTAGE}?${query}`,
    { method: 'GET' }
  )

  if (!response.status || !response.data) {
    throw new Error(response.message || 'Failed to load shortage report')
  }

  return {
    orders: response.data.orders || [],
    summary: {
      total_pending_orders: response.data.summary?.total_pending_orders || 0,
      orders_with_shortage: response.data.summary?.orders_with_shortage || 0,
      total_variants_shortage: response.data.summary?.total_variants_shortage || 0,
      total_quantity_shortage: response.data.summary?.total_quantity_shortage || 0,
    } satisfies ShortageSummary,
    pagination: {
      current_page: response.data.pagination?.current_page || 1,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies ShortagePagination,
  }
}

export async function exportShortageReport(params: Partial<ShortageFilters>) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<ShortageExportPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_SHORTAGE_EXPORT}?${query}`,
    { method: 'GET' }
  )

  if (!response.status || !response.data?.csv) {
    throw new Error(response.message || 'Failed to export shortage report')
  }

  const blob = new Blob([response.data.csv], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = response.data.filename || 'shortage_report.csv'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

export async function fetchShortageByVariant(params: {
  page: number
  per_page: number
} & Partial<ShortageFilters>) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<ShortageByVariantPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_SHORTAGE_BY_VARIANT}?${query}`,
    { method: 'GET' }
  )

  if (!response.status || !response.data) {
    throw new Error(response.message || 'Failed to load shortage by variant')
  }

  return {
    variants: response.data.variants || [],
    summary: {
      total_variants: response.data.summary?.total_variants || 0,
      total_shortage: response.data.summary?.total_shortage || 0,
      total_orders_affected: response.data.summary?.total_orders_affected || 0,
    } satisfies ShortageByVariantSummary,
    pagination: {
      current_page: response.data.pagination?.current_page || 1,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies ShortagePagination,
  }
}

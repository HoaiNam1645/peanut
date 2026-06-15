'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type StockAuditLogFilters = {
  variant_id: string
  style: string
  color: string
  size: string
  action: string
  order_id: string
  date_from: string
  date_to: string
}

export type StockAuditLogRecord = {
  id: number
  created_at?: string | null
  variant_id?: string | null
  action?: string | null
  before_quantity?: number | null
  after_quantity?: number | null
  change?: number | null
  reason?: string | null
  order_id?: number | string | null
  user?: {
    username?: string | null
  } | null
  product?: {
    name?: string | null
    brand?: string | null
    style?: string | null
  } | null
  variant?: {
    color?: string | null
    size?: string | null
  } | null
}

export type StockAuditLogPagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type StockAuditLogFilterOptions = {
  styles: string[]
  colors: string[]
  sizes: string[]
  actions: string[]
}

export type VariantProductionRecord = {
  production_id?: number | string | null
  order_id?: number | string | null
  order_ref?: string | null
  quantity?: number | null
  status?: string | null
}

type BaseResponse<T> = {
  code?: number
  status?: boolean
  message?: string
  data?: T
}

type AuditLogListPayload = {
  logs?: StockAuditLogRecord[]
  pagination?: Partial<StockAuditLogPagination>
}

type VariantProductionPayload = {
  productions?: VariantProductionRecord[]
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

export async function fetchStockAuditLogs(params: {
  page: number
  per_page: number
} & Partial<StockAuditLogFilters>) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<AuditLogListPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_AUDIT_LOGS}?${query}`,
    { method: 'GET' }
  )

  if (!response.status || !response.data) {
    throw new Error(response.message || 'Failed to load audit logs')
  }

  return {
    logs: response.data.logs || [],
    pagination: {
      current_page: response.data.pagination?.current_page || params.page,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies StockAuditLogPagination,
  }
}

export async function fetchStockAuditLogFilterOptions() {
  const response = await apiRequest<BaseResponse<Partial<StockAuditLogFilterOptions>>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_AUDIT_LOG_FILTER_OPTIONS}`,
    { method: 'GET' }
  )

  if (!response.status || !response.data) {
    throw new Error(response.message || 'Failed to load filter options')
  }

  return {
    styles: response.data.styles || [],
    colors: response.data.colors || [],
    sizes: response.data.sizes || [],
    actions: response.data.actions || [],
  } satisfies StockAuditLogFilterOptions
}

export async function checkVariantProductions(variantId: string) {
  const query = buildQueryString({ variant_id: variantId })
  const response = await apiRequest<BaseResponse<VariantProductionPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.STOCK_AUDIT_LOG_CHECK_VARIANT}?${query}`,
    { method: 'GET' }
  )

  if (!response.status || !response.data) {
    throw new Error(response.message || 'Failed to check variant productions')
  }

  return response.data.productions || []
}

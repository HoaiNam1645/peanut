'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type StaffListOption = {
  id: number | string
  name?: string | null
  username?: string | null
}

export type StaffReportFilters = {
  date_from: string
  date_to: string
  staff_id: string
}

export type StaffSummaryRow = {
  staff_name?: string | null
  username?: string | null
  items_processed?: number | null
  percentage?: number | null
}

export type StaffDetailRow = {
  staff_name?: string | null
  username?: string | null
  order_id?: number | string | null
  item_id?: number | string | null
  meta_key?: string | null
  processed_at?: string | null
}

export type StaffDetailsPagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

type BaseResponse<T> = {
  success?: boolean
  message?: string
  data?: T
}

type StaffReportPayload = {
  summary?: StaffSummaryRow[]
  total_processed_in_period?: number
  details?: {
    data?: StaffDetailRow[]
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
  }
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

export async function fetchStaffList() {
  const response = await apiRequest<BaseResponse<StaffListOption[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.REPORT_STAFF_LIST}`,
    { method: 'GET' }
  )

  if (!response.success || !response.data) {
    throw new Error(response.message || 'Failed to load staff list')
  }

  return response.data
}

export async function fetchStaffReport(params: {
  page: number
  per_page: number
} & StaffReportFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<StaffReportPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.REPORT_STAFF}?${query}`,
    { method: 'GET' }
  )

  if (!response.success || !response.data) {
    throw new Error(response.message || 'Failed to load report data')
  }

  return {
    summary: response.data.summary || [],
    totalProcessed: response.data.total_processed_in_period || 0,
    details: response.data.details?.data || [],
    pagination: {
      current_page: response.data.details?.current_page || params.page,
      last_page: response.data.details?.last_page || 1,
      per_page: response.data.details?.per_page || params.per_page,
      total: response.data.details?.total || 0,
    } satisfies StaffDetailsPagination,
  }
}

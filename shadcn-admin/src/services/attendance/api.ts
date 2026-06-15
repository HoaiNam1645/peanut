'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type AttendanceFilters = {
  user_name?: string
  date?: string
  month?: string
  date_from?: string
  date_to?: string
}

export type AttendanceRow = {
  user_id: number | string
  user_name?: string | null
  total_days?: number | null
  total_hours_week?: string | null
  total_hours_month?: string | null
  total_hours_year?: string | null
}

export type AttendanceLogRow = {
  work_date?: string | null
  check_in?: string | null
  check_out?: string | null
  total_work?: string | null
  scan_count?: number | null
}

export type AttendancePagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

type BaseResponse<T> = {
  success?: boolean
  status?: boolean
  code?: number
  message?: string
  data?: T
  pagination?: Partial<AttendancePagination>
  errors?: Record<string, string[]>
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

async function requestWithErrors<T>(
  input: string,
  init?: RequestInit
): Promise<BaseResponse<T>> {
  const token =
    typeof window !== 'undefined'
      ? window.localStorage.getItem('lemiex_access_token') || ''
      : ''

  const response = await fetch(`${API_BASE_URL}${input}`, {
    credentials: 'include',
    ...init,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(init?.headers || {}),
    },
  })

  const data = (await response.json().catch(() => null)) as BaseResponse<T> | null

  if (!response.ok || !(data?.success || data?.status)) {
    const error = new Error(data?.message || 'Request failed') as Error & {
      errors?: Record<string, string[]>
    }
    error.errors = data?.errors
    throw error
  }

  return data
}

export async function fetchAttendances(params: {
  page: number
  per_page: number
} & AttendanceFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<AttendanceRow[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.ATTENDANCES}?${query}`,
    { method: 'GET' }
  )

  if (!(response.status || response.success) || !response.data) {
    throw new Error(response.message || 'Failed to load attendance data')
  }

  return {
    data: response.data || [],
    pagination: {
      current_page: response.pagination?.current_page || params.page,
      last_page: response.pagination?.last_page || 1,
      per_page: response.pagination?.per_page || params.per_page,
      total: response.pagination?.total || 0,
    } satisfies AttendancePagination,
  }
}

export async function importAttendance(file: File) {
  const formData = new FormData()
  formData.append('file', file)

  return requestWithErrors<unknown>(API_ENDPOINTS.ATTENDANCES_IMPORT, {
    method: 'POST',
    body: formData,
  })
}

export async function fetchAttendanceLogs(
  userId: number | string,
  params: {
    page: number
    per_page: number
  } & AttendanceFilters
) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<AttendanceLogRow[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.ATTENDANCE_LOGS}/${userId}?${query}`,
    { method: 'GET' }
  )

  if (!(response.status || response.success) || !response.data) {
    throw new Error(response.message || 'Failed to load attendance logs')
  }

  return {
    data: response.data || [],
    pagination: {
      current_page: response.pagination?.current_page || params.page,
      last_page: response.pagination?.last_page || 1,
      per_page: response.pagination?.per_page || params.per_page,
      total: response.pagination?.total || 0,
    } satisfies AttendancePagination,
  }
}

export async function completeMissingAttendanceLog(
  userId: number | string,
  payload: {
    work_date: string
    missing_type: 'check_in' | 'check_out'
    time: string
  }
) {
  return requestWithErrors<unknown>(
    `${API_ENDPOINTS.ATTENDANCE_LOGS}/${userId}/complete`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )
}

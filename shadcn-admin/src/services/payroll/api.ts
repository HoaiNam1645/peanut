'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type PayrollFilters = {
  month?: string
  date_from?: string
  date_to?: string
}

export type PayrollTier = {
  id: number | string
  name?: string | null
  hourly_rate?: number | string | null
  currency?: string | null
  description?: string | null
}

export type PayrollAdjustment = {
  id?: number | string
  type?: string | null
  amount?: number | string | null
  date?: string | null
  reason?: string | null
}

export type PayrollRow = {
  employee_id: number | string
  user_name?: string | null
  total_hours?: number | null
  current_rate?: number | null
  base_salary?: number | null
  gross_salary?: number | null
  final_salary?: number | null
  net_salary?: number | null
  company_tax?: number | null
  month?: string | null
  adjustments_detail?: PayrollAdjustment[] | null
}

export type CurrentSalary = {
  salary_tier_id?: number | string | null
  custom_hourly_rate?: number | string | null
  effective_date?: string | null
  note?: string | null
}

export type SalaryLogItem = {
  id: number | string
  custom_hourly_rate?: number | string | null
  effective_date?: string | null
  deleted_at?: string | null
  tier?: {
    name?: string | null
    hourly_rate?: number | string | null
  } | null
}

type BaseResponse<T> = {
  success?: boolean
  status?: boolean
  code?: number
  message?: string
  data?: T
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

export async function fetchPayrollTiers() {
  const response = await apiRequest<BaseResponse<PayrollTier[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.PAYROLL_TIERS}`,
    { method: 'GET' }
  )

  if (!(response.status || response.success) || !response.data) {
    throw new Error(response.message || 'Failed to load payroll tiers')
  }

  return response.data || []
}

export async function createPayrollTier(payload: {
  name: string
  hourly_rate: number
  currency: string
  description: string
}) {
  return requestWithErrors<PayrollTier>(API_ENDPOINTS.PAYROLL_TIERS, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export async function updatePayrollTier(
  tierId: number | string,
  payload: {
    name: string
    hourly_rate: number
    currency: string
    description: string
  }
) {
  return requestWithErrors<PayrollTier>(`${API_ENDPOINTS.PAYROLL_TIERS}/${tierId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export async function deletePayrollTier(tierId: number | string) {
  return requestWithErrors<unknown>(`${API_ENDPOINTS.PAYROLL_TIERS}/${tierId}`, {
    method: 'DELETE',
  })
}

export async function fetchPayrollReport(params: PayrollFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<PayrollRow[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.PAYROLL_REPORT}?${query}`,
    { method: 'GET' }
  )

  if (!(response.status || response.success) || !response.data) {
    throw new Error(response.message || 'Failed to load payroll report')
  }

  return response.data || []
}

export async function fetchCurrentSalary(employeeId: number | string) {
  const response = await apiRequest<BaseResponse<CurrentSalary>>(
    `${API_BASE_URL}${API_ENDPOINTS.PAYROLL_EMPLOYEES}/${employeeId}/current-salary`,
    { method: 'GET' }
  )

  if (!(response.status || response.success)) {
    throw new Error(response.message || 'Failed to load current salary')
  }

  return response.data || {}
}

export async function fetchSalaryLog(employeeId: number | string) {
  const response = await apiRequest<BaseResponse<SalaryLogItem[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.PAYROLL_EMPLOYEES}/${employeeId}/salary-log`,
    { method: 'GET' }
  )

  if (!(response.status || response.success) || !response.data) {
    throw new Error(response.message || 'Failed to load salary log')
  }

  return response.data || []
}

export async function createSalary(
  employeeId: number | string,
  payload: {
    salary_tier_id?: string | number
    custom_hourly_rate?: number
    effective_date: string
    note: string
  }
) {
  return requestWithErrors<unknown>(
    `${API_ENDPOINTS.PAYROLL_EMPLOYEES}/${employeeId}/salary`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function updateSalary(
  employeeId: number | string,
  payload: {
    salary_tier_id?: string | number
    custom_hourly_rate?: number
    effective_date: string
    note: string
  }
) {
  return requestWithErrors<unknown>(
    `${API_ENDPOINTS.PAYROLL_EMPLOYEES}/${employeeId}/salary`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function createPayrollAdjustment(payload: {
  employee_id: number | string
  type: string
  amount: number
  date: string
  reason: string
}) {
  return requestWithErrors<unknown>(API_ENDPOINTS.PAYROLL_ADJUSTMENTS, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export async function updatePayrollNetSalary(payload: {
  employee_id: number | string
  period?: string | null
  net_salary?: number
  company_tax?: number
}) {
  return requestWithErrors<unknown>(API_ENDPOINTS.PAYROLL_NET_SALARY, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

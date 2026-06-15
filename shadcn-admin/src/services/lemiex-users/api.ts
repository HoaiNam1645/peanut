'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type LemiexUserRole = {
  id: number | string
  name?: string | null
  display_name?: string | null
}

export type LemiexUserProfile = {
  avatar?: string | null
  first_name?: string | null
  last_name?: string | null
  phone?: string | null
  address?: string | null
  birthday?: string | null
  webhook_url?: string | null
  telegram_id?: string | null
  max_debit?: number | string | null
  max_date_debit?: number | string | null
  min_date_debit?: number | string | null
  wallet_balance?: number | string | null
  is_support_us?: boolean | null
  private_seller?: number | null
}

export type LemiexUserRecord = {
  id: number | string
  email?: string | null
  username?: string | null
  role_id?: number | string | null
  status?: string | null
  api_key?: string | null
  created_at?: string | null
  updated_at?: string | null
  role?: LemiexUserRole | null
  profile?: LemiexUserProfile | null
}

export type LemiexUsersPagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type LemiexUsersFilters = {
  search?: string
  status?: string
  role_id?: string
  tier?: string
}

export type UserMutationPayload = {
  email: string
  username?: string
  password?: string
  password_confirmation?: string
  role_id: string
  status: string
  first_name?: string
  last_name?: string
  phone?: string
  address?: string
  birthday?: string
  webhook_url?: string
  telegram_id?: string
  api_key?: string
  max_debit?: string
  max_date_debit?: string
  min_date_debit?: string
  is_support_us?: boolean
  tier_id?: number
}

type BaseResponse<T> = {
  status?: boolean
  success?: boolean
  code?: number
  message?: string | Record<string, string[]>
  data?: T
  errors?: Record<string, string[]>
}

type PaginatedUsersPayload = {
  data?: LemiexUserRecord[]
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
}

type AddFundResponse = {
  new_balance?: number
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
    const error = new Error(
      typeof data?.message === 'string' ? data.message : 'Request failed'
    ) as Error & {
      errors?: Record<string, string[]>
      responseMessage?: string | Record<string, string[]>
    }
    error.errors = data?.errors || (typeof data?.message === 'object' ? data.message : undefined)
    error.responseMessage = data?.message
    throw error
  }

  return data
}

export async function fetchLemiexUsers(params: {
  page: number
  per_page: number
} & LemiexUsersFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<PaginatedUsersPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.USERS}?${query}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(
      typeof response.message === 'string' ? response.message : 'Failed to load users'
    )
  }

  return {
    users: response.data.data || [],
    pagination: {
      current_page: response.data.current_page || params.page,
      last_page: response.data.last_page || 1,
      per_page: response.data.per_page || params.per_page,
      total: response.data.total || 0,
    } satisfies LemiexUsersPagination,
  }
}

export async function fetchLemiexUserById(userId: number | string) {
  const response = await apiRequest<BaseResponse<LemiexUserRecord>>(
    `${API_BASE_URL}${API_ENDPOINTS.USERS}/${userId}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(
      typeof response.message === 'string' ? response.message : 'Failed to load user'
    )
  }

  return response.data
}

export async function fetchLemiexUserRoles() {
  const response = await apiRequest<BaseResponse<LemiexUserRole[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.USER_ROLES}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(
      typeof response.message === 'string' ? response.message : 'Failed to load roles'
    )
  }

  return response.data
}

export async function createLemiexUser(payload: UserMutationPayload) {
  return requestWithErrors<LemiexUserRecord>(API_ENDPOINTS.USERS, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

export async function updateLemiexUser(
  userId: number | string,
  payload: UserMutationPayload
) {
  return requestWithErrors<LemiexUserRecord>(`${API_ENDPOINTS.USERS}/${userId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

export async function deleteLemiexUser(userId: number | string) {
  return requestWithErrors<unknown>(`${API_ENDPOINTS.USERS}/${userId}`, {
    method: 'DELETE',
  })
}

export async function addFundToLemiexUser(
  userId: number | string,
  payload: { type: 'Deposit' | 'Withdraw'; amount: number; note: string }
) {
  return requestWithErrors<AddFundResponse>(`${API_ENDPOINTS.USERS}/${userId}/add-fund`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type StoreListFilters = {
  search?: string
  status?: string
}

export type StoreUser = {
  id: number | string
  username?: string | null
  email?: string | null
  role?: unknown
}

export type StoreRecord = {
  id: number
  name?: string | null
  status?: string | null
  created_at?: string | null
  api_key?: string | null
  user?: {
    id?: number | string
    username?: string | null
    email?: string | null
  } | null
}

export type StorePagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type StoreMutationPayload = {
  user_id: string | number
  name: string
  api_key: string
  status?: string
}

type BaseResponse<T> = {
  success?: boolean
  status?: boolean
  code?: number
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

type StoreListPayload = {
  stores?: StoreRecord[]
  pagination?: Partial<StorePagination>
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

  if (!response.ok || !data?.success) {
    const error = new Error(data?.message || 'Request failed') as Error & {
      errors?: Record<string, string[]>
    }
    error.errors = data?.errors
    throw error
  }

  return data
}

export async function fetchStoresList(params: {
  page: number
  per_page: number
} & StoreListFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<StoreListPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.STORES_LIST}?${query}`,
    { method: 'GET' }
  )

  if (!response.success || !response.data) {
    throw new Error(response.message || 'Failed to load stores')
  }

  return {
    stores: response.data.stores || [],
    pagination: {
      current_page: response.data.pagination?.current_page || 1,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies StorePagination,
  }
}

export async function fetchStoreById(storeId: number | string) {
  const response = await apiRequest<BaseResponse<StoreRecord>>(
    `${API_BASE_URL}${API_ENDPOINTS.STORES}/${storeId}`,
    { method: 'GET' }
  )

  if (!(response.status || response.success) || !response.data) {
    throw new Error(response.message || 'Failed to load store')
  }

  return response.data
}

export async function fetchStoreUsers() {
  const response = await apiRequest<BaseResponse<StoreUser[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.STORE_USERS}`,
    { method: 'GET' }
  )

  if (!response.success || !response.data) {
    throw new Error(response.message || 'Failed to load store users')
  }

  return response.data
}

export async function createStore(payload: StoreMutationPayload) {
  return requestWithErrors<StoreRecord>(API_ENDPOINTS.STORES, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

export async function updateStore(
  storeId: number | string,
  payload: StoreMutationPayload
) {
  return requestWithErrors<StoreRecord>(`${API_ENDPOINTS.STORES}/${storeId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

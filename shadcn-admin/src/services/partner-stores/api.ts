'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type PartnerStoreRecord = {
  id: number
  name: string
  code: string
  total_order?: number | null
  status?: string | null
  account_no?: string | null
  created_at?: string | null
  updated_at?: string | null
  partner_app?: {
    id: number
    name?: string | null
    slug?: string | null
    proxy_status?: string | null
    status?: string | null
  } | null
  partnerApp?: {
    id: number
    name?: string | null
    slug?: string | null
    proxy_status?: string | null
    status?: string | null
  } | null
  user?: {
    id?: number | string
    username?: string | null
    email?: string | null
  } | null
}

export type PartnerStoreUser = {
  id: number | string
  username?: string | null
  email?: string | null
  role?: {
    id?: number | string
    name?: string | null
  } | null
}

export type PartnerStorePagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type PartnerStoreListFilters = {
  search?: string
  status?: string
  partner_app_id?: string | number
}

export type PartnerStoreMutationPayload = {
  name: string
  code: string
  user_id: string | number
  partner_app_id: string | number
  status?: string
  account_no?: string
  total_order?: number
}

type BaseResponse<T> = {
  status?: boolean
  success?: boolean
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

type PartnerStoreListPayload = {
  partner_stores?: PartnerStoreRecord[]
  pagination?: Partial<PartnerStorePagination>
}

function normalizePartnerStore(store: PartnerStoreRecord): PartnerStoreRecord {
  return store
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

function getSuccess(response: BaseResponse<unknown>) {
  return Boolean(response.status || response.success)
}

export async function fetchPartnerStores(
  params: { page: number; per_page: number } & PartnerStoreListFilters
) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<PartnerStoreListPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_STORES}?${query}`,
    { method: 'GET' }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to load partner stores')
  }

  return {
    stores: (response.data.partner_stores || []).map(normalizePartnerStore),
    pagination: {
      current_page: response.data.pagination?.current_page || 1,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies PartnerStorePagination,
  }
}

export async function fetchPartnerStoreUsers() {
  const response = await apiRequest<
    BaseResponse<PartnerStoreUser[] | { users?: PartnerStoreUser[] }>
  >(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_STORE_USERS}`,
    { method: 'GET' }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to load users')
  }

  if (Array.isArray(response.data)) {
    return response.data
  }

  return Array.isArray(response.data.users) ? response.data.users : []
}

export async function createPartnerStore(payload: PartnerStoreMutationPayload) {
  const response = await apiRequest<BaseResponse<PartnerStoreRecord>>(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_STORES}`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to create partner store')
  }

  return normalizePartnerStore(response.data)
}

export async function updatePartnerStore(
  partnerStoreId: number | string,
  payload: PartnerStoreMutationPayload
) {
  const response = await apiRequest<BaseResponse<PartnerStoreRecord>>(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_STORES}/${partnerStoreId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to update partner store')
  }

  return normalizePartnerStore(response.data)
}

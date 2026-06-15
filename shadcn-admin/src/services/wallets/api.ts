'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type WalletSeller = {
  id?: number | string
  username?: string | null
  email?: string | null
}

export type WalletOrder = {
  id?: number | string
  order_stt?: string | null
  store?: {
    id?: number | string
    name?: string | null
  } | null
}

export type WalletTransaction = {
  id: number | string
  transaction_id?: string | null
  seller?: WalletSeller | null
  order?: WalletOrder | null
  type?: string | null
  amount?: number | null
  remaining_balance?: number | null
  note?: string | null
  status?: string | null
  created_at?: string | null
}

export type WalletTransactionsSummary = {
  total_amount: number
  page_amount: number
}

export type WalletPagination = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type WalletTransactionFilters = {
  seller_id?: string
  date_from?: string
  date_to?: string
  type?: string
  status?: string
  search?: string
}

export type PendingFundRequest = WalletTransaction

type BaseResponse<T> = {
  success?: boolean
  status?: boolean
  code?: number
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

type TransactionsPayload = {
  transactions?: WalletTransaction[]
  pagination?: Partial<WalletPagination>
  summary?: Partial<WalletTransactionsSummary>
}

type PendingFundsPayload = {
  transactions?: PendingFundRequest[]
  pagination?: Partial<WalletPagination>
}

type ExportPayload = {
  csv?: string
  filename?: string
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

export async function fetchWalletTransactions(params: {
  page: number
  per_page: number
} & WalletTransactionFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<TransactionsPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.TRANSACTIONS}?${query}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load transactions')
  }

  return {
    transactions: response.data.transactions || [],
    pagination: {
      current_page: response.data.pagination?.current_page || params.page,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies WalletPagination,
    summary: {
      total_amount: Number(response.data.summary?.total_amount || 0),
      page_amount: Number(response.data.summary?.page_amount || 0),
    } satisfies WalletTransactionsSummary,
  }
}

export async function exportWalletTransactions(
  params: WalletTransactionFilters
) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<ExportPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.TRANSACTIONS_EXPORT}?${query}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to export transactions')
  }

  return {
    csv: response.data.csv || '',
    filename: response.data.filename || 'transactions.csv',
  }
}

export async function fetchTransactionSellers() {
  const response = await apiRequest<BaseResponse<WalletSeller[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.TRANSACTIONS_SELLERS}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load sellers')
  }

  return response.data
}

export async function fetchPendingFundRequests(params: {
  page: number
  per_page: number
}) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<PendingFundsPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.TRANSACTIONS_PENDING}?${query}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load pending fund requests')
  }

  return {
    requests: response.data.transactions || [],
    pagination: {
      current_page: response.data.pagination?.current_page || params.page,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies WalletPagination,
  }
}

export async function approvePendingFund(transactionId: number | string) {
  return requestWithErrors<unknown>(
    `${API_ENDPOINTS.TRANSACTIONS}/${transactionId}/approve`,
    {
      method: 'POST',
    }
  )
}

export async function rejectPendingFund(
  transactionId: number | string,
  reason?: string
) {
  return requestWithErrors<unknown>(
    `${API_ENDPOINTS.TRANSACTIONS}/${transactionId}/reject`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ reason: reason || null }),
    }
  )
}

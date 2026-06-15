'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type TicketStatus = 0 | 1

export type TicketUser = {
  id?: number | string
  username?: string | null
  email?: string | null
}

export type TicketOrder = {
  id?: number | string
  order_stt?: string | null
}

export type TicketMessage = {
  id: number | string
  message?: string | null
  created_at?: string | null
  user?: TicketUser | null
}

export type TicketRecord = {
  id: number | string
  subject?: string | null
  status?: TicketStatus | number | null
  user_reply?: TicketUser | null
  owner?: TicketUser | null
  order?: TicketOrder | null
  last_reply?: string | null
  updated_at?: string | null
  created_at?: string | null
}

export type TicketDetail = TicketRecord & {
  messages?: TicketMessage[]
}

export type TicketFilters = {
  ticket_id?: string
  order_id?: string
  subject?: string
  seller_id?: string
  support_id?: string
  status?: string | number
}

export type TicketPagination = {
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
  errors?: Record<string, string[]>
}

type TicketListPayload = {
  tickets?: TicketRecord[]
  pagination?: Partial<TicketPagination>
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

export async function fetchTickets(params: {
  page: number
  per_page: number
} & TicketFilters) {
  const query = buildQueryString(params)
  const response = await apiRequest<BaseResponse<TicketListPayload>>(
    `${API_BASE_URL}${API_ENDPOINTS.TICKETS}?${query}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load tickets')
  }

  return {
    tickets: response.data.tickets || [],
    pagination: {
      current_page: response.data.pagination?.current_page || params.page,
      last_page: response.data.pagination?.last_page || 1,
      per_page: response.data.pagination?.per_page || params.per_page,
      total: response.data.pagination?.total || 0,
    } satisfies TicketPagination,
  }
}

export async function fetchTicketById(ticketId: number | string) {
  const response = await apiRequest<BaseResponse<TicketDetail>>(
    `${API_BASE_URL}${API_ENDPOINTS.TICKETS}/${ticketId}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load ticket')
  }

  return response.data
}

export async function createTicket(payload: FormData) {
  return requestWithErrors<TicketDetail>(API_ENDPOINTS.TICKETS, {
    method: 'POST',
    body: payload,
  })
}

export async function updateTicketStatus(
  ticketId: number | string,
  status: TicketStatus | number
) {
  return requestWithErrors<TicketDetail>(`${API_ENDPOINTS.TICKETS}/${ticketId}/status`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ status }),
  })
}

export async function sendTicketMessage(
  ticketId: number | string,
  message: string,
  file?: File | null
) {
  if (file) {
    const formData = new FormData()
    formData.append('message', message)
    formData.append('file', file)

    return requestWithErrors<TicketMessage>(
      `${API_ENDPOINTS.TICKETS}/${ticketId}/messages`,
      {
        method: 'POST',
        body: formData,
      }
    )
  }

  return requestWithErrors<TicketMessage>(`${API_ENDPOINTS.TICKETS}/${ticketId}/messages`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ message }),
  })
}

export async function fetchTicketSellers() {
  const response = await apiRequest<BaseResponse<TicketUser[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.TICKET_SELLERS}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load sellers')
  }

  return response.data
}

export async function fetchTicketSupports() {
  const response = await apiRequest<BaseResponse<TicketUser[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.TICKET_SUPPORTS}`,
    { method: 'GET' }
  )

  if (!(response.success || response.status) || !response.data) {
    throw new Error(response.message || 'Failed to load supports')
  }

  return response.data
}

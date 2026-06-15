'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

type BaseResponse<T> = {
  success?: boolean
  status?: boolean
  code?: number
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

export type TierExtraFee = {
  id: number
  tier_id: number
  min_stitch: number
  max_stitch: number
  amount: number
}

export type TierRefundFee = {
  id: number
  tier_id: number
  stitch: number
  amount: number
}

export type TierEmbroideryFee = {
  id: number
  tier_id: number
  embroidery_type: string
  min_stitch: number
  max_stitch: number
  amount: number
}

export type TierPriorityFee = {
  id: number
  tier_id: number
  name: string
  display_name: string
  description?: string | null
  price: number
  active?: boolean | null
}

export type TierRecord = {
  id: number
  tier_id: number
  name: string
  extra_fees?: TierExtraFee[]
  refund_fees?: TierRefundFee[]
  embroidery_fees?: TierEmbroideryFee[]
  priority_fees?: TierPriorityFee[]
}

type TierListPayload = {
  tiers?: TierRecord[]
}

function isSuccess<T>(response: BaseResponse<T>) {
  return Boolean(response.success || response.status)
}

async function requestWithErrors<T>(input: string, init?: RequestInit) {
  const response = await apiRequest<BaseResponse<T>>(`${API_BASE_URL}${input}`, init)

  if (!isSuccess(response)) {
    const error = new Error(response.message || 'Request failed') as Error & {
      errors?: Record<string, string[]>
    }
    error.errors = response.errors
    throw error
  }

  return response
}

export async function fetchTiers() {
  const response = await requestWithErrors<TierListPayload>(API_ENDPOINTS.TIERS, {
    method: 'GET',
  })

  return response.data?.tiers || []
}

export async function createTier(name: string) {
  return requestWithErrors<TierRecord>(API_ENDPOINTS.TIERS, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name }),
  })
}

export async function updateTier(tierId: number, name: string) {
  return requestWithErrors<TierRecord>(`${API_ENDPOINTS.TIERS}/${tierId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name }),
  })
}

export async function deleteTier(tierId: number) {
  return requestWithErrors<unknown>(`${API_ENDPOINTS.TIERS}/${tierId}`, {
    method: 'DELETE',
  })
}

export async function createExtraFee(
  tierId: number,
  payload: { min_stitch: number; max_stitch: number; amount: number }
) {
  return requestWithErrors<TierExtraFee>(`${API_ENDPOINTS.TIERS}/${tierId}/extra-fee`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export async function updateExtraFee(
  tierId: number,
  feeId: number,
  payload: { min_stitch: number; max_stitch: number; amount: number }
) {
  return requestWithErrors<TierExtraFee>(
    `${API_ENDPOINTS.TIERS}/${tierId}/extra-fee/${feeId}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function deleteExtraFee(tierId: number, feeId: number) {
  return requestWithErrors<unknown>(`${API_ENDPOINTS.TIERS}/${tierId}/extra-fee/${feeId}`, {
    method: 'DELETE',
  })
}

export async function createRefundFee(
  tierId: number,
  payload: { stitch: number; amount: number }
) {
  return requestWithErrors<TierRefundFee>(`${API_ENDPOINTS.TIERS}/${tierId}/refund-fee`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export async function updateRefundFee(
  tierId: number,
  feeId: number,
  payload: { stitch: number; amount: number }
) {
  return requestWithErrors<TierRefundFee>(
    `${API_ENDPOINTS.TIERS}/${tierId}/refund-fee/${feeId}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function deleteRefundFee(tierId: number, feeId: number) {
  return requestWithErrors<unknown>(`${API_ENDPOINTS.TIERS}/${tierId}/refund-fee/${feeId}`, {
    method: 'DELETE',
  })
}

export async function createEmbroideryFee(
  tierId: number,
  payload: {
    embroidery_type: string
    min_stitch: number
    max_stitch: number
    amount: number
  }
) {
  return requestWithErrors<TierEmbroideryFee>(
    `${API_ENDPOINTS.TIERS}/${tierId}/embroidery-fee`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function updateEmbroideryFee(
  tierId: number,
  feeId: number,
  payload: {
    embroidery_type: string
    min_stitch: number
    max_stitch: number
    amount: number
  }
) {
  return requestWithErrors<TierEmbroideryFee>(
    `${API_ENDPOINTS.TIERS}/${tierId}/embroidery-fee/${feeId}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function deleteEmbroideryFee(tierId: number, feeId: number) {
  return requestWithErrors<unknown>(
    `${API_ENDPOINTS.TIERS}/${tierId}/embroidery-fee/${feeId}`,
    {
      method: 'DELETE',
    }
  )
}

export async function createPriorityFee(
  tierId: number,
  payload: {
    name: string
    display_name: string
    description?: string | null
    price: number
  }
) {
  return requestWithErrors<TierPriorityFee>(`${API_ENDPOINTS.TIERS}/${tierId}/priority-fee`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
}

export async function updatePriorityFee(
  tierId: number,
  feeId: number,
  payload: {
    name: string
    display_name: string
    description?: string | null
    price: number
  }
) {
  return requestWithErrors<TierPriorityFee>(
    `${API_ENDPOINTS.TIERS}/${tierId}/priority-fee/${feeId}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )
}

export async function deletePriorityFee(tierId: number, feeId: number) {
  return requestWithErrors<unknown>(`${API_ENDPOINTS.TIERS}/${tierId}/priority-fee/${feeId}`, {
    method: 'DELETE',
  })
}

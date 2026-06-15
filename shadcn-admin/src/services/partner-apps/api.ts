'use client'

import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type PartnerAppRecord = {
  id: number
  name: string
  slug: string
  auth_url?: string | null
  token?: string | null
  api_url?: string | null
  proxy_status?: string | null
  status?: string | null
  created_at?: string | null
  updated_at?: string | null
}

type BaseResponse<T> = {
  status?: boolean
  success?: boolean
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

export type PartnerAppMutationPayload = {
  name: string
  slug: string
  auth_url?: string
  token?: string
  api_url?: string
  proxy_status?: string
  status?: string
}

function getSuccess(response: BaseResponse<unknown>) {
  return Boolean(response.status || response.success)
}

export async function fetchPartnerApps() {
  const response = await apiRequest<BaseResponse<PartnerAppRecord[]>>(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_APPS}`,
    { method: 'GET' }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to load partner apps')
  }

  return response.data
}

export async function createPartnerApp(payload: PartnerAppMutationPayload) {
  const response = await apiRequest<BaseResponse<PartnerAppRecord>>(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_APPS}`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to create partner app')
  }

  return response.data
}

export async function updatePartnerApp(
  partnerAppId: number | string,
  payload: PartnerAppMutationPayload
) {
  const response = await apiRequest<BaseResponse<PartnerAppRecord>>(
    `${API_BASE_URL}${API_ENDPOINTS.PARTNER_APPS}/${partnerAppId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )

  if (!getSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to update partner app')
  }

  return response.data
}

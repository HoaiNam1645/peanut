'use client'

import { API_BASE_URL } from '@/config/api'
import { apiRequest } from '@/lib/client'

type BaseResponse<T> = {
  code?: number
  status?: boolean
  success?: boolean
  message?: string
  data?: T
}

export type PermissionRecord = {
  id: number
  name?: string | null
  display_name?: string | null
  description?: string | null
  group?: string | null
  route?: string | null
  method?: string | null
  removable?: boolean | null
}

export type CreatePermissionPayload = {
  name: string
  display_name: string
  description?: string | null
  group?: string | null
  route?: string | null
  method?: string | null
}

export type PermissionRole = {
  id: number
  name?: string | null
  display_name?: string | null
  description?: string | null
  removable?: boolean | null
}

type PermissionMatrixEntry = {
  role?: PermissionRole | null
  permissions?: number[]
}

type PermissionMatrixPayload = {
  roles?: PermissionRole[]
  permissions?: PermissionRecord[]
  grouped?: Record<string, PermissionRecord[]>
  groups?: string[]
  matrix?: Record<string, PermissionMatrixEntry>
}

function isSuccess(response: BaseResponse<unknown>) {
  return Boolean(response.status || response.success)
}

export async function fetchPermissionMatrix() {
  const response = await apiRequest<BaseResponse<PermissionMatrixPayload>>(
    `${API_BASE_URL}/permissions/matrix`,
    { method: 'GET' }
  )

  if (!isSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to load permissions')
  }

  return response.data
}

export async function fetchPermissions() {
  const response = await apiRequest<
    BaseResponse<{
      permissions?: PermissionRecord[]
      grouped?: Record<string, PermissionRecord[]>
      groups?: string[]
    }>
  >(`${API_BASE_URL}/permissions`, { method: 'GET' })

  if (!isSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to load permissions')
  }

  return response.data
}

export async function createPermission(payload: CreatePermissionPayload) {
  const response = await apiRequest<BaseResponse<PermissionRecord>>(
    `${API_BASE_URL}/permissions`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )

  if (!isSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to create permission')
  }

  return response.data
}

export async function updateRolePermissions(roleId: number, permissionIds: number[]) {
  const response = await apiRequest<BaseResponse<{ permissions?: number[] }>>(
    `${API_BASE_URL}/permissions/roles/${roleId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ permissions: permissionIds }),
    }
  )

  if (!isSuccess(response)) {
    throw new Error(response.message || 'Failed to update permissions')
  }

  return response
}

export type CreateRolePayload = {
  name: string
  display_name: string
  description?: string | null
  permission_ids?: number[]
}

export type UpdateRolePayload = {
  name?: string
  display_name?: string
  description?: string | null
}

export async function createRole(payload: CreateRolePayload) {
  const response = await apiRequest<BaseResponse<PermissionRole>>(
    `${API_BASE_URL}/permissions/roles`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )

  if (!isSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to create role')
  }

  return response.data
}

export async function updateRole(roleId: number, payload: UpdateRolePayload) {
  const response = await apiRequest<BaseResponse<PermissionRole>>(
    `${API_BASE_URL}/permissions/roles/${roleId}/info`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  )

  if (!isSuccess(response) || !response.data) {
    throw new Error(response.message || 'Failed to update role')
  }

  return response.data
}

export async function deleteRole(roleId: number) {
  const response = await apiRequest<BaseResponse<unknown>>(
    `${API_BASE_URL}/permissions/roles/${roleId}`,
    { method: 'DELETE' }
  )

  if (!isSuccess(response)) {
    throw new Error(response.message || 'Failed to delete role')
  }

  return response
}

export async function seedPermissionsFromRoutes() {
  const response = await apiRequest<
    BaseResponse<{ created?: number; total_defined?: number; already_exists?: number }>
  >(`${API_BASE_URL}/permissions/seed`, {
    method: 'POST',
  })

  if (!isSuccess(response)) {
    throw new Error(response.message || 'Failed to sync permissions')
  }

  return response
}

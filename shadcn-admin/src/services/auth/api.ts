import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import type { AuthUser, LemiexRole } from '@/stores/auth-store'
import { apiRequest } from '@/lib/client'

type AuthSuccessResult = {
  success: true
  token: string
  user: AuthUser
}

type AuthFailureResult = {
  success: false
  message: string
}

type AuthResult = AuthSuccessResult | AuthFailureResult

function formatErrorMessage(message: unknown) {
  if (typeof message === 'string') return message

  if (message && typeof message === 'object') {
    const errors = Object.values(message as Record<string, unknown>).flat()
    return errors.filter(Boolean).join(', ')
  }

  return 'Đã có lỗi xảy ra. Vui lòng thử lại.'
}

export function getUserRoleName(user: AuthUser | null | undefined): LemiexRole | null {
  if (!user) return null

  const role =
    (typeof user.role === 'object' && user.role
      ? user.role.name
      : user.role) || user.role_name

  return (role as LemiexRole | null) || null
}

export function getLoginRedirectPath(user: AuthUser | null | undefined) {
  return '/lemiex/dashboard'
}

export async function login(loginValue: string, password: string): Promise<AuthResult> {
  try {
    const data = await apiRequest<{
      status?: boolean
      data?: { token?: string; user?: AuthUser }
      message?: unknown
    }>(`${API_BASE_URL}${API_ENDPOINTS.LOGIN}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        email: loginValue,
        login: loginValue,
        password,
      }),
    })

    if (data.status && data.data?.token && data.data?.user) {
      return {
        success: true,
        token: data.data.token as string,
        user: data.data.user as AuthUser,
      }
    }

    return {
      success: false,
      message: formatErrorMessage(data.message),
    }
  } catch (error) {
    return {
      success: false,
      message:
        error instanceof Error
          ? error.message
          : 'Không thể kết nối đến server. Vui lòng thử lại sau.',
    }
  }
}

export async function logout(accessToken?: string) {
  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    }

    if (accessToken) {
      headers.Authorization = `Bearer ${accessToken}`
    }

    await apiRequest(`${API_BASE_URL}${API_ENDPOINTS.LOGOUT}`, {
      method: 'POST',
      headers,
    })
  } catch {
    // Ignore logout API failures and clear local session anyway.
  }
}

export async function fetchCurrentUser() {
  try {
    const data = await apiRequest<{
      status?: boolean
      data?: AuthUser
      message?: unknown
    }>(`${API_BASE_URL}${API_ENDPOINTS.ME}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    })

    if (data.status && data.data) {
      return {
        success: true as const,
        user: data.data as AuthUser,
      }
    }

    return {
      success: false as const,
      message: formatErrorMessage(data.message),
    }
  } catch (error) {
    return {
      success: false as const,
      message:
        error instanceof Error ? error.message : 'Không thể lấy thông tin user',
    }
  }
}

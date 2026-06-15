'use client'

import { useEffect } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import {
  canAccessLemiexPath,
  extractPermissionNames,
  getDefaultLemiexRouteForPermissions,
  getLemiexRole,
} from '@/features/lemiex/layout/sidebar-data'
import { useAuthStore } from '@/stores/auth-store'

type RouteGuardProps = {
  children: React.ReactNode
}

export function RouteGuard({ children }: RouteGuardProps) {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const hydrated = useAuthStore((state) => state.auth.hydrated)
  const accessToken = useAuthStore((state) => state.auth.accessToken)
  const serverChecked = useAuthStore((state) => state.auth.serverChecked)
  const user = useAuthStore((state) => state.auth.user)

  useEffect(() => {
    if (!hydrated) return

    const currentSearch = searchParams.toString()
    const currentPath = currentSearch ? `${pathname}?${currentSearch}` : pathname

    if (!accessToken) {
      router.replace(`/login?redirect=${encodeURIComponent(currentPath)}`)
      return
    }

    if (!user) return
    if (!serverChecked) return

    if (!pathname.startsWith('/lemiex')) return

    const role = getLemiexRole(user?.role)
    const permissionNames = extractPermissionNames(user?.role)
    if (canAccessLemiexPath(role, pathname, permissionNames)) return

    router.replace(getDefaultLemiexRouteForPermissions(role, permissionNames))
  }, [accessToken, hydrated, pathname, router, searchParams, serverChecked, user])

  if (!hydrated) return null
  if (!accessToken) return null
  if (!user) return null
  if (!serverChecked) return null

  if (pathname.startsWith('/lemiex')) {
    const role = getLemiexRole(user?.role)
    const permissionNames = extractPermissionNames(user?.role)
    if (!canAccessLemiexPath(role, pathname, permissionNames)) return null
  }

  return <>{children}</>
}

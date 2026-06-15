'use client'

import { useEffect, useRef } from 'react'
import { fetchCurrentUser } from '@/services/auth/api'
import { useAuthStore } from '@/stores/auth-store'

export function AuthBootstrap() {
  const hydrate = useAuthStore((state) => state.auth.hydrate)
  const hydrated = useAuthStore((state) => state.auth.hydrated)
  const accessToken = useAuthStore((state) => state.auth.accessToken)
  const setUser = useAuthStore((state) => state.auth.setUser)
  const setServerChecked = useAuthStore((state) => state.auth.setServerChecked)
  const reset = useAuthStore((state) => state.auth.reset)
  const bootstrappedTokenRef = useRef<string>('')

  useEffect(() => {
    hydrate()
  }, [hydrate])

  useEffect(() => {
    if (!hydrated || !accessToken) return
    if (bootstrappedTokenRef.current === accessToken) return

    let cancelled = false
    bootstrappedTokenRef.current = accessToken

    fetchCurrentUser()
      .then((result) => {
        if (cancelled || !result.success || !result.user) {
          if (!cancelled) reset()
          return
        }

        if (!cancelled) {
          setUser(result.user)
          setServerChecked(true)
        }
      })
      .catch(() => {
        if (!cancelled) reset()
      })

    return () => {
      cancelled = true
    }
  }, [accessToken, hydrated, reset, setServerChecked, setUser])

  useEffect(() => {
    if (accessToken) return
    bootstrappedTokenRef.current = ''
  }, [accessToken])

  return null
}

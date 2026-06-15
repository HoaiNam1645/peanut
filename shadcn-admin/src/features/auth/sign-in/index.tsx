'use client'

import { useSearch } from '@/lib/router'
import { SignIn2 } from './sign-in-2'

export function SignIn() {
  const { redirect } = useSearch<{ redirect?: string }>({
    from: '/(auth)/sign-in',
  })

  return <SignIn2 redirectTo={redirect} />
}

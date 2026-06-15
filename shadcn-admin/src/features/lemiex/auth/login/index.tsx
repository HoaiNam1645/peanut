'use client'

import { User } from 'lucide-react'
import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useSearch } from '@/lib/router'
import { getLoginRedirectPath } from '@/services/auth/api'
import { UserAuthForm } from '@/features/auth/sign-in/components/user-auth-form'
import { useAuthStore } from '@/stores/auth-store'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

type LemiexLoginProps = {
  redirectTo?: string
}

export function LemiexLogin({ redirectTo }: LemiexLoginProps) {
  const router = useRouter()
  const search = useSearch<{ redirect?: string }>()
  const hydrated = useAuthStore((state) => state.auth.hydrated)
  const accessToken = useAuthStore((state) => state.auth.accessToken)
  const user = useAuthStore((state) => state.auth.user)
  const targetRedirect = redirectTo || search.redirect

  useEffect(() => {
    if (!hydrated || !accessToken) return

    router.replace(targetRedirect || getLoginRedirectPath(user))
  }, [accessToken, hydrated, router, targetRedirect, user])

  if (!hydrated) return null
  if (accessToken) return null

  return (
    <div className='flex min-h-svh items-center justify-center bg-background px-4 py-10 sm:px-6'>
      <Card className='relative z-10 w-full max-w-md border-border/80 bg-card/95 shadow-[0_24px_80px_rgba(0,0,0,0.08)] backdrop-blur-sm dark:shadow-[0_24px_80px_rgba(0,0,0,0.35)]'>
        <CardHeader className='space-y-5 text-center'>
          <div className='mx-auto flex h-20 w-20 items-center justify-center rounded-3xl border border-border bg-foreground shadow-sm'>
            <User className='h-10 w-10 text-background' strokeWidth={1.5} />
          </div>
          <div className='space-y-2'>
            <CardTitle className='text-2xl font-semibold tracking-tight'>
              Đăng nhập Wecat
            </CardTitle>
            <CardDescription className='text-sm leading-6'>
              Nhập tài khoản của bạn để truy cập hệ thống quản trị Wecat.
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent>
          <UserAuthForm
            redirectTo={targetRedirect}
            defaultRedirectTo='/lemiex/dashboard'
            showForgotPassword={false}
            showSignUpPrompt={false}
          />
        </CardContent>
      </Card>
    </div>
  )
}

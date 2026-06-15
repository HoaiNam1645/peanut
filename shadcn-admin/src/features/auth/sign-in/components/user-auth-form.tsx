'use client'

import { useState } from 'react'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useNavigate } from '@/lib/router'
import { Loader2, LogIn } from 'lucide-react'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'
import { login as loginWithApi, getLoginRedirectPath } from '@/services/auth/api'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { PasswordInput } from '@/components/password-input'

const formSchema = z.object({
  login: z.string().min(1, 'Vui lòng nhập email hoặc tên đăng nhập'),
  password: z
    .string()
    .min(1, 'Vui lòng nhập mật khẩu')
    .min(7, 'Mật khẩu phải có ít nhất 7 ký tự'),
})

interface UserAuthFormProps extends React.HTMLAttributes<HTMLFormElement> {
  redirectTo?: string
  defaultRedirectTo?: string
  showForgotPassword?: boolean
  showSignUpPrompt?: boolean
}

export function UserAuthForm({
  className,
  redirectTo,
  defaultRedirectTo = '/lemiex/dashboard',
  showForgotPassword = true,
  showSignUpPrompt = true,
  ...props
}: UserAuthFormProps) {
  const [isLoading, setIsLoading] = useState(false)
  const navigate = useNavigate()
  const { auth } = useAuthStore()

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      login: '',
      password: '',
    },
  })

  async function onSubmit(data: z.infer<typeof formSchema>) {
    setIsLoading(true)
    form.clearErrors('root')

    const result = await loginWithApi(data.login, data.password)

    if (!result.success) {
      form.setError('root', {
        message: result.message,
      })
      setIsLoading(false)
      return
    }

    auth.setUser(result.user)
    auth.setAccessToken(result.token)

    const targetPath =
      redirectTo || getLoginRedirectPath(result.user) || defaultRedirectTo

    navigate({ to: targetPath, replace: true })
    setIsLoading(false)
  }

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className={cn('grid gap-3', className)}
        {...props}
      >
        {form.formState.errors.root?.message ? (
          <div className='rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-destructive'>
            {form.formState.errors.root.message}
          </div>
        ) : null}
        <FormField
          control={form.control}
          name='login'
          render={({ field }) => (
            <FormItem>
              <FormLabel>Email hoặc tài khoản</FormLabel>
              <FormControl>
                <Input placeholder='ban@example.com' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem className='relative'>
              <FormLabel>Mật khẩu</FormLabel>
              <FormControl>
                <PasswordInput placeholder='********' {...field} />
              </FormControl>
              <FormMessage />
              {showForgotPassword ? (
                <Link
                  to='/forgot-password'
                  className='absolute end-0 -top-0.5 text-sm font-medium text-muted-foreground hover:opacity-75'
                >
                  Quên mật khẩu?
                </Link>
              ) : null}
            </FormItem>
          )}
        />
        <Button className='mt-2' disabled={isLoading}>
          {isLoading ? <Loader2 className='animate-spin' /> : <LogIn />}
          Đăng nhập
        </Button>
        {showSignUpPrompt ? (
          <p className='text-center text-sm text-muted-foreground'>
            Chưa có tài khoản?{' '}
            <Link
              to='/sign-up'
              className='font-medium underline underline-offset-4 hover:text-primary'
            >
              Tạo tài khoản mới
            </Link>
          </p>
        ) : null}
      </form>
    </Form>
  )
}

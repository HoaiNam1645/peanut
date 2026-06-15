'use client'

import { Link, useNavigate } from '@/lib/router'
import { toast } from 'sonner'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { AuthLayout } from '../auth-layout'
import { SignUpForm } from './components/sign-up-form'

export function SignUp() {
  const navigate = useNavigate()
  const handleAccountComplete = (email: string) => {
    toast.success(`Tạo tài khoản thành công cho ${email}.`)
    navigate({ to: '/sign-in', replace: true })
  }

  return (
    <AuthLayout>
      <Card className='w-full gap-4 border-border/80 shadow-sm'>
        <CardHeader>
          <CardTitle className='text-lg tracking-tight'>
            Tạo tài khoản mới
          </CardTitle>
        </CardHeader>

        <CardContent className='space-y-5'>
          <div className='text-sm text-muted-foreground'>
            Nhập email và mật khẩu để tạo tài khoản mới. Đã có tài khoản?{' '}
            <Link
              to='/sign-in'
              className='font-medium underline underline-offset-4 hover:text-primary'
            >
              Đăng nhập
            </Link>
          </div>
          <SignUpForm onComplete={handleAccountComplete} />
        </CardContent>
      </Card>
    </AuthLayout>
  )
}

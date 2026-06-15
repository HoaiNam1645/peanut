import { Suspense } from 'react'
import { ForgotPassword } from '@/features/auth/forgot-password'

export default function ForgotPasswordPage() {
  return (
    <Suspense fallback={null}>
      <ForgotPassword />
    </Suspense>
  )
}

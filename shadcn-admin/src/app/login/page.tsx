import { Suspense } from 'react'
import { LemiexLogin } from '@/features/lemiex/auth/login'

export default function LoginPage() {
  return (
    <Suspense fallback={null}>
      <LemiexLogin />
    </Suspense>
  )
}

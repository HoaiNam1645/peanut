import { Suspense } from 'react'
import { Otp } from '@/features/auth/otp'

export default function OtpPage() {
  return (
    <Suspense fallback={null}>
      <Otp />
    </Suspense>
  )
}

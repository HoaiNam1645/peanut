import { Suspense } from 'react'
import { SignIn } from '@/features/auth/sign-in'

export default function SignInPage() {
  return (
    <Suspense fallback={null}>
      <SignIn />
    </Suspense>
  )
}

import { Suspense } from 'react'
import { SignIn2 } from '@/features/auth/sign-in/sign-in-2'

export default function SignIn2Page() {
  return (
    <Suspense fallback={null}>
      <SignIn2 />
    </Suspense>
  )
}

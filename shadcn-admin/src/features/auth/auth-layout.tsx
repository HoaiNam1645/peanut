'use client'

import { Suspense } from 'react'
import Image from 'next/image'

type AuthLayoutProps = {
  children: React.ReactNode
}

export function AuthLayout({ children }: AuthLayoutProps) {
  return (
    <div className='container flex min-h-svh max-w-none items-center justify-center px-4 py-8 sm:px-6'>
      <div className='mx-auto flex w-full max-w-[640px] flex-col justify-center space-y-2'>
        <div className='mb-4 flex items-center justify-center'>
          <div className='relative me-3 h-10 w-10 overflow-hidden rounded-xl'>
            <Image src='/logo.jpg' alt='NutriWise logo' fill className='object-cover' />
          </div>
          <h1 className='text-xl font-semibold'>NutriWise Health</h1>
        </div>
        <Suspense fallback={null}>{children}</Suspense>
      </div>
    </div>
  )
}

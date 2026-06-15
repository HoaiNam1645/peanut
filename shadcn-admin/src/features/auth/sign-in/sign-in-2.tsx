'use client'

import Image from 'next/image'
import { cn } from '@/lib/utils'
import previewLogin from '../../../../public/images/preview-sign-in.png'
import { UserAuthForm } from './components/user-auth-form'

type SignIn2Props = {
  redirectTo?: string
}

export function SignIn2({ redirectTo }: SignIn2Props) {
  return (
    <div className='relative container grid h-svh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 lg:px-0'>
      <div className='lg:p-8'>
        <div className='mx-auto flex w-full flex-col justify-center space-y-2 py-8 sm:w-[480px] sm:p-8'>
          <div className='mb-4 flex items-center justify-center'>
            <div className='relative me-3 h-10 w-10 overflow-hidden rounded-xl'>
              <Image src='/logo.jpg' alt='NutriWise Health logo' fill className='object-cover' />
            </div>
            <h1 className='text-xl font-semibold'>NutriWise Health</h1>
          </div>
        </div>
        <div className='mx-auto flex w-full max-w-sm flex-col justify-center space-y-2'>
          <UserAuthForm redirectTo={redirectTo} />
        </div>
      </div>

      <div
        className={cn(
          'relative h-full overflow-hidden bg-[#eef4fb] max-lg:hidden',
          'before:absolute before:inset-x-0 before:top-0 before:h-40 before:bg-white/30 before:backdrop-blur-sm',
          '[&>span]:absolute [&>span]:inset-0'
        )}
      >
        <div className='absolute inset-0 bg-[radial-gradient(circle_at_center,_rgba(255,255,255,0.95)_0%,_rgba(238,244,251,0.75)_38%,_rgba(238,244,251,1)_100%)]' />
        <div className='absolute inset-0 flex items-end justify-end p-10'>
          <div className='relative w-full max-w-[980px] translate-x-10 translate-y-16 rounded-[2rem] shadow-[0_32px_90px_rgba(15,23,42,0.14)] xl:translate-x-14 xl:translate-y-20'>
            <Image
              src={previewLogin}
              className='h-auto w-full rounded-[2rem] select-none'
              priority
              alt='NutriWise dashboard preview'
            />
          </div>
        </div>
      </div>
    </div>
  )
}

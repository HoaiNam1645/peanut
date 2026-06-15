import type { Metadata } from 'next'
import Link from 'next/link'

export const metadata: Metadata = {
  title: 'Policies | DragonBug LLC',
}

export default function PoliciesLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <div className='flex min-h-svh flex-col bg-background'>
      <main className='mx-auto w-full max-w-4xl flex-1 px-4 py-8 sm:px-6 lg:px-8'>
        <div className='mb-6 text-sm text-muted-foreground'>
          <Link href='/tasks' className='underline-offset-4 hover:underline'>
            Back to dashboard
          </Link>
        </div>
        <article className='space-y-3 text-sm leading-6 text-muted-foreground [&_h1]:mb-3 [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:text-foreground'>
          {children}
        </article>
      </main>
    </div>
  )
}

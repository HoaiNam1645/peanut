import type { Metadata } from 'next'
import { Inter, Manrope } from 'next/font/google'

import { Providers } from '@/components/providers'
import './globals.css'

const inter = Inter({
  subsets: ['latin'],
  variable: '--font-inter-next',
  display: 'swap',
})

const manrope = Manrope({
  subsets: ['latin'],
  variable: '--font-manrope-next',
  display: 'swap',
})

export const metadata: Metadata = {
  title: 'Shadcn Admin Dashboard',
  description: 'Admin dashboard built with Next.js and shadcn/ui.',
}

const themeInitScript = `
(() => {
  const root = document.documentElement
  const match = document.cookie.match(/(?:^|; )vite-ui-theme=([^;]+)/)
  const savedTheme = match ? decodeURIComponent(match[1]) : 'system'
  const resolvedTheme =
    savedTheme === 'dark' || savedTheme === 'light'
      ? savedTheme
      : window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light'

  root.classList.remove('light', 'dark')
  root.classList.add(resolvedTheme)
})()
`

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html
      lang='vi'
      suppressHydrationWarning
    >
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInitScript }} />
      </head>
      <body
        suppressHydrationWarning
        className={`${inter.variable} ${manrope.variable} min-h-svh bg-background font-inter text-foreground antialiased`}
      >
        <Providers>{children}</Providers>
      </body>
    </html>
  )
}

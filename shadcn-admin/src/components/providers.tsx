'use client'

import { ThemeProvider } from '@/context/theme-provider'
import { FontProvider } from '@/context/font-provider'
import { DirectionProvider } from '@/context/direction-provider'
import { I18nProvider } from '@/context/i18n-provider'
import { AuthBootstrap } from '@/components/auth/auth-bootstrap'
import { NavigationProgress } from '@/components/navigation-progress'
import { Toaster } from '@/components/ui/sonner'

type ProvidersProps = {
  children: React.ReactNode
  initialTheme?: 'light' | 'dark' | 'system'
}

export function Providers({ children, initialTheme }: ProvidersProps) {
  return (
    <I18nProvider>
      <ThemeProvider initialTheme={initialTheme}>
        <FontProvider>
          <DirectionProvider>
            <AuthBootstrap />
            <NavigationProgress />
            {children}
            <Toaster duration={5000} />
          </DirectionProvider>
        </FontProvider>
      </ThemeProvider>
    </I18nProvider>
  )
}

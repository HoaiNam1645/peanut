'use client'

import { useNavigate, useLocation } from '@/lib/router'
import { useI18n } from '@/context/i18n-provider'
import { useAuthStore } from '@/stores/auth-store'
import { logout } from '@/services/auth/api'
import { ConfirmDialog } from '@/components/confirm-dialog'

interface SignOutDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function SignOutDialog({ open, onOpenChange }: SignOutDialogProps) {
  const navigate = useNavigate()
  const location = useLocation()
  const { auth } = useAuthStore()
  const { messages } = useI18n()

  const handleSignOut = async () => {
    await logout(auth.accessToken)
    auth.reset()
    onOpenChange(false)
    // Preserve current location for redirect after sign-in
    const currentPath = location.href
    navigate({
      to: '/login',
      search: { redirect: currentPath },
      replace: true,
    })
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      title={messages.profile.signOutTitle}
      desc={messages.profile.signOutDesc}
      cancelBtnText={messages.profile.cancel}
      confirmText={messages.profile.signOut}
      destructive
      handleConfirm={() => void handleSignOut()}
      className='sm:max-w-sm'
    />
  )
}

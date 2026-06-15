'use client'

import { LemiexPageShell } from '@/features/lemiex/components/lemiex-page-shell'

export function LemiexWallets() {
  return (
    <LemiexPageShell
      title='Lemiex Wallets'
      description='Vùng chuẩn bị cho transactions, pending fund, surcharge và debits.'
      routePath='/lemiex/wallets'
    />
  )
}

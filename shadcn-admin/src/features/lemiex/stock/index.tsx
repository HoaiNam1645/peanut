'use client'

import { LemiexPageShell } from '@/features/lemiex/components/lemiex-page-shell'

export function LemiexStock() {
  return (
    <LemiexPageShell
      title='Lemiex Stock'
      description='Vùng chuẩn bị cho stock dashboard, manage stock và audit logs.'
      routePath='/lemiex/stock'
    />
  )
}

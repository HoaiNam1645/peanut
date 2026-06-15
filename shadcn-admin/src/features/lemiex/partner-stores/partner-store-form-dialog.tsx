'use client'

import { useEffect, useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useI18n } from '@/context/i18n-provider'
import type { AuthUser } from '@/stores/auth-store'
import type { PartnerAppRecord } from '@/services/partner-apps/api'
import {
  createPartnerStore,
  fetchPartnerStoreUsers,
  type PartnerStoreRecord,
  type PartnerStoreUser,
  updatePartnerStore,
} from '@/services/partner-stores/api'

const fallbackMessages = {
  createTitle: 'Add Partner Store',
  editTitle: 'Edit Partner Store',
  storeName: 'Store Name',
  storeCode: 'Shop Code',
  user: 'Staff',
  partnerApp: 'Partner App',
  status: 'Status',
  accountNo: 'Account No',
  cancel: 'Cancel',
  create: 'Submit',
  update: 'Update',
  successCreate: 'Partner store created successfully',
  successUpdate: 'Partner store updated successfully',
  na: 'N/A',
}

type FormState = {
  name: string
  code: string
  user_id: string
  partner_app_id: string
  status: string
  account_no: string
}

export function PartnerStoreFormDialog({
  open,
  mode,
  store,
  apps,
  currentUser,
  onOpenChange,
  onComplete,
}: {
  open: boolean
  mode: 'create' | 'edit'
  store: PartnerStoreRecord | null
  apps: PartnerAppRecord[]
  currentUser: AuthUser | null
  onOpenChange: (open: boolean) => void
  onComplete: (message: string) => void
}) {
  const { messages } = useI18n()
  const ui = useMemo(
    () =>
      ((messages as { partnerStoresPage?: { dialog?: typeof fallbackMessages } })
        .partnerStoresPage?.dialog ?? fallbackMessages),
    [messages]
  )
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')
  const isSeller = roleName === 'Seller'

  const [saving, setSaving] = useState(false)
  const [users, setUsers] = useState<PartnerStoreUser[]>([])
  const [form, setForm] = useState<FormState>({
    name: '',
    code: '',
    user_id: '',
    partner_app_id: '',
    status: 'Pending',
    account_no: '',
  })

  useEffect(() => {
    let active = true

    async function initialize() {
      if (!open) return

      if (isSeller && currentUser?.id) {
        if (!active) return
        setUsers([
          {
            id: currentUser.id,
            username: currentUser.username,
            email: currentUser.email,
          },
        ])
      } else {
        const nextUsers = await fetchPartnerStoreUsers()
        if (!active) return
        setUsers(nextUsers)
      }

      if (!active) return

      setForm({
        name: store?.name || '',
        code: store?.code || '',
        user_id:
          String(store?.user?.id || (isSeller && currentUser?.id ? currentUser.id : '')) || '',
        partner_app_id: String(store?.partnerApp?.id || store?.partner_app?.id || ''),
        status: store?.status || 'Active',
        account_no: store?.account_no || '',
      })
    }

    void initialize()
    return () => {
      active = false
    }
  }, [currentUser, isSeller, open, store])

  function setField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }))
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        code: form.code.trim(),
        user_id: form.user_id,
        partner_app_id: form.partner_app_id,
        ...(mode === 'edit'
          ? {
              status: form.status.trim(),
              account_no: form.account_no.trim(),
            }
          : {}),
      }

      if (mode === 'create') {
        await createPartnerStore(payload)
        onComplete(ui.successCreate)
      } else if (store) {
        await updatePartnerStore(store.id, payload)
        onComplete(ui.successUpdate)
      }

      onOpenChange(false)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='rounded-[6px] sm:max-w-2xl'>
        <DialogHeader>
          <DialogTitle>{mode === 'create' ? ui.createTitle : ui.editTitle}</DialogTitle>
        </DialogHeader>

        <form className='space-y-4' onSubmit={handleSubmit}>
          <div className='space-y-2'>
            <Label>{ui.storeName}</Label>
            <Input
              className='h-11 rounded-[6px]'
              value={form.name}
              onChange={(event) => setField('name', event.target.value)}
              disabled={saving}
            />
          </div>

          <div className='space-y-2'>
            <Label>{ui.storeCode}</Label>
            <Input
              className='h-11 rounded-[6px]'
              value={form.code}
              onChange={(event) => setField('code', event.target.value)}
              disabled={saving}
            />
          </div>

          <div className='grid gap-4 md:grid-cols-2'>
            <div className='space-y-2'>
              <Label>{ui.user}</Label>
              <Select
                value={form.user_id}
                onValueChange={(value) => setField('user_id', value)}
                disabled={saving || isSeller}
              >
                <SelectTrigger className='h-11 w-full rounded-[6px]'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {users.map((user) => (
                      <SelectItem key={String(user.id)} value={String(user.id)}>
                      {user.username || ui.na} ({user.email || ui.na})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className='space-y-2'>
              <Label>{ui.partnerApp}</Label>
              <Select
                value={form.partner_app_id}
                onValueChange={(value) => setField('partner_app_id', value)}
                disabled={saving}
              >
                <SelectTrigger className='h-11 w-full rounded-[6px]'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {apps.map((app) => (
                    <SelectItem key={app.id} value={String(app.id)}>
                      {app.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {mode === 'edit' ? (
            <div className='grid gap-4 md:grid-cols-3'>
              <div className='space-y-2'>
                <Label>{ui.status}</Label>
                <Input
                  className='h-11 rounded-[6px]'
                  value={form.status}
                  onChange={(event) => setField('status', event.target.value)}
                  disabled={saving}
                />
              </div>

              <div className='space-y-2'>
                <Label>{ui.accountNo}</Label>
                <Input
                  className='h-11 rounded-[6px]'
                  value={form.account_no}
                  onChange={(event) => setField('account_no', event.target.value)}
                  disabled={saving}
                />
              </div>

            </div>
          ) : null}

          <DialogFooter>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => onOpenChange(false)}
              disabled={saving}
            >
              {ui.cancel}
            </Button>
            <Button type='submit' className='rounded-[6px]' disabled={saving}>
              {mode === 'create' ? ui.create : ui.update}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

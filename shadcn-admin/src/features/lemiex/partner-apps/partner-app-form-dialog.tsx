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
import { createPartnerApp, type PartnerAppRecord, updatePartnerApp } from '@/services/partner-apps/api'
import { useI18n } from '@/context/i18n-provider'

const fallbackMessages = {
  createTitle: 'Create Partner App',
  editTitle: 'Edit Partner App',
  name: 'Name',
  slug: 'Slug',
  authUrl: 'Auth URL',
  proxyStatus: 'Proxy Status',
  status: 'Status',
  cancel: 'Cancel',
  create: 'Create',
  update: 'Update',
  successCreate: 'Partner app created successfully',
  successUpdate: 'Partner app updated successfully',
}

type FormState = {
  name: string
  slug: string
  auth_url: string
  proxy_status: string
  status: string
}

export function PartnerAppFormDialog({
  open,
  mode,
  app,
  onOpenChange,
  onComplete,
}: {
  open: boolean
  mode: 'create' | 'edit'
  app: PartnerAppRecord | null
  onOpenChange: (open: boolean) => void
  onComplete: (message: string) => void
}) {
  const { messages } = useI18n()
  const ui = useMemo(
    () =>
      ((messages as { partnerAppsPage?: { dialog?: typeof fallbackMessages } })
        .partnerAppsPage?.dialog ?? fallbackMessages),
    [messages]
  )
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<FormState>({
    name: '',
    slug: '',
    auth_url: '',
    proxy_status: 'live',
    status: 'Active',
  })

  useEffect(() => {
    if (!open) return

    setForm({
      name: app?.name || '',
      slug: app?.slug || '',
      auth_url: app?.auth_url || '',
      proxy_status: app?.proxy_status || 'live',
      status: app?.status || 'Active',
    })
  }, [app, open])

  function setField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }))
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setSaving(true)
    try {
      if (mode === 'create') {
        await createPartnerApp(form)
        onComplete(ui.successCreate)
      } else if (app) {
        await updatePartnerApp(app.id, form)
        onComplete(ui.successUpdate)
      }
      onOpenChange(false)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='rounded-[6px] sm:max-w-xl'>
        <DialogHeader>
          <DialogTitle>{mode === 'create' ? ui.createTitle : ui.editTitle}</DialogTitle>
        </DialogHeader>

        <form className='space-y-4' onSubmit={handleSubmit}>
          <div className='space-y-2'>
            <Label>{ui.name}</Label>
            <Input
              className='h-10 rounded-[6px]'
              value={form.name}
              onChange={(event) => setField('name', event.target.value)}
              disabled={saving}
            />
          </div>

          <div className='space-y-2'>
            <Label>{ui.slug}</Label>
            <Input
              className='h-10 rounded-[6px]'
              value={form.slug}
              onChange={(event) => setField('slug', event.target.value)}
              disabled={saving || mode === 'edit'}
            />
          </div>

          <div className='space-y-2'>
            <Label>{ui.authUrl}</Label>
            <Input
              className='h-10 rounded-[6px]'
              value={form.auth_url}
              onChange={(event) => setField('auth_url', event.target.value)}
              disabled={saving}
            />
          </div>

          <div className='grid gap-4 md:grid-cols-2'>
            <div className='space-y-2'>
              <Label>{ui.proxyStatus}</Label>
              <Input
                className='h-10 rounded-[6px]'
                value={form.proxy_status}
                onChange={(event) => setField('proxy_status', event.target.value)}
                disabled={saving}
              />
            </div>

            <div className='space-y-2'>
              <Label>{ui.status}</Label>
              <Input
                className='h-10 rounded-[6px]'
                value={form.status}
                onChange={(event) => setField('status', event.target.value)}
                disabled={saving}
              />
            </div>
          </div>

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

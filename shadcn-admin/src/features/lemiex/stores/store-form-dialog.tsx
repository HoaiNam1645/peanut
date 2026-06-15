'use client'

import { useEffect, useMemo, useState } from 'react'
import { RefreshCw } from 'lucide-react'
import { useI18n } from '@/context/i18n-provider'
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
import { createStore, fetchStoreById, fetchStoreUsers, updateStore, type StoreRecord, type StoreUser } from '@/services/stores/api'
import type { AuthUser } from '@/stores/auth-store'

function generateApiKey() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
  const segments = [8, 4, 4, 4]

  return segments
    .map((length) => {
      let segment = ''
      for (let index = 0; index < length; index += 1) {
        segment += chars.charAt(Math.floor(Math.random() * chars.length))
      }
      return segment
    })
    .join('-')
}

const fallbackMessages = {
  createTitle: 'Add New Store',
  createSubtitle: 'Create a new store for a seller',
  editTitle: 'Edit Store',
  editSubtitle: 'Update store information',
  loadingUsers: 'Loading users...',
  loadingStore: 'Loading store data...',
  user: 'User (Seller)',
  selectUser: 'Select a user',
  storeName: 'Store Name',
  enterStoreName: 'Enter store name',
  apiKey: 'API Key',
  status: 'Status',
  cancel: 'Cancel',
  create: 'Create Store',
  creating: 'Creating...',
  update: 'Update Store',
  updating: 'Updating...',
  onlySelf: 'You can only create stores for yourself',
  onlyAdmin: 'Only Admin can change store owner',
  statusHint: 'This will update the user status',
  apiKeyHint: 'Auto-generated API key. Click refresh to generate a new one.',
  apiKeyEditHint: 'Click refresh to generate a new API key',
  refreshKey: 'Generate new API key',
  successCreate: 'Store created successfully!',
  successUpdate: 'Store updated successfully!',
  failedCreate: 'Failed to create store. Please try again.',
  failedUpdate: 'Failed to update store. Please try again.',
  failedLoadUsers: 'Failed to load users. Please try again.',
  failedLoadStore: 'Failed to load store data. Please try again.',
  validation: {
    requiredUser: 'Please select a user',
    requiredName: 'Store name is required',
    requiredApiKey: 'API Key is required',
  },
  active: 'Active',
  unconfirmed: 'Unconfirmed',
  banned: 'Banned',
}

type FieldErrors = Partial<Record<'user_id' | 'name' | 'api_key' | 'status', string>>

export function StoreFormDialog({
  open,
  mode,
  store,
  currentUser,
  onOpenChange,
  onComplete,
}: {
  open: boolean
  mode: 'create' | 'edit'
  store: StoreRecord | null
  currentUser: AuthUser | null
  onOpenChange: (open: boolean) => void
  onComplete: (result: { ok: boolean; message: string }) => void
}) {
  const { messages } = useI18n()
  const ui = useMemo(
    () => messages.storesPage?.dialog ?? fallbackMessages,
    [messages.storesPage]
  )
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')
  const isAdmin = roleName === 'Admin'
  const isSeller = roleName === 'Seller'

  const [users, setUsers] = useState<StoreUser[]>([])
  const [loadingData, setLoadingData] = useState(false)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [form, setForm] = useState({
    user_id: '',
    name: '',
    api_key: generateApiKey(),
    status: 'Active',
  })

  useEffect(() => {
    let active = true

    async function initialize() {
      if (!open) return

      setErrors({})
      setLoadingData(true)

      try {
        if (mode === 'create') {
          if (isSeller && currentUser?.id) {
            const seller = {
              id: currentUser.id,
              username: currentUser.username,
              email: currentUser.email,
            }
            if (!active) return
            setUsers([seller])
            setForm({
              user_id: String(currentUser.id),
              name: '',
              api_key: generateApiKey(),
              status: 'Active',
            })
          } else {
            const nextUsers = await fetchStoreUsers()
            if (!active) return
            setUsers(nextUsers)
            setForm({
              user_id: '',
              name: '',
              api_key: generateApiKey(),
              status: 'Active',
            })
          }
        } else if (store) {
          const [storeDetail, nextUsers] = await Promise.all([
            fetchStoreById(store.id),
            isAdmin
              ? fetchStoreUsers()
              : Promise.resolve(
                  currentUser?.id
                    ? [
                        {
                          id: currentUser.id,
                          username: currentUser.username,
                          email: currentUser.email,
                        },
                      ]
                    : []
                ),
          ])

          if (!active) return
          setUsers(nextUsers)
          setForm({
            user_id: String(store.user?.id || storeDetail.user?.id || ''),
            name: store.name || storeDetail.name || '',
            api_key: storeDetail.api_key || '',
            status: store.status || storeDetail.status || 'Active',
          })
        }
      } catch {
        if (!active) return
      } finally {
        if (active) {
          setLoadingData(false)
        }
      }
    }

    void initialize()

    return () => {
      active = false
    }
  }, [open, mode, store, isAdmin, isSeller, currentUser])

  function setField<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((prev) => ({ ...prev, [key]: value }))
    setErrors((prev) => ({ ...prev, [key]: '' }))
  }

  function validate() {
    const nextErrors: FieldErrors = {}

    if (!form.user_id) nextErrors.user_id = ui.validation.requiredUser
    if (!form.name.trim()) nextErrors.name = ui.validation.requiredName
    if (!form.api_key.trim()) nextErrors.api_key = ui.validation.requiredApiKey

    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (!validate()) return

    setSaving(true)
    try {
      const payload = {
        user_id: form.user_id,
        name: form.name.trim(),
        api_key: form.api_key.trim(),
        ...(mode === 'edit' ? { status: form.status } : {}),
      }

      if (mode === 'create') {
        await createStore(payload)
        onComplete({ ok: true, message: ui.successCreate })
      } else if (store) {
        await updateStore(store.id, payload)
        onComplete({ ok: true, message: ui.successUpdate })
      }

      onOpenChange(false)
    } catch (error) {
      const err = error as Error & { errors?: Record<string, string[]> }
      if (err.errors) {
        setErrors({
          user_id: err.errors.user_id?.[0],
          name: err.errors.name?.[0],
          api_key: err.errors.api_key?.[0],
          status: err.errors.status?.[0],
        })
      }
      onComplete({
        ok: false,
        message: mode === 'create' ? err.message || ui.failedCreate : err.message || ui.failedUpdate,
      })
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

        {loadingData ? (
          <div className='py-10 text-center text-sm text-muted-foreground'>
            {mode === 'create' ? ui.loadingUsers : ui.loadingStore}
          </div>
        ) : (
          <form className='space-y-5' onSubmit={handleSubmit}>
            <div className='space-y-2'>
              <Label>{ui.user}</Label>
              <Select
                value={form.user_id}
                onValueChange={(value) => setField('user_id', value)}
                disabled={saving || (mode === 'create' ? isSeller : !isAdmin)}
              >
                <SelectTrigger className='h-11 w-full rounded-[6px]'>
                  <SelectValue placeholder={ui.selectUser} />
                </SelectTrigger>
                <SelectContent>
                  {users.map((user) => (
                    <SelectItem key={String(user.id)} value={String(user.id)}>
                      {user.username || 'N/A'} ({user.email || 'N/A'})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.user_id ? <p className='text-xs text-destructive'>{errors.user_id}</p> : null}
            </div>

            <div className='space-y-2'>
              <Label>{ui.storeName}</Label>
              <Input
                className='h-11 w-full rounded-[6px]'
                value={form.name}
                placeholder={ui.enterStoreName}
                onChange={(event) => setField('name', event.target.value)}
                disabled={saving}
              />
              {errors.name ? <p className='text-xs text-destructive'>{errors.name}</p> : null}
            </div>

            {mode === 'edit' && isAdmin ? (
              <div className='space-y-2'>
                <Label>{ui.status}</Label>
                <Select
                  value={form.status}
                  onValueChange={(value) => setField('status', value)}
                  disabled={saving}
                >
                  <SelectTrigger className='h-11 w-full rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='Active'>{ui.active}</SelectItem>
                    <SelectItem value='Unconfirmed'>{ui.unconfirmed}</SelectItem>
                    <SelectItem value='Banned'>{ui.banned}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            ) : null}

            <div className='space-y-2'>
              <Label>{ui.apiKey}</Label>
              <div className='flex gap-2'>
                <Input
                  className='h-11 flex-1 rounded-[6px]'
                  value={form.api_key}
                  readOnly
                  disabled={saving}
                />
                <Button
                  type='button'
                  variant='outline'
                  className='h-11 w-11 shrink-0 rounded-[6px] p-0'
                  disabled={saving}
                  onClick={() => setField('api_key', generateApiKey())}
                  aria-label={ui.refreshKey}
                  title={ui.refreshKey}
                >
                  <RefreshCw className='size-4' />
                </Button>
              </div>
              {errors.api_key ? <p className='text-xs text-destructive'>{errors.api_key}</p> : null}
            </div>

            <DialogFooter>
              <Button
                type='button'
                variant='outline'
                className='h-11 rounded-[6px]'
                onClick={() => onOpenChange(false)}
                disabled={saving}
              >
                {ui.cancel}
              </Button>
              <Button type='submit' className='h-11 rounded-[6px]' disabled={saving}>
                {saving
                  ? mode === 'create'
                    ? ui.creating
                    : ui.updating
                  : mode === 'create'
                    ? ui.create
                    : ui.update}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}

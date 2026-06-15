'use client'

import { useEffect, useMemo, useState } from 'react'
import Link from 'next/link'
import { ArrowLeft } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  createLemiexUser,
  fetchLemiexUserById,
  fetchLemiexUserRoles,
  updateLemiexUser,
  type LemiexUserRole,
  type UserMutationPayload,
} from '@/services/lemiex-users/api'

const TIERS = [
  { id: 0, key: 'silver' },
  { id: 1, key: 'gold' },
  { id: 2, key: 'platinum' },
  { id: 3, key: 'diamond' },
] as const

const fallbackMessages = {
  backToList: 'Back to Users',
  backToDetail: 'Back to Details',
  createTitle: 'Add New User',
  editTitle: 'Edit User',
  loading: 'Loading...',
  notFound: 'User not found',
  loadFailed: 'Failed to load user information',
  createSuccess: 'User created successfully!',
  updateSuccess: 'User updated successfully!',
  error: 'An error occurred',
  form: {
    accountInfo: 'Account Information',
    userDetails: 'User Details',
    integrationSettings: 'Integration Settings',
    debitSettings: 'Debit Settings',
    additionalOptions: 'Additional Options',
    email: 'Email',
    username: 'Username',
    password: 'Password',
    confirmPassword: 'Confirm Password',
    newPassword: 'New Password',
    confirmNewPassword: 'Confirm New Password',
    leaveBlank: 'Leave blank to keep current password',
    role: 'Role',
    status: 'Status',
    firstName: 'First Name',
    lastName: 'Last Name',
    phone: 'Phone',
    dob: 'Date of Birth',
    address: 'Address',
    webhookUrl: 'Webhook URL',
    telegramId: 'Telegram ID',
    apiKey: 'API Key',
    maxDebit: 'Max Debit',
    maxDateDebit: 'Max Date Debit',
    minDateDebit: 'Min Date Debit',
    supportUs: 'Support Us',
    yes: 'Yes',
    no: 'No',
    optional: '(optional)',
    loadingRoles: 'Loading roles...',
    noRoles: 'No roles available',
    submit: 'Create User',
    update: 'Update User',
    cancel: 'Cancel',
  },
  status: {
    active: 'Active',
    unconfirmed: 'Unconfirmed',
    banned: 'Banned',
  },
  columns: {
    tier: 'Tier',
  },
  tiers: {
    silver: 'Silver',
    gold: 'Gold',
    platinum: 'Platinum',
    diamond: 'Diamond',
  },
}

type FormMode = 'create' | 'edit'

const initialState: UserMutationPayload = {
  email: '',
  username: '',
  password: '',
  password_confirmation: '',
  role_id: '',
  status: 'Active',
  first_name: '',
  last_name: '',
  phone: '',
  address: '',
  birthday: '',
  webhook_url: '',
  telegram_id: '',
  api_key: '',
  max_debit: '0',
  max_date_debit: '0',
  min_date_debit: '0',
  is_support_us: false,
  tier_id: 0,
}

export function LemiexUserFormPage({
  mode,
  id,
}: {
  mode: FormMode
  id?: string
}) {
  const { messages } = useI18n()
  const m = messages.usersPage ?? fallbackMessages
  const [formData, setFormData] = useState<UserMutationPayload>(initialState)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [roles, setRoles] = useState<LemiexUserRole[]>([])
  const [loading, setLoading] = useState(mode === 'edit')
  const [loadingRoles, setLoadingRoles] = useState(true)
  const [saving, setSaving] = useState(false)

  const isEdit = mode === 'edit'

  useEffect(() => {
    let active = true
    async function loadRoles() {
      try {
        const response = await fetchLemiexUserRoles()
        if (!active) return
        setRoles(response)
        if (!isEdit && response.length > 0) {
          setFormData((prev) => ({ ...prev, role_id: String(response[0].id) }))
        }
      } catch {
        if (!active) return
      } finally {
        if (active) {
          setLoadingRoles(false)
        }
      }
    }
    void loadRoles()
    return () => {
      active = false
    }
  }, [isEdit])

  useEffect(() => {
    if (!isEdit || !id) return
    const userId = id
    let active = true
    async function loadUser() {
      try {
        const user = await fetchLemiexUserById(userId)
        if (!active) return
        setFormData({
          email: user.email || '',
          username: user.username || '',
          password: '',
          password_confirmation: '',
          role_id: String(user.role_id || '2'),
          status: user.status || 'Active',
          first_name: user.profile?.first_name || '',
          last_name: user.profile?.last_name || '',
          phone: user.profile?.phone || '',
          address: user.profile?.address || '',
          birthday: user.profile?.birthday || '',
          webhook_url: user.profile?.webhook_url || '',
          telegram_id: user.profile?.telegram_id || '',
          api_key: user.api_key || '',
          max_debit: String(user.profile?.max_debit || '0'),
          max_date_debit: String(user.profile?.max_date_debit || '0'),
          min_date_debit: String(user.profile?.min_date_debit || '0'),
          is_support_us: Boolean(user.profile?.is_support_us),
          tier_id: Number(user.profile?.private_seller ?? 0),
        })
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.loadFailed)
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }
    void loadUser()
    return () => {
      active = false
    }
  }, [id, isEdit, m.loadFailed])

  const isSellerRole = useMemo(() => {
    const selectedRole = roles.find((role) => String(role.id) === String(formData.role_id))
    return selectedRole?.name === 'Seller'
  }, [formData.role_id, roles])

  function updateField(name: keyof UserMutationPayload, value: string | boolean | number) {
    setFormData((prev) => {
      const next = { ...prev, [name]: value }
      if (name === 'role_id') {
        const selectedRole = roles.find((role) => String(role.id) === String(value))
        if (selectedRole?.name !== 'Seller') {
          next.tier_id = 0
        }
      }
      return next
    })
    if (errors[String(name)]) {
      setErrors((prev) => {
        const next = { ...prev }
        delete next[String(name)]
        return next
      })
    }
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    try {
      setSaving(true)
      const payload = { ...formData }
      if (isEdit && !payload.password) {
        delete payload.password
        delete payload.password_confirmation
      }
      const response = isEdit && id
        ? await updateLemiexUser(id, payload)
        : await createLemiexUser(payload)

      if (response.status || response.success) {
        toast.success(isEdit ? m.updateSuccess : m.createSuccess)
        window.location.href = isEdit && id ? `/lemiex/users/${id}` : '/lemiex/users'
      }
    } catch (error) {
      const err = error as Error & { errors?: Record<string, string[]> }
      if (err.errors) {
        setErrors(err.errors)
      } else {
        toast.error(err.message || m.error)
      }
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <>
        <Header fixed>
          <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
        </Header>
        <Main fluid className='flex flex-1 items-center justify-center px-4 py-6'>
          <div className='text-sm text-muted-foreground'>{m.loading}</div>
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <Search />
      </Header>

      <Main fluid className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
        <div className='space-y-6'>
          <div className='space-y-3'>
            <Button asChild variant='outline' className='h-10 rounded-[6px]'>
              <Link href={isEdit && id ? `/lemiex/users/${id}` : '/lemiex/users'}>
                <ArrowLeft className='size-4' />
                {isEdit ? m.backToDetail : m.backToList}
              </Link>
            </Button>
            <h1 className='text-3xl font-semibold tracking-tight'>
              {isEdit ? m.editTitle : m.createTitle}
            </h1>
          </div>

          <form className='space-y-6' onSubmit={handleSubmit}>
            <Card className='rounded-[6px]'>
              <CardHeader>
                <CardTitle>{m.form.accountInfo}</CardTitle>
              </CardHeader>
              <CardContent className='grid gap-4 lg:grid-cols-2'>
                <div className='space-y-2'>
                  <Label htmlFor='email'>{m.form.email}</Label>
                  <Input id='email' value={formData.email} onChange={(e) => updateField('email', e.target.value)} className='h-10 rounded-[6px]' required />
                  {errors.email ? <p className='text-xs text-rose-600'>{errors.email[0]}</p> : null}
                </div>
                <div className='space-y-2'>
                  <Label htmlFor='username'>{m.form.username}</Label>
                  <Input id='username' value={formData.username || ''} onChange={(e) => updateField('username', e.target.value)} className='h-10 rounded-[6px]' placeholder={m.form.optional} />
                  {errors.username ? <p className='text-xs text-rose-600'>{errors.username[0]}</p> : null}
                </div>
                <div className='space-y-2'>
                  <Label htmlFor='password'>{isEdit ? m.form.newPassword : m.form.password}</Label>
                  <Input id='password' type='password' value={formData.password || ''} onChange={(e) => updateField('password', e.target.value)} className='h-10 rounded-[6px]' placeholder={isEdit ? m.form.leaveBlank : ''} required={!isEdit} />
                  {errors.password ? <p className='text-xs text-rose-600'>{errors.password[0]}</p> : null}
                </div>
                <div className='space-y-2'>
                  <Label htmlFor='password_confirmation'>{isEdit ? m.form.confirmNewPassword : m.form.confirmPassword}</Label>
                  <Input id='password_confirmation' type='password' value={formData.password_confirmation || ''} onChange={(e) => updateField('password_confirmation', e.target.value)} className='h-10 rounded-[6px]' required={!isEdit} />
                  {errors.password_confirmation ? <p className='text-xs text-rose-600'>{errors.password_confirmation[0]}</p> : null}
                </div>
              </CardContent>
            </Card>

            <Card className='rounded-[6px]'>
              <CardHeader><CardTitle>{m.form.userDetails}</CardTitle></CardHeader>
              <CardContent className='grid gap-4 lg:grid-cols-2'>
                <div className='space-y-2'>
                  <Label>{m.form.role}</Label>
                  <Select value={formData.role_id} onValueChange={(value) => updateField('role_id', value)} disabled={loadingRoles}>
                    <SelectTrigger className='h-10 w-full rounded-[6px]'><SelectValue placeholder={loadingRoles ? m.form.loadingRoles : m.form.noRoles} /></SelectTrigger>
                    <SelectContent>
                      {roles.map((role) => (
                        <SelectItem key={String(role.id)} value={String(role.id)}>
                          {role.display_name || role.name || String(role.id)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.role_id ? <p className='text-xs text-rose-600'>{errors.role_id[0]}</p> : null}
                </div>
                <div className='space-y-2'>
                  <Label>{m.form.status}</Label>
                  <Select value={formData.status} onValueChange={(value) => updateField('status', value)}>
                    <SelectTrigger className='h-10 w-full rounded-[6px]'><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value='Active'>{m.status.active}</SelectItem>
                      <SelectItem value='Unconfirmed'>{m.status.unconfirmed}</SelectItem>
                      <SelectItem value='Banned'>{m.status.banned}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className='space-y-2'><Label>{m.form.firstName}</Label><Input value={formData.first_name || ''} onChange={(e) => updateField('first_name', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2'><Label>{m.form.lastName}</Label><Input value={formData.last_name || ''} onChange={(e) => updateField('last_name', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2'><Label>{m.form.phone}</Label><Input value={formData.phone || ''} onChange={(e) => updateField('phone', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2'><Label>{m.form.dob}</Label><Input type='date' value={formData.birthday || ''} onChange={(e) => updateField('birthday', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2 lg:col-span-2'><Label>{m.form.address}</Label><Input value={formData.address || ''} onChange={(e) => updateField('address', e.target.value)} className='h-10 rounded-[6px]' /></div>
              </CardContent>
            </Card>

            <Card className='rounded-[6px]'>
              <CardHeader><CardTitle>{m.form.integrationSettings}</CardTitle></CardHeader>
              <CardContent className='grid gap-4 lg:grid-cols-2'>
                <div className='space-y-2'><Label>{m.form.webhookUrl}</Label><Input value={formData.webhook_url || ''} onChange={(e) => updateField('webhook_url', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2'><Label>{m.form.telegramId}</Label><Input value={formData.telegram_id || ''} onChange={(e) => updateField('telegram_id', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2 lg:col-span-2'><Label>{m.form.apiKey}</Label><Input value={formData.api_key || ''} onChange={(e) => updateField('api_key', e.target.value)} className='h-10 rounded-[6px]' /></div>
              </CardContent>
            </Card>

            <Card className='rounded-[6px]'>
              <CardHeader><CardTitle>{m.form.debitSettings}</CardTitle></CardHeader>
              <CardContent className='grid gap-4 md:grid-cols-3'>
                <div className='space-y-2'><Label>{m.form.maxDebit}</Label><Input type='number' min='0' step='0.01' value={formData.max_debit || '0'} onChange={(e) => updateField('max_debit', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2'><Label>{m.form.maxDateDebit}</Label><Input type='number' min='0' value={formData.max_date_debit || '0'} onChange={(e) => updateField('max_date_debit', e.target.value)} className='h-10 rounded-[6px]' /></div>
                <div className='space-y-2'><Label>{m.form.minDateDebit}</Label><Input type='number' min='0' value={formData.min_date_debit || '0'} onChange={(e) => updateField('min_date_debit', e.target.value)} className='h-10 rounded-[6px]' /></div>
              </CardContent>
            </Card>

            <Card className='rounded-[6px]'>
              <CardHeader><CardTitle>{m.form.additionalOptions}</CardTitle></CardHeader>
              <CardContent className='grid gap-4 lg:grid-cols-2'>
                <div className='space-y-2'>
                  <Label>{m.form.supportUs}</Label>
                  <Select
                    value={formData.is_support_us ? '1' : '0'}
                    onValueChange={(value) => updateField('is_support_us', value === '1')}
                  >
                    <SelectTrigger className='h-10 w-full rounded-[6px]'><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value='0'>{m.form.no}</SelectItem>
                      <SelectItem value='1'>{m.form.yes}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {isSellerRole ? (
                  <div className='space-y-2'>
                    <Label>{m.columns.tier}</Label>
                    <Select
                      value={String(formData.tier_id || 0)}
                      onValueChange={(value) => updateField('tier_id', Number(value))}
                    >
                      <SelectTrigger className='h-10 w-full rounded-[6px]'><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {TIERS.map((tier) => (
                          <SelectItem key={tier.id} value={String(tier.id)}>
                            {m.tiers[tier.key]}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                ) : null}
              </CardContent>
            </Card>

            <div className='flex justify-end gap-2'>
              <Button asChild variant='outline' className='rounded-[6px]'>
                <Link href={isEdit && id ? `/lemiex/users/${id}` : '/lemiex/users'}>
                  {m.form.cancel}
                </Link>
              </Button>
              <Button type='submit' className='rounded-[6px]' disabled={saving}>
                {saving ? (isEdit ? m.form.update : m.form.submit) : isEdit ? m.form.update : m.form.submit}
              </Button>
            </div>
          </form>
        </div>
      </Main>
    </>
  )
}

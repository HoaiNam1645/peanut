'use client'

import { useEffect, useMemo, useState } from 'react'
import {
  BadgeCheck,
  CalendarClock,
  Copy,
  CreditCard,
  Mail,
  MapPin,
  Phone,
  RefreshCcw,
  Send,
  UserRound,
  Wallet,
} from 'lucide-react'
import { fetchCurrentUser } from '@/services/auth/api'
import { useAuthStore, type AuthUser } from '@/stores/auth-store'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'

function formatCurrency(value: unknown) {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0)
}

function formatDate(value: unknown) {
  if (!value || typeof value !== 'string') return 'N/A'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'N/A'

  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatDateInput(value: unknown) {
  if (!value || typeof value !== 'string') return ''
  return value.includes('T') ? value.split('T')[0] : value
}

function getProfileName(user: AuthUser | null) {
  const firstName = typeof user?.profile === 'object' ? String((user.profile as Record<string, unknown>).first_name || '') : ''
  const lastName = typeof user?.profile === 'object' ? String((user.profile as Record<string, unknown>).last_name || '') : ''
  const fullName = `${firstName} ${lastName}`.trim()

  return (
    fullName ||
    user?.full_name ||
    user?.name ||
    user?.username ||
    (user?.email ? user.email.split('@')[0] : 'User')
  )
}

function getAvatarUrl(user: AuthUser | null) {
  const profile =
    user && typeof user.profile === 'object'
      ? (user.profile as Record<string, unknown>)
      : null
  const avatar = typeof profile?.avatar === 'string' ? profile.avatar : null

  if (!avatar) return null
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar

  const backendOrigin = (
    process.env.NEXT_PUBLIC_API_BASE_URL?.startsWith('http')
      ? process.env.NEXT_PUBLIC_API_BASE_URL
      : process.env.BACKEND_API_ORIGIN
  ) || 'http://127.0.0.1:8000/api'

  const baseOrigin = backendOrigin.replace(/\/api\/?$/, '')

  if (avatar.startsWith('/storage')) return `${baseOrigin}${avatar}`
  if (avatar.startsWith('storage/')) return `${baseOrigin}/${avatar}`

  return `${baseOrigin}/storage/${avatar}`
}

function InfoField({
  label,
  value,
  icon: Icon,
}: {
  label: string
  value: string
  icon: React.ElementType
}) {
  return (
    <div className='rounded-xl border bg-card/60 p-4'>
      <div className='mb-2 flex items-center gap-2 text-sm text-muted-foreground'>
        <Icon className='size-4' />
        <span>{label}</span>
      </div>
      <div className='text-sm font-medium break-words'>{value || 'N/A'}</div>
    </div>
  )
}

export function LemiexProfile() {
  const storedUser = useAuthStore((state) => state.auth.user)
  const setUser = useAuthStore((state) => state.auth.setUser)
  const [user, setLocalUser] = useState<AuthUser | null>(storedUser)
  const [loading, setLoading] = useState(!storedUser)
  const [refreshing, setRefreshing] = useState(false)

  const profile =
    user && typeof user.profile === 'object'
      ? (user.profile as Record<string, unknown>)
      : null

  useEffect(() => {
    setLocalUser(storedUser)
  }, [storedUser])

  useEffect(() => {
    let cancelled = false

    async function loadProfile() {
      setLoading(true)
      const result = await fetchCurrentUser()

      if (cancelled) return

      if (result.success) {
        setLocalUser(result.user)
        setUser(result.user)
      }

      setLoading(false)
    }

    loadProfile()

    return () => {
      cancelled = true
    }
  }, [setUser])

  async function handleRefresh() {
    setRefreshing(true)
    const result = await fetchCurrentUser()

    if (result.success) {
      setLocalUser(result.user)
      setUser(result.user)
    }

    setRefreshing(false)
  }

  const profileName = useMemo(() => getProfileName(user), [user])
  const avatarUrl = useMemo(() => getAvatarUrl(user), [user])
  const roleName = useMemo(() => {
    if (!user) return 'N/A'
    if (typeof user.role === 'object' && user.role) {
      return String(user.role.display_name || user.role.name || 'N/A')
    }
    return String(user.role || user.role_name || 'N/A')
  }, [user])
  const apiKey = typeof user?.api_key === 'string' ? user.api_key : ''

  async function handleCopyApiKey() {
    if (!apiKey) return

    try {
      await navigator.clipboard.writeText(apiKey)
    } catch {
      // Ignore clipboard failures for now.
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

        <Main className='flex flex-1 items-center justify-center'>
          <div className='text-sm text-muted-foreground'>Đang tải hồ sơ...</div>
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <Search />
      </Header>

      <Main className='flex flex-1 flex-col gap-6 sm:gap-8'>
        <div className='flex flex-wrap items-start justify-between gap-4'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>Profile</h1>
          </div>
          <Button variant='outline' onClick={() => void handleRefresh()} disabled={refreshing}>
            <RefreshCcw className={refreshing ? 'animate-spin' : ''} />
            Làm mới
          </Button>
        </div>

        <div className='grid gap-6 xl:grid-cols-[1.15fr_0.85fr]'>
          <Card>
            <CardHeader className='space-y-5'>
              <div className='flex flex-col gap-4 sm:flex-row sm:items-center'>
                <div className='flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl border bg-muted'>
                  {avatarUrl ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={avatarUrl} alt={profileName} className='h-full w-full object-cover' />
                  ) : (
                    <UserRound className='size-10 text-muted-foreground' />
                  )}
                </div>
                <div className='space-y-3'>
                  <div>
                    <CardTitle className='text-2xl'>{profileName}</CardTitle>
                    <CardDescription className='mt-1'>
                      {user?.email || 'N/A'}
                    </CardDescription>
                  </div>
                  <div className='inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium'>
                    <BadgeCheck className='me-2 size-4' />
                    {roleName}
                  </div>
                </div>
              </div>
            </CardHeader>
            <CardContent className='space-y-6'>
              <div className='grid gap-4 sm:grid-cols-2'>
                <InfoField label='Username' value={String(user?.username || 'N/A')} icon={UserRound} />
                <InfoField label='Member since' value={formatDate(user?.created_at)} icon={CalendarClock} />
                <InfoField label='Wallet balance' value={formatCurrency(profile?.wallet_balance)} icon={Wallet} />
                <InfoField label='Telegram ID' value={String(profile?.telegram_id || 'N/A')} icon={Send} />
              </div>

              <Separator />

              <div className='grid gap-4 sm:grid-cols-2'>
                <div className='space-y-2'>
                  <Label>First name</Label>
                  <Input value={String(profile?.first_name || '')} readOnly />
                </div>
                <div className='space-y-2'>
                  <Label>Last name</Label>
                  <Input value={String(profile?.last_name || '')} readOnly />
                </div>
                <div className='space-y-2'>
                  <Label>Email</Label>
                  <Input value={String(user?.email || '')} readOnly />
                </div>
                <div className='space-y-2'>
                  <Label>Phone</Label>
                  <Input value={String(profile?.phone || '')} readOnly />
                </div>
                <div className='space-y-2'>
                  <Label>Birthday</Label>
                  <Input value={formatDateInput(profile?.birthday)} readOnly />
                </div>
                <div className='space-y-2'>
                  <Label>Telegram ID</Label>
                  <Input value={String(profile?.telegram_id || '')} readOnly />
                </div>
                <div className='space-y-2 sm:col-span-2'>
                  <Label>Address</Label>
                  <Textarea
                    value={String(profile?.address || '')}
                    readOnly
                    className='min-h-28 resize-none'
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <div className='grid gap-6'>
            <Card>
              <CardHeader>
                <CardTitle>Tín dụng seller</CardTitle>
                <CardDescription>
                  Các mốc công nợ hiện tại từ hồ sơ người dùng.
                </CardDescription>
              </CardHeader>
              <CardContent className='grid gap-4'>
                <InfoField label='Max debit' value={formatCurrency(profile?.max_debit)} icon={CreditCard} />
                <InfoField
                  label='Max date debit'
                  value={`${profile?.max_date_debit || 0} ngày`}
                  icon={CalendarClock}
                />
                <InfoField
                  label='Min date debit'
                  value={`${profile?.min_date_debit || 0} ngày`}
                  icon={CalendarClock}
                />
                <InfoField
                  label='Debit status'
                  value={profile?.debit_status ? 'Enabled' : 'Disabled'}
                  icon={BadgeCheck}
                />
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Liên hệ & API</CardTitle>
                <CardDescription>
                  Thông tin cơ bản đồng bộ từ backend.
                </CardDescription>
              </CardHeader>
              <CardContent className='grid gap-4'>
                <InfoField label='Email' value={String(user?.email || 'N/A')} icon={Mail} />
                <InfoField label='Phone' value={String(profile?.phone || 'N/A')} icon={Phone} />
                <InfoField label='Address' value={String(profile?.address || 'N/A')} icon={MapPin} />
                <div className='rounded-xl border bg-card/60 p-4'>
                  <div className='mb-2 flex items-center gap-2 text-sm text-muted-foreground'>
                    <CreditCard className='size-4' />
                    <span>API Key</span>
                  </div>
                  <div className='flex items-center gap-2'>
                    <code className='min-w-0 flex-1 overflow-hidden rounded-md bg-muted px-3 py-2 text-sm whitespace-nowrap text-ellipsis'>
                      {apiKey || 'Not available'}
                    </code>
                    <Button
                      type='button'
                      variant='outline'
                      size='icon'
                      onClick={() => void handleCopyApiKey()}
                      disabled={!apiKey}
                      aria-label='Copy API key'
                    >
                      <Copy className='size-4' />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </Main>
    </>
  )
}

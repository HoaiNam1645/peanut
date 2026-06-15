'use client'
/* eslint-disable @next/next/no-img-element */

import Link from 'next/link'
import { useEffect, useState } from 'react'
import { ArrowLeft, Pencil } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { fetchLemiexUserById, type LemiexUserRecord } from '@/services/lemiex-users/api'

const fallbackMessages = {
  backToList: 'Back to Users',
  viewTitle: 'User Details',
  editTitle: 'Edit User',
  accountInfo: 'Account Information',
  userDetails: 'User Details',
  integrationSettings: 'Integration Settings',
  debitSettings: 'Debit Settings',
  additionalOptions: 'Additional Options',
  username: 'Username',
  email: 'Email',
  role: 'Role',
  statusLabel: 'Status',
  registrationDate: 'Registration Date',
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
  balanceLabel: 'Balance',
  supportUs: 'Support Us',
  privateSeller: 'Private Seller',
  days: 'days',
  yes: 'Yes',
  no: 'No',
  status: {
    active: 'Active',
    unconfirmed: 'Unconfirmed',
    banned: 'Banned',
  },
  loadFailed: 'Failed to load user information',
  loading: 'Loading...',
  na: 'N/A',
}

function formatDate(dateString?: string | null) {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function statusClass(status?: string | null) {
  switch (status) {
    case 'Active':
      return 'border-emerald-200 bg-emerald-500/10 text-emerald-700'
    case 'Banned':
      return 'border-rose-200 bg-rose-500/10 text-rose-700'
    default:
      return 'border-amber-200 bg-amber-500/10 text-amber-700'
  }
}

function InfoSection({
  title,
  rows,
}: {
  title: string
  rows: Array<{ label: string; value: React.ReactNode }>
}) {
  return (
    <Card className='rounded-[6px]'>
      <CardHeader><CardTitle>{title}</CardTitle></CardHeader>
      <CardContent className='space-y-4'>
        {rows.map((row) => (
          <div key={row.label} className='flex items-start justify-between gap-4 border-b pb-3 last:border-b-0 last:pb-0'>
            <span className='text-sm text-muted-foreground'>{row.label}</span>
            <div className='text-right text-sm font-medium'>{row.value}</div>
          </div>
        ))}
      </CardContent>
    </Card>
  )
}

export function LemiexUserDetailPage({ id }: { id: string }) {
  const { messages } = useI18n()
  const m = messages.usersPage ?? fallbackMessages
  const [user, setUser] = useState<LemiexUserRecord | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    async function loadUser() {
      try {
        const response = await fetchLemiexUserById(id)
        if (!active) return
        setUser(response)
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
  }, [id, m.loadFailed])

  if (loading) {
    return (
      <>
        <Header fixed><Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div></Header>
        <Main fluid className='flex flex-1 items-center justify-center px-4 py-6'><div className='text-sm text-muted-foreground'>{m.loading}</div></Main>
      </>
    )
  }

  if (!user) return null

  return (
    <>
      <Header fixed><Search /></Header>
      <Main fluid className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
        <div className='space-y-6'>
          <div className='flex flex-col gap-3'>
            <div className='flex flex-wrap items-center justify-between gap-3'>
              <Button asChild variant='outline' className='h-10 rounded-[6px]'>
                <Link href='/lemiex/users'>
                  <ArrowLeft className='size-4' />
                  {m.backToList}
                </Link>
              </Button>
              <Button asChild className='h-10 rounded-[6px]'>
                <Link href={`/lemiex/users/${id}/edit`}>
                  <Pencil className='size-4' />
                  {m.editTitle}
                </Link>
              </Button>
            </div>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.viewTitle}</h1>
          </div>

          <Card className='rounded-[6px]'>
            <CardContent className='flex flex-col gap-4 p-6 md:flex-row md:items-center'>
              {user.profile?.avatar ? (
                <img src={user.profile.avatar} alt={user.username || 'avatar'} className='size-24 rounded-full object-cover' />
              ) : (
                <div className='flex size-24 items-center justify-center rounded-full bg-muted text-3xl font-semibold'>
                  {String(user.username || '?').charAt(0).toUpperCase()}
                </div>
              )}
              <div className='space-y-3'>
                <div>
                  <h2 className='text-2xl font-semibold'>
                    {`${user.profile?.first_name || ''} ${user.profile?.last_name || ''}`.trim() || user.username}
                  </h2>
                  <p className='text-sm text-muted-foreground'>{user.email}</p>
                </div>
                <div className='flex flex-wrap gap-2'>
                  <Badge variant='outline' className={statusClass(user.status)}>
                    {user.status === 'Active' ? m.status.active : user.status === 'Banned' ? m.status.banned : m.status.unconfirmed}
                  </Badge>
                  <Badge variant='outline' className='border-slate-200 bg-slate-500/10 text-slate-700'>
                    {user.role?.display_name || user.role?.name || m.na}
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className='grid gap-6 xl:grid-cols-2'>
            <InfoSection title={m.accountInfo} rows={[
              { label: m.username, value: user.username || m.na },
              { label: m.email, value: user.email || m.na },
              { label: m.role, value: user.role?.display_name || user.role?.name || m.na },
              { label: m.statusLabel, value: user.status || m.na },
              { label: m.registrationDate, value: formatDate(user.created_at) },
            ]} />
            <InfoSection title={m.userDetails} rows={[
              { label: m.firstName, value: user.profile?.first_name || m.na },
              { label: m.lastName, value: user.profile?.last_name || m.na },
              { label: m.phone, value: user.profile?.phone || m.na },
              { label: m.dob, value: formatDate(user.profile?.birthday) },
              { label: m.address, value: user.profile?.address || m.na },
            ]} />
            <InfoSection title={m.integrationSettings} rows={[
              { label: m.webhookUrl, value: user.profile?.webhook_url || m.na },
              { label: m.telegramId, value: user.profile?.telegram_id || m.na },
              { label: m.apiKey, value: user.api_key || m.na },
            ]} />
            <InfoSection title={m.debitSettings} rows={[
              { label: m.maxDebit, value: `$${user.profile?.max_debit || 0}` },
              { label: m.maxDateDebit, value: `${user.profile?.max_date_debit || 0} ${m.days}` },
              { label: m.minDateDebit, value: `${user.profile?.min_date_debit || 0} ${m.days}` },
              { label: m.balanceLabel, value: `$${user.profile?.wallet_balance || 0}` },
            ]} />
            <InfoSection title={m.additionalOptions} rows={[
              { label: m.supportUs, value: user.profile?.is_support_us ? m.yes : m.no },
              { label: m.privateSeller, value: user.profile?.private_seller ? m.yes : m.no },
            ]} />
          </div>
        </div>
      </Main>
    </>
  )
}

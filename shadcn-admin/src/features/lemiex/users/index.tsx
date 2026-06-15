'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import type { ColumnDef } from '@tanstack/react-table'
import { Eye, Pencil, Plus, Trash2, Wallet } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import {
  addFundToLemiexUser,
  deleteLemiexUser,
  fetchLemiexUserRoles,
  fetchLemiexUsers,
  type LemiexUserRecord,
  type LemiexUsersFilters,
  type LemiexUsersPagination,
} from '@/services/lemiex-users/api'

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'User Management',
  addFund: 'Add Fund',
  addNew: 'Add New User',
  filters: {
    search: 'Search by name, email, username...',
    allStatus: 'All Status',
    allRoles: 'All Roles',
    allTiers: 'All Tiers',
  },
  status: {
    active: 'Active',
    unconfirmed: 'Unconfirmed',
    banned: 'Banned',
  },
  columns: {
    username: 'Username',
    fullName: 'Full Name',
    role: 'Role',
    email: 'Email',
    balance: 'Balance',
    tier: 'Tier',
    registrationDate: 'Registration Date',
    status: 'Status',
    actions: 'Actions',
  },
  viewTitle: 'View Details',
  editTitle: 'Edit',
  deleteTitle: 'Delete',
  notFound: 'No users found',
  loadFailed: 'Failed to load user information',
  deleteConfirm: 'Are you sure you want to delete this user?',
  deleteSuccess: 'User deleted successfully',
  deleteFailed: 'Failed to delete user',
  error: 'An error occurred',
  addFundModal: {
    title: 'Add Fund to Seller',
    selectSeller: 'Select Seller',
    loadingSellers: 'Loading sellers...',
    selectPlaceholder: '-- Select a seller --',
    currentBalance: 'Current Balance',
    type: 'Type',
    deposit: 'Deposit (+)',
    withdraw: 'Withdraw (-)',
    amount: 'Amount',
    enterAmount: 'Enter amount',
    note: 'Note',
    notePlaceholder: 'e.g. Monthly deposit',
    newBalance: 'New Balance',
    cancel: 'Cancel',
    submit: 'Confirm',
    selectSellerRequired: 'Please select a seller',
    invalidAmount: 'Please enter a valid amount',
    fundFailed: 'Failed to add fund',
    fundSuccess:
      'Successfully {action} ${amount} {direction} {user}. New balance: ${balance}',
  },
  tiers: {
    silver: 'Silver',
    gold: 'Gold',
    platinum: 'Platinum',
    diamond: 'Diamond',
  },
  roles: {
    admin: 'Admin',
    seller: 'Seller',
    user: 'User',
    supplier: 'Supplier',
    staff: 'Staff',
    support: 'Support',
    designer: 'Designer',
    finance: 'Finance',
  },
  na: 'N/A',
}

const TIERS = [
  { id: 0, key: 'silver' },
  { id: 1, key: 'gold' },
  { id: 2, key: 'platinum' },
  { id: 3, key: 'diamond' },
] as const

function formatDate(dateString?: string | null) {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatCurrency(value?: number | string | null) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(Number(value || 0))
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

function tierClass(tier?: number | null) {
  switch (tier) {
    case 1:
      return 'border-amber-200 bg-amber-500/10 text-amber-700'
    case 2:
      return 'border-slate-300 bg-slate-500/10 text-slate-700'
    case 3:
      return 'border-sky-200 bg-sky-500/10 text-sky-700'
    default:
      return 'border-zinc-200 bg-zinc-500/10 text-zinc-700'
  }
}

export function LemiexUsers() {
  const { messages } = useI18n()
  const router = useRouter()
  const m = messages.usersPage ?? fallbackMessages

  const [users, setUsers] = useState<LemiexUserRecord[]>([])
  const [roles, setRoles] = useState<Array<{ id: number | string; name: string }>>([])
  const [filters, setFilters] = useState<LemiexUsersFilters>({
    search: '',
    status: '',
    role_id: '',
    tier: '',
  })
  const [pagination, setPagination] = useState<LemiexUsersPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  })
  const [loading, setLoading] = useState(true)
  const [showAddFundModal, setShowAddFundModal] = useState(false)
  const [selectedUserId, setSelectedUserId] = useState('')
  const [sellers, setSellers] = useState<LemiexUserRecord[]>([])
  const [loadingSellers, setLoadingSellers] = useState(false)
  const [isAddingFund, setIsAddingFund] = useState(false)
  const [addFundData, setAddFundData] = useState({
    amount: '',
    type: 'Deposit' as 'Deposit' | 'Withdraw',
    note: '',
  })

  const selectedUser = useMemo(
    () => sellers.find((seller) => String(seller.id) === selectedUserId) || null,
    [selectedUserId, sellers]
  )

  useEffect(() => {
    let active = true
    async function loadRoles() {
      try {
        const response = await fetchLemiexUserRoles()
        if (!active) return
        setRoles(
          response.map((role) => ({
            id: role.id,
            name:
              role.display_name || role.name || String(role.id),
          }))
        )
      } catch {
        if (!active) return
      }
    }
    void loadRoles()
    return () => {
      active = false
    }
  }, [])

  const loadUsers = useCallback(async () => {
    try {
      setLoading(true)
      const response = await fetchLemiexUsers({
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...filters,
      })
      setUsers(response.users)
      setPagination(response.pagination)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.loadFailed)
    } finally {
      setLoading(false)
    }
  }, [filters, m.loadFailed, pagination.current_page, pagination.per_page])

  useEffect(() => {
    void loadUsers()
  }, [loadUsers])

  async function openAddFundModal() {
    setSelectedUserId('')
    setAddFundData({ amount: '', type: 'Deposit', note: '' })
    setShowAddFundModal(true)
    setLoadingSellers(true)
    try {
      const response = await fetchLemiexUsers({
        page: 1,
        per_page: 100,
        role_id: '2',
      })
      setSellers(response.users)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.loadFailed)
    } finally {
      setLoadingSellers(false)
    }
  }

  const handleDelete = useCallback(async (id: number | string) => {
    if (!window.confirm(m.deleteConfirm)) return
    try {
      await deleteLemiexUser(id)
      toast.success(m.deleteSuccess)
      await loadUsers()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.deleteFailed)
    }
  }, [loadUsers, m.deleteConfirm, m.deleteFailed, m.deleteSuccess])

  async function handleAddFund() {
    if (!selectedUser) {
      toast.error(m.addFundModal.selectSellerRequired)
      return
    }
    if (!addFundData.amount || Number(addFundData.amount) <= 0) {
      toast.error(m.addFundModal.invalidAmount)
      return
    }
    try {
      setIsAddingFund(true)
      const response = await addFundToLemiexUser(selectedUser.id, {
        type: addFundData.type,
        amount: Number(addFundData.amount),
        note:
          addFundData.note ||
          `Admin ${addFundData.type === 'Withdraw' ? 'withdraw' : 'deposit'} to ${selectedUser.username}`,
      })
      const balance = Number(response.data?.new_balance || 0)
      toast.success(
        m.addFundModal.fundSuccess
          .replace('{action}', addFundData.type === 'Withdraw' ? 'deducted' : 'added')
          .replace('{amount}', addFundData.amount)
          .replace('{direction}', addFundData.type === 'Withdraw' ? 'from' : 'to')
          .replace('{user}', selectedUser.username || '')
          .replace('{balance}', String(balance))
      )
      setShowAddFundModal(false)
      await loadUsers()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.addFundModal.fundFailed)
    } finally {
      setIsAddingFund(false)
    }
  }

  const columns = useMemo<ColumnDef<LemiexUserRecord>[]>(
    () => [
      {
        id: 'username',
        header: m.columns.username,
        cell: ({ row }) => (
          <div className='flex items-center gap-3'>
            {row.original.profile?.avatar ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={row.original.profile.avatar}
                alt={row.original.username || 'avatar'}
                className='size-9 rounded-full object-cover'
              />
            ) : (
              <div className='flex size-9 items-center justify-center rounded-full bg-muted font-semibold'>
                {String(row.original.username || '?').charAt(0).toUpperCase()}
              </div>
            )}
            <span className='font-medium'>{row.original.username || m.na}</span>
          </div>
        ),
        meta: { thClassName: 'min-w-[200px]' },
      },
      {
        id: 'full_name',
        header: m.columns.fullName,
        cell: ({ row }) => {
          const fullName = `${row.original.profile?.first_name || ''} ${row.original.profile?.last_name || ''}`.trim()
          return fullName || m.na
        },
        meta: { thClassName: 'min-w-[170px]' },
      },
      {
        id: 'role',
        header: m.columns.role,
        cell: ({ row }) => (
          <Badge variant='outline' className='border-slate-200 bg-slate-500/10 text-slate-700'>
            {row.original.role?.display_name || row.original.role?.name || m.na}
          </Badge>
        ),
        meta: { thClassName: 'min-w-[120px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'email',
        header: m.columns.email,
        cell: ({ row }) => row.original.email || m.na,
        meta: { thClassName: 'min-w-[220px]' },
      },
      {
        id: 'balance',
        header: m.columns.balance,
        cell: ({ row }) => (
          <span
            className={
              Number(row.original.profile?.wallet_balance || 0) < 0
                ? 'font-semibold text-rose-600'
                : 'font-semibold text-emerald-600'
            }
          >
            {formatCurrency(row.original.profile?.wallet_balance)}
          </span>
        ),
        meta: { thClassName: 'min-w-[130px] text-right', tdClassName: 'text-right' },
      },
      {
        id: 'tier',
        header: m.columns.tier,
        cell: ({ row }) => {
          const tier = Number(row.original.profile?.private_seller || 0)
          const tierKey = TIERS.find((item) => item.id === tier)?.key || 'silver'
          return (
            <Badge variant='outline' className={tierClass(tier)}>
              {m.tiers[tierKey as keyof typeof m.tiers]}
            </Badge>
          )
        },
        meta: { thClassName: 'min-w-[120px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'created_at',
        header: m.columns.registrationDate,
        cell: ({ row }) => formatDate(row.original.created_at),
        meta: { thClassName: 'min-w-[150px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'status',
        header: m.columns.status,
        cell: ({ row }) => (
          <Badge variant='outline' className={statusClass(row.original.status)}>
            {row.original.status === 'Active'
              ? m.status.active
              : row.original.status === 'Banned'
                ? m.status.banned
                : m.status.unconfirmed}
          </Badge>
        ),
        meta: { thClassName: 'min-w-[130px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'actions',
        header: m.columns.actions,
        cell: ({ row }) => (
          <div className='flex justify-center gap-2'>
            <Button
              variant='outline'
              size='icon'
              className='rounded-[6px]'
              onClick={() => router.push(`/lemiex/users/${row.original.id}`)}
              title={m.viewTitle}
            >
              <Eye className='size-4' />
            </Button>
            <Button
              variant='outline'
              size='icon'
              className='rounded-[6px]'
              onClick={() => router.push(`/lemiex/users/${row.original.id}/edit`)}
              title={m.editTitle}
            >
              <Pencil className='size-4' />
            </Button>
            <Button
              variant='outline'
              size='icon'
              className='rounded-[6px] border-rose-200 text-rose-700 hover:bg-rose-50'
              onClick={() => void handleDelete(row.original.id)}
              title={m.deleteTitle}
            >
              <Trash2 className='size-4' />
            </Button>
          </div>
        ),
        meta: { thClassName: 'min-w-[150px] text-center', tdClassName: 'text-center' },
      },
    ],
    [handleDelete, m, router]
  )

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

      <Main fluid className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
        <div className='space-y-6'>
          <div className='flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between'>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            <div className='flex flex-wrap gap-2'>
              <Button variant='outline' className='h-10 rounded-[6px]' onClick={() => void openAddFundModal()}>
                <Wallet className='size-4' />
                {m.addFund}
              </Button>
              <Button asChild className='h-10 rounded-[6px]'>
                <Link href='/lemiex/users/create'>
                  <Plus className='size-4' />
                  {m.addNew}
                </Link>
              </Button>
            </div>
          </div>

          <Card className='rounded-[6px]'>
            <CardContent className='grid gap-3 p-4 lg:grid-cols-[minmax(0,1.8fr)_200px_200px_180px]'>
              <Input
                value={filters.search || ''}
                onChange={(event) => {
                  setFilters((prev) => ({ ...prev, search: event.target.value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
                className='h-9 rounded-[6px]'
                placeholder={m.filters.search}
              />

              <Select
                value={filters.status || ALL_VALUE}
                onValueChange={(value) => {
                  setFilters((prev) => ({ ...prev, status: value === ALL_VALUE ? '' : value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
              >
                <SelectTrigger className='h-9 w-full rounded-[6px]'>
                  <SelectValue placeholder={m.filters.allStatus} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL_VALUE}>{m.filters.allStatus}</SelectItem>
                  <SelectItem value='Active'>{m.status.active}</SelectItem>
                  <SelectItem value='Unconfirmed'>{m.status.unconfirmed}</SelectItem>
                  <SelectItem value='Banned'>{m.status.banned}</SelectItem>
                </SelectContent>
              </Select>

              <Select
                value={filters.role_id || ALL_VALUE}
                onValueChange={(value) => {
                  setFilters((prev) => ({ ...prev, role_id: value === ALL_VALUE ? '' : value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
              >
                <SelectTrigger className='h-9 w-full rounded-[6px]'>
                  <SelectValue placeholder={m.filters.allRoles} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL_VALUE}>{m.filters.allRoles}</SelectItem>
                  {roles.map((role) => (
                    <SelectItem key={String(role.id)} value={String(role.id)}>
                      {role.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select
                value={filters.tier || ALL_VALUE}
                onValueChange={(value) => {
                  setFilters((prev) => ({ ...prev, tier: value === ALL_VALUE ? '' : value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
              >
                <SelectTrigger className='h-9 w-full rounded-[6px]'>
                  <SelectValue placeholder={m.filters.allTiers} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL_VALUE}>{m.filters.allTiers}</SelectItem>
                  {TIERS.map((tier) => (
                    <SelectItem key={tier.id} value={String(tier.id)}>
                      {m.tiers[tier.key]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </CardContent>
          </Card>

          <LemiexDataTable
            columns={columns}
            data={users}
            page={pagination.current_page}
            pageSize={pagination.per_page}
            total={pagination.total}
            loading={loading}
            loadingText={m.loadFailed}
            emptyText={m.notFound}
            getRowId={(row) => String(row.id)}
            onPageChange={(page) => setPagination((prev) => ({ ...prev, current_page: page }))}
            onPageSizeChange={(pageSize) =>
              setPagination((prev) => ({ ...prev, current_page: 1, per_page: pageSize }))
            }
          />
        </div>
      </Main>

      <Dialog open={showAddFundModal} onOpenChange={setShowAddFundModal}>
        <DialogContent className='rounded-[6px] sm:max-w-[720px]'>
          <DialogHeader>
            <DialogTitle>{m.addFundModal.title}</DialogTitle>
          </DialogHeader>
          <div className='space-y-5'>
            <div className='space-y-2'>
              <label className='text-sm font-medium'>{m.addFundModal.selectSeller}</label>
              <Select
                value={selectedUserId || ALL_VALUE}
                onValueChange={(value) => setSelectedUserId(value === ALL_VALUE ? '' : value)}
                disabled={loadingSellers}
              >
                <SelectTrigger className='h-10 w-full rounded-[6px]'>
                  <SelectValue
                    placeholder={
                      loadingSellers
                        ? m.addFundModal.loadingSellers
                        : m.addFundModal.selectPlaceholder
                    }
                  />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL_VALUE}>{m.addFundModal.selectPlaceholder}</SelectItem>
                  {sellers.map((seller) => (
                    <SelectItem key={String(seller.id)} value={String(seller.id)}>
                      {(seller.username || m.na) +
                        ` (${formatCurrency(seller.profile?.wallet_balance)})`}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {selectedUser ? (
              <Card className='rounded-[6px] bg-muted/30'>
                <CardContent className='flex items-center justify-between p-4 text-sm'>
                  <span>{`${m.addFundModal.currentBalance} (${selectedUser.username}):`}</span>
                  <strong
                    className={
                      Number(selectedUser.profile?.wallet_balance || 0) < 0
                        ? 'text-rose-600'
                        : 'text-emerald-600'
                    }
                  >
                    {formatCurrency(selectedUser.profile?.wallet_balance)}
                  </strong>
                </CardContent>
              </Card>
            ) : null}

            <div className='grid gap-4 md:grid-cols-2'>
              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.addFundModal.type}</label>
                <Select
                  value={addFundData.type}
                  onValueChange={(value: 'Deposit' | 'Withdraw') =>
                    setAddFundData((prev) => ({ ...prev, type: value }))
                  }
                >
                  <SelectTrigger className='h-10 w-full rounded-[6px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='Deposit'>{m.addFundModal.deposit}</SelectItem>
                    <SelectItem value='Withdraw'>{m.addFundModal.withdraw}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{`${m.addFundModal.amount} ($)`}</label>
                <Input
                  type='number'
                  step='0.01'
                  min='0.01'
                  value={addFundData.amount}
                  onChange={(event) =>
                    setAddFundData((prev) => ({ ...prev, amount: event.target.value }))
                  }
                  className='h-10 w-full rounded-[6px]'
                  placeholder={m.addFundModal.enterAmount}
                />
              </div>
            </div>

            <div className='space-y-2'>
              <label className='text-sm font-medium'>{m.addFundModal.note}</label>
              <Textarea
                value={addFundData.note}
                onChange={(event) =>
                  setAddFundData((prev) => ({ ...prev, note: event.target.value }))
                }
                className='min-h-[120px] rounded-[6px]'
                placeholder={m.addFundModal.notePlaceholder}
              />
            </div>

            {selectedUser && addFundData.amount ? (
              <Card className='rounded-[6px] bg-muted/30'>
                <CardContent className='flex items-center justify-between p-4 text-sm'>
                  <span>{m.addFundModal.newBalance}</span>
                  <strong>
                    {formatCurrency(
                      (Number(selectedUser.profile?.wallet_balance || 0) || 0) +
                        (addFundData.type === 'Withdraw' ? -1 : 1) *
                          Number(addFundData.amount || 0)
                    )}
                  </strong>
                </CardContent>
              </Card>
            ) : null}
          </div>
          <DialogFooter>
            <Button
              variant='outline'
              className='rounded-[6px]'
              onClick={() => setShowAddFundModal(false)}
            >
              {m.addFundModal.cancel}
            </Button>
            <Button
              className='rounded-[6px]'
              onClick={() => void handleAddFund()}
              disabled={isAddingFund}
            >
              {m.addFundModal.submit}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

'use client'

import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type FormEvent,
  type ReactNode,
} from 'react'
import { ChevronDown, Plus, SquarePen, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { useI18n } from '@/context/i18n-provider'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cn } from '@/lib/utils'
import {
  createEmbroideryFee,
  createExtraFee,
  createPriorityFee,
  createRefundFee,
  createTier,
  deleteEmbroideryFee,
  deleteExtraFee,
  deletePriorityFee,
  deleteRefundFee,
  deleteTier,
  fetchTiers,
  type TierEmbroideryFee,
  type TierExtraFee,
  type TierPriorityFee,
  type TierRecord,
  type TierRefundFee,
  updateEmbroideryFee,
  updateExtraFee,
  updatePriorityFee,
  updateRefundFee,
  updateTier,
} from '@/services/tiers/api'

type FeeType = 'extra' | 'refund' | 'embroidery' | 'priority'
type FeeEntity = TierExtraFee | TierRefundFee | TierEmbroideryFee | TierPriorityFee

type DeleteState =
  | { open: false }
  | {
      open: true
      type: 'tier' | FeeType
      tier: TierRecord
      fee?: FeeEntity
    }

const fallbackMessages = {
  title: 'Tiers',
  createTier: 'Create Tier',
  loading: 'Loading tiers...',
  noTiers: 'No tiers available',
  tierBadge: 'Tier',
  extraFees: 'Extra Fees',
  refundFees: 'Refund Fees',
  embroideryFees: 'Embroidery Fees',
  priorityFees: 'Priority Fees',
  addExtraFee: 'Add Extra Fee',
  addRefundFee: 'Add Refund Fee',
  addEmbroideryFee: 'Add Embroidery Fee',
  addPriorityFee: 'Add Priority Fee',
  emptyExtraFees: 'No extra fees configured',
  emptyRefundFees: 'No refund fees configured',
  emptyEmbroideryFees: 'No embroidery fees configured',
  emptyPriorityFees: 'No priority fees configured',
  minStitch: 'Min Stitch',
  maxStitch: 'Max Stitch',
  amount: 'Amount ($)',
  stitch: 'Stitch',
  type: 'Type',
  name: 'Name',
  displayName: 'Display Name',
  description: 'Description',
  price: 'Price ($)',
  actions: 'Actions',
  edit: 'Edit',
  delete: 'Delete',
  createTitle: 'Create Tier',
  editTitle: 'Edit Tier',
  tierName: 'Tier Name',
  tierNamePlaceholder: 'Enter tier name',
  save: 'Save',
  cancel: 'Cancel',
  creating: 'Creating...',
  saving: 'Saving...',
  deleting: 'Deleting...',
  confirmDeleteTitle: 'Confirm Delete',
  confirmDeleteDescription: 'This action cannot be undone.',
  extraFeeDialogTitle: 'Extra Fee',
  refundFeeDialogTitle: 'Refund Fee',
  embroideryFeeDialogTitle: 'Embroidery Fee',
  priorityFeeDialogTitle: 'Priority Fee',
  embroideryType: 'Embroidery Type',
  embroideryTypePlaceholder: 'Select embroidery type',
  priorityName: 'Priority Name',
  priorityDisplayNamePlaceholder: 'Priority',
  priorityDescriptionPlaceholder: 'Standard processing 3-5 days',
  standard: 'Standard',
  metallic: 'Metallic',
  glow: 'Glow',
  puff: 'Puff',
  normalPriority: 'Normal',
  rushPriority: 'Priority',
  requiredTierName: 'Tier name is required',
  requiredFields: 'Please fill in all required fields',
  tierCreated: 'Tier created successfully',
  tierUpdated: 'Tier updated successfully',
  tierDeleted: 'Tier deleted successfully',
  feeCreated: 'Fee created successfully',
  feeUpdated: 'Fee updated successfully',
  feeDeleted: 'Fee deleted successfully',
  failedLoad: 'Failed to load tiers',
  failedCreateTier: 'Failed to create tier',
  failedUpdateTier: 'Failed to update tier',
  failedDeleteTier: 'Failed to delete tier',
  failedSaveFee: 'Failed to save fee',
  failedDeleteFee: 'Failed to delete fee',
}

const EMBROIDERY_TYPES = ['standard', 'metallic', 'glow', 'puff'] as const

const PRIORITY_OPTIONS = [
  { value: 'normal', description: 'Standard processing' },
  { value: 'priority', description: 'Expedited processing' },
] as const

function formatCurrency(amount: number | string | null | undefined) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(Number(amount || 0))
}

function formatNumber(value: number | string | null | undefined) {
  return new Intl.NumberFormat('en-US').format(Number(value || 0))
}

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback
}

function FeeSection({
  title,
  addLabel,
  headers,
  hasRows,
  emptyText,
  onAdd,
  children,
}: {
  title: string
  addLabel: string
  headers: string[]
  hasRows: boolean
  emptyText: string
  onAdd: () => void
  children: ReactNode
}) {
  return (
    <div className='overflow-hidden rounded-[8px] border bg-background'>
      <div className='flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between'>
        <h3 className='text-sm font-semibold'>{title}</h3>
        <Button variant='outline' className='h-9 rounded-[6px]' onClick={onAdd}>
          <Plus className='mr-2 size-4' />
          {addLabel}
        </Button>
      </div>

      {hasRows ? (
        <div className='overflow-x-auto'>
          <Table className='min-w-[640px]'>
            <TableHeader>
              <TableRow>
                {headers.map((header) => (
                  <TableHead key={header}>{header}</TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>{children}</TableBody>
          </Table>
        </div>
      ) : (
        <div className='px-4 py-6 text-sm text-muted-foreground'>{emptyText}</div>
      )}
    </div>
  )
}

export function LemiexTiersPage() {
  const { messages } = useI18n()
  const m = messages.tiersPage ?? fallbackMessages

  const [loading, setLoading] = useState(true)
  const [tiers, setTiers] = useState<TierRecord[]>([])
  const [expanded, setExpanded] = useState<Record<number, boolean>>({})
  const [saving, setSaving] = useState(false)
  const [selectedTier, setSelectedTier] = useState<TierRecord | null>(null)
  const [tierDialogOpen, setTierDialogOpen] = useState(false)
  const [tierDialogMode, setTierDialogMode] = useState<'create' | 'edit'>('create')
  const [tierName, setTierName] = useState('')
  const [feeDialogOpen, setFeeDialogOpen] = useState(false)
  const [feeDialogMode, setFeeDialogMode] = useState<'create' | 'edit'>('create')
  const [feeType, setFeeType] = useState<FeeType>('extra')
  const [selectedFee, setSelectedFee] = useState<FeeEntity | null>(null)
  const [deleteState, setDeleteState] = useState<DeleteState>({ open: false })
  const [form, setForm] = useState<Record<string, string>>({})

  const embroideryOptions = useMemo(
    () =>
      EMBROIDERY_TYPES.map((value) => ({
        value,
        label:
          value === 'standard'
            ? m.standard
            : value === 'metallic'
              ? m.metallic
              : value === 'glow'
                ? m.glow
                : m.puff,
      })),
    [m.glow, m.metallic, m.puff, m.standard]
  )

  const priorityOptions = useMemo(
    () =>
      PRIORITY_OPTIONS.map((item) => ({
        value: item.value,
        displayName: item.value === 'normal' ? m.normalPriority : m.rushPriority,
        description: item.description,
      })),
    [m.normalPriority, m.rushPriority]
  )

  const loadTiers = useCallback(async () => {
    try {
      setLoading(true)
      const data = await fetchTiers()
      setTiers(data)
      setExpanded((prev) => {
        const next = { ...prev }
        data.forEach((tier) => {
          if (next[tier.id] === undefined) next[tier.id] = false
        })
        return next
      })
    } catch (error) {
      toast.error(getErrorMessage(error, m.failedLoad))
    } finally {
      setLoading(false)
    }
  }, [m.failedLoad])

  useEffect(() => {
    void loadTiers()
  }, [loadTiers])

  function openCreateTier() {
    setTierDialogMode('create')
    setTierName('')
    setSelectedTier(null)
    setTierDialogOpen(true)
  }

  function openEditTier(tier: TierRecord) {
    setTierDialogMode('edit')
    setTierName(tier.name || '')
    setSelectedTier(tier)
    setTierDialogOpen(true)
  }

  function openFeeDialog(type: FeeType, tier: TierRecord, fee?: FeeEntity) {
    setFeeType(type)
    setSelectedTier(tier)
    setSelectedFee(fee || null)
    setFeeDialogMode(fee ? 'edit' : 'create')

    if (type === 'extra') {
      const current = fee as TierExtraFee | undefined
      setForm({
        min_stitch: current ? String(current.min_stitch) : '',
        max_stitch: current ? String(current.max_stitch) : '',
        amount: current ? String(current.amount) : '',
      })
    } else if (type === 'refund') {
      const current = fee as TierRefundFee | undefined
      setForm({
        stitch: current ? String(current.stitch) : '',
        amount: current ? String(current.amount) : '',
      })
    } else if (type === 'embroidery') {
      const current = fee as TierEmbroideryFee | undefined
      setForm({
        embroidery_type: current?.embroidery_type || '',
        min_stitch: current ? String(current.min_stitch) : '',
        max_stitch: current ? String(current.max_stitch) : '',
        amount: current ? String(current.amount) : '',
      })
    } else {
      const current = fee as TierPriorityFee | undefined
      setForm({
        name: current?.name || '',
        display_name: current?.display_name || '',
        description: current?.description || '',
        price: current ? String(current.price) : '',
      })
    }

    setFeeDialogOpen(true)
  }

  async function handleSaveTier(event: FormEvent) {
    event.preventDefault()
    if (!tierName.trim()) {
      toast.error(m.requiredTierName)
      return
    }

    try {
      setSaving(true)
      if (tierDialogMode === 'create') {
        await createTier(tierName.trim())
        toast.success(m.tierCreated)
      } else if (selectedTier) {
        await updateTier(selectedTier.id, tierName.trim())
        toast.success(m.tierUpdated)
      }
      setTierDialogOpen(false)
      await loadTiers()
    } catch (error) {
      toast.error(
        getErrorMessage(
          error,
          tierDialogMode === 'create' ? m.failedCreateTier : m.failedUpdateTier
        )
      )
    } finally {
      setSaving(false)
    }
  }

  async function handleSaveFee(event: FormEvent) {
    event.preventDefault()
    if (!selectedTier) return

    try {
      setSaving(true)

      if (feeType === 'extra') {
        if (!form.min_stitch || !form.max_stitch || !form.amount) {
          toast.error(m.requiredFields)
          return
        }
        const payload = {
          min_stitch: Number(form.min_stitch),
          max_stitch: Number(form.max_stitch),
          amount: Number(form.amount),
        }
        if (feeDialogMode === 'create') {
          await createExtraFee(selectedTier.id, payload)
        } else if (selectedFee) {
          await updateExtraFee(selectedTier.id, selectedFee.id, payload)
        }
      } else if (feeType === 'refund') {
        if (!form.stitch || !form.amount) {
          toast.error(m.requiredFields)
          return
        }
        const payload = {
          stitch: Number(form.stitch),
          amount: Number(form.amount),
        }
        if (feeDialogMode === 'create') {
          await createRefundFee(selectedTier.id, payload)
        } else if (selectedFee) {
          await updateRefundFee(selectedTier.id, selectedFee.id, payload)
        }
      } else if (feeType === 'embroidery') {
        if (!form.embroidery_type || !form.min_stitch || !form.max_stitch || !form.amount) {
          toast.error(m.requiredFields)
          return
        }
        const payload = {
          embroidery_type: form.embroidery_type,
          min_stitch: Number(form.min_stitch),
          max_stitch: Number(form.max_stitch),
          amount: Number(form.amount),
        }
        if (feeDialogMode === 'create') {
          await createEmbroideryFee(selectedTier.id, payload)
        } else if (selectedFee) {
          await updateEmbroideryFee(selectedTier.id, selectedFee.id, payload)
        }
      } else {
        if (!form.name || !form.display_name || !form.price) {
          toast.error(m.requiredFields)
          return
        }
        const payload = {
          name: form.name,
          display_name: form.display_name,
          description: form.description || '',
          price: Number(form.price),
        }
        if (feeDialogMode === 'create') {
          await createPriorityFee(selectedTier.id, payload)
        } else if (selectedFee) {
          await updatePriorityFee(selectedTier.id, selectedFee.id, payload)
        }
      }

      toast.success(feeDialogMode === 'create' ? m.feeCreated : m.feeUpdated)
      setFeeDialogOpen(false)
      await loadTiers()
    } catch (error) {
      toast.error(getErrorMessage(error, m.failedSaveFee))
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete() {
    if (!deleteState.open) return

    try {
      setSaving(true)
      if (deleteState.type === 'tier') {
        await deleteTier(deleteState.tier.id)
        toast.success(m.tierDeleted)
      } else if (deleteState.fee) {
        if (deleteState.type === 'extra') {
          await deleteExtraFee(deleteState.tier.id, deleteState.fee.id)
        } else if (deleteState.type === 'refund') {
          await deleteRefundFee(deleteState.tier.id, deleteState.fee.id)
        } else if (deleteState.type === 'embroidery') {
          await deleteEmbroideryFee(deleteState.tier.id, deleteState.fee.id)
        } else {
          await deletePriorityFee(deleteState.tier.id, deleteState.fee.id)
        }
        toast.success(m.feeDeleted)
      }
      setDeleteState({ open: false })
      await loadTiers()
    } catch (error) {
      toast.error(
        getErrorMessage(
          error,
          deleteState.type === 'tier' ? m.failedDeleteTier : m.failedDeleteFee
        )
      )
    } finally {
      setSaving(false)
    }
  }

  function feeDialogTitle() {
    if (feeType === 'extra') return m.extraFeeDialogTitle
    if (feeType === 'refund') return m.refundFeeDialogTitle
    if (feeType === 'embroidery') return m.embroideryFeeDialogTitle
    return m.priorityFeeDialogTitle
  }

  function deleteDescription() {
    if (!deleteState.open) return m.confirmDeleteDescription
    if (deleteState.type === 'tier') {
      return `${deleteState.tier.name} will be removed together with all related fees.`
    }
    const fee = deleteState.fee
    if (!fee) return m.confirmDeleteDescription
    if (deleteState.type === 'extra' && 'min_stitch' in fee) {
      return `Delete extra fee for ${formatNumber(fee.min_stitch)} - ${formatNumber(fee.max_stitch)} stitches?`
    }
    if (deleteState.type === 'refund' && 'stitch' in fee) {
      return `Delete refund fee for ${formatNumber(fee.stitch)} stitches?`
    }
    if (deleteState.type === 'embroidery' && 'embroidery_type' in fee) {
      return `Delete ${fee.embroidery_type} fee for ${formatNumber(fee.min_stitch)} - ${formatNumber(fee.max_stitch)} stitches?`
    }
    if (deleteState.type === 'priority' && 'display_name' in fee) {
      return `Delete priority fee ${fee.display_name}?`
    }
    return m.confirmDeleteDescription
  }

  function renderFeeFields() {
    if (feeType === 'extra') {
      return (
        <>
          <div className='grid gap-4 sm:grid-cols-2'>
            <div className='space-y-2'>
              <Label>{m.minStitch}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                value={form.min_stitch || ''}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, min_stitch: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.maxStitch}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                value={form.max_stitch || ''}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, max_stitch: event.target.value }))
                }
              />
            </div>
          </div>
          <div className='space-y-2'>
            <Label>{m.amount}</Label>
            <Input
              type='number'
              step='0.01'
              className='h-11 rounded-[6px]'
              value={form.amount || ''}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, amount: event.target.value }))
              }
            />
          </div>
        </>
      )
    }

    if (feeType === 'refund') {
      return (
        <>
          <div className='space-y-2'>
            <Label>{m.stitch}</Label>
            <Input
              type='number'
              className='h-11 rounded-[6px]'
              value={form.stitch || ''}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, stitch: event.target.value }))
              }
            />
          </div>
          <div className='space-y-2'>
            <Label>{m.amount}</Label>
            <Input
              type='number'
              step='0.01'
              className='h-11 rounded-[6px]'
              value={form.amount || ''}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, amount: event.target.value }))
              }
            />
          </div>
        </>
      )
    }

    if (feeType === 'embroidery') {
      return (
        <>
          <div className='space-y-2'>
            <Label>{m.embroideryType}</Label>
            <Select
              value={form.embroidery_type || ''}
              onValueChange={(value) =>
                setForm((prev) => ({ ...prev, embroidery_type: value }))
              }
            >
              <SelectTrigger className='h-11 rounded-[6px]'>
                <SelectValue placeholder={m.embroideryTypePlaceholder} />
              </SelectTrigger>
              <SelectContent>
                {embroideryOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className='grid gap-4 sm:grid-cols-2'>
            <div className='space-y-2'>
              <Label>{m.minStitch}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                value={form.min_stitch || ''}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, min_stitch: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.maxStitch}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                value={form.max_stitch || ''}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, max_stitch: event.target.value }))
                }
              />
            </div>
          </div>
          <div className='space-y-2'>
            <Label>{m.amount}</Label>
            <Input
              type='number'
              step='0.01'
              className='h-11 rounded-[6px]'
              value={form.amount || ''}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, amount: event.target.value }))
              }
            />
          </div>
        </>
      )
    }

    return (
      <>
        <div className='grid gap-4 sm:grid-cols-2'>
          <div className='space-y-2'>
            <Label>{m.priorityName}</Label>
            <Select
              value={form.name || ''}
              onValueChange={(value) => {
                const selected = priorityOptions.find((item) => item.value === value)
                setForm((prev) => ({
                  ...prev,
                  name: value,
                  display_name: selected?.displayName || '',
                  description: prev.description || selected?.description || '',
                }))
              }}
              disabled={feeDialogMode === 'edit'}
            >
              <SelectTrigger className='h-11 rounded-[6px]'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {priorityOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.displayName}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className='space-y-2'>
            <Label>{m.displayName}</Label>
            <Input
              className='h-11 rounded-[6px]'
              placeholder={m.priorityDisplayNamePlaceholder}
              value={form.display_name || ''}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, display_name: event.target.value }))
              }
            />
          </div>
        </div>
        <div className='space-y-2'>
          <Label>{m.description}</Label>
          <Input
            className='h-11 rounded-[6px]'
            placeholder={m.priorityDescriptionPlaceholder}
            value={form.description || ''}
            onChange={(event) =>
              setForm((prev) => ({ ...prev, description: event.target.value }))
            }
          />
        </div>
        <div className='space-y-2'>
          <Label>{m.price}</Label>
          <Input
            type='number'
            step='0.01'
            className='h-11 rounded-[6px]'
            value={form.price || ''}
            onChange={(event) =>
              setForm((prev) => ({ ...prev, price: event.target.value }))
            }
          />
        </div>
      </>
    )
  }

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

      <Main fluid className='px-4 py-6 @7xl/content:px-6'>
        <div className='space-y-6'>
          <div className='flex flex-col gap-4 md:flex-row md:items-center md:justify-between'>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            <Button className='h-10 rounded-[6px]' onClick={openCreateTier}>
              <Plus className='mr-2 size-4' />
              {m.createTier}
            </Button>
          </div>

          {loading ? (
            <div className='py-16 text-center text-sm text-muted-foreground'>{m.loading}</div>
          ) : tiers.length === 0 ? (
            <div className='rounded-[8px] border bg-background py-16 text-center text-sm text-muted-foreground'>
              {m.noTiers}
            </div>
          ) : (
            <div className='space-y-4'>
              {tiers.map((tier) => (
                <Collapsible
                  key={tier.id}
                  open={expanded[tier.id] ?? true}
                  onOpenChange={(open) =>
                    setExpanded((prev) => ({ ...prev, [tier.id]: open }))
                  }
                >
                  <div className='overflow-hidden rounded-[8px] border bg-background'>
                    <div className='flex flex-col gap-4 border-b px-4 py-4 lg:flex-row lg:items-start lg:justify-between'>
                      <button
                        type='button'
                        className='flex flex-1 items-start gap-3 text-left'
                        onClick={() =>
                          setExpanded((prev) => ({ ...prev, [tier.id]: !prev[tier.id] }))
                        }
                      >
                        <ChevronDown
                          className={cn(
                            'mt-1 size-4 shrink-0 transition-transform',
                            expanded[tier.id] ? 'rotate-180' : 'rotate-0'
                          )}
                        />
                        <div className='space-y-3'>
                          <div className='flex flex-wrap items-center gap-3'>
                            <Badge variant='secondary' className='rounded-[6px] px-2.5 py-1'>
                              {m.tierBadge} {tier.tier_id}
                            </Badge>
                            <h2 className='text-lg font-semibold'>{tier.name}</h2>
                          </div>
                          <div className='flex flex-wrap gap-3 text-xs text-muted-foreground'>
                            <span>{m.extraFees}: {tier.extra_fees?.length || 0}</span>
                            <span>{m.refundFees}: {tier.refund_fees?.length || 0}</span>
                            <span>{m.embroideryFees}: {tier.embroidery_fees?.length || 0}</span>
                            <span>{m.priorityFees}: {tier.priority_fees?.length || 0}</span>
                          </div>
                        </div>
                      </button>

                      <div className='flex items-center gap-2'>
                        <Button
                          variant='outline'
                          size='icon'
                          className='rounded-[6px]'
                          onClick={() => openEditTier(tier)}
                        >
                          <SquarePen className='size-4' />
                        </Button>
                        <Button
                          variant='outline'
                          size='icon'
                          className='rounded-[6px] text-rose-600'
                          onClick={() => setDeleteState({ open: true, type: 'tier', tier })}
                        >
                          <Trash2 className='size-4' />
                        </Button>
                      </div>
                    </div>

                    <CollapsibleContent>
                      <div className='space-y-4 bg-muted/20 p-4'>
                        <FeeSection
                          title={m.extraFees}
                          addLabel={m.addExtraFee}
                          headers={[m.minStitch, m.maxStitch, m.amount, m.actions]}
                          hasRows={Boolean(tier.extra_fees?.length)}
                          emptyText={m.emptyExtraFees}
                          onAdd={() => openFeeDialog('extra', tier)}
                        >
                          {tier.extra_fees?.map((fee) => (
                            <TableRow key={fee.id}>
                              <TableCell>{formatNumber(fee.min_stitch)}</TableCell>
                              <TableCell>{formatNumber(fee.max_stitch)}</TableCell>
                              <TableCell className='font-medium'>{formatCurrency(fee.amount)}</TableCell>
                              <TableCell>
                                <div className='flex gap-2'>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3'
                                    onClick={() => openFeeDialog('extra', tier, fee)}
                                  >
                                    {m.edit}
                                  </Button>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3 text-rose-600'
                                    onClick={() =>
                                      setDeleteState({ open: true, type: 'extra', tier, fee })
                                    }
                                  >
                                    {m.delete}
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          ))}
                        </FeeSection>

                        <FeeSection
                          title={m.refundFees}
                          addLabel={m.addRefundFee}
                          headers={[m.stitch, m.amount, m.actions]}
                          hasRows={Boolean(tier.refund_fees?.length)}
                          emptyText={m.emptyRefundFees}
                          onAdd={() => openFeeDialog('refund', tier)}
                        >
                          {tier.refund_fees?.map((fee) => (
                            <TableRow key={fee.id}>
                              <TableCell>{formatNumber(fee.stitch)}</TableCell>
                              <TableCell className='font-medium'>{formatCurrency(fee.amount)}</TableCell>
                              <TableCell>
                                <div className='flex gap-2'>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3'
                                    onClick={() => openFeeDialog('refund', tier, fee)}
                                  >
                                    {m.edit}
                                  </Button>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3 text-rose-600'
                                    onClick={() =>
                                      setDeleteState({ open: true, type: 'refund', tier, fee })
                                    }
                                  >
                                    {m.delete}
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          ))}
                        </FeeSection>

                        <FeeSection
                          title={m.embroideryFees}
                          addLabel={m.addEmbroideryFee}
                          headers={[m.type, m.minStitch, m.maxStitch, m.amount, m.actions]}
                          hasRows={Boolean(tier.embroidery_fees?.length)}
                          emptyText={m.emptyEmbroideryFees}
                          onAdd={() => openFeeDialog('embroidery', tier)}
                        >
                          {tier.embroidery_fees?.map((fee) => (
                            <TableRow key={fee.id}>
                              <TableCell className='capitalize'>{fee.embroidery_type}</TableCell>
                              <TableCell>{formatNumber(fee.min_stitch)}</TableCell>
                              <TableCell>{formatNumber(fee.max_stitch)}</TableCell>
                              <TableCell className='font-medium'>{formatCurrency(fee.amount)}</TableCell>
                              <TableCell>
                                <div className='flex gap-2'>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3'
                                    onClick={() => openFeeDialog('embroidery', tier, fee)}
                                  >
                                    {m.edit}
                                  </Button>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3 text-rose-600'
                                    onClick={() =>
                                      setDeleteState({
                                        open: true,
                                        type: 'embroidery',
                                        tier,
                                        fee,
                                      })
                                    }
                                  >
                                    {m.delete}
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          ))}
                        </FeeSection>

                        <FeeSection
                          title={m.priorityFees}
                          addLabel={m.addPriorityFee}
                          headers={[m.name, m.displayName, m.description, m.price, m.actions]}
                          hasRows={Boolean(tier.priority_fees?.length)}
                          emptyText={m.emptyPriorityFees}
                          onAdd={() => openFeeDialog('priority', tier)}
                        >
                          {tier.priority_fees?.map((fee) => (
                            <TableRow key={fee.id}>
                              <TableCell>{fee.name}</TableCell>
                              <TableCell>{fee.display_name}</TableCell>
                              <TableCell className='text-muted-foreground'>
                                {fee.description || '-'}
                              </TableCell>
                              <TableCell className='font-medium'>{formatCurrency(fee.price)}</TableCell>
                              <TableCell>
                                <div className='flex gap-2'>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3'
                                    onClick={() => openFeeDialog('priority', tier, fee)}
                                  >
                                    {m.edit}
                                  </Button>
                                  <Button
                                    variant='outline'
                                    className='h-8 rounded-[6px] px-3 text-rose-600'
                                    onClick={() =>
                                      setDeleteState({ open: true, type: 'priority', tier, fee })
                                    }
                                  >
                                    {m.delete}
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          ))}
                        </FeeSection>
                      </div>
                    </CollapsibleContent>
                  </div>
                </Collapsible>
              ))}
            </div>
          )}
        </div>
      </Main>

      <Dialog open={tierDialogOpen} onOpenChange={setTierDialogOpen}>
        <DialogContent className='rounded-[8px] sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>
              {tierDialogMode === 'create' ? m.createTitle : m.editTitle}
            </DialogTitle>
          </DialogHeader>
          <form className='space-y-4' onSubmit={handleSaveTier}>
            <div className='space-y-2'>
              <Label>{m.tierName}</Label>
              <Input
                className='h-11 rounded-[6px]'
                placeholder={m.tierNamePlaceholder}
                value={tierName}
                onChange={(event) => setTierName(event.target.value)}
              />
            </div>
            <DialogFooter>
              <Button
                type='button'
                variant='outline'
                className='h-11 rounded-[6px]'
                onClick={() => setTierDialogOpen(false)}
              >
                {m.cancel}
              </Button>
              <Button type='submit' className='h-11 rounded-[6px]' disabled={saving}>
                {saving ? (tierDialogMode === 'create' ? m.creating : m.saving) : m.save}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={feeDialogOpen} onOpenChange={setFeeDialogOpen}>
        <DialogContent className='rounded-[8px] sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>{feeDialogTitle()}</DialogTitle>
          </DialogHeader>
          <form className='space-y-4' onSubmit={handleSaveFee}>
            {renderFeeFields()}
            <DialogFooter>
              <Button
                type='button'
                variant='outline'
                className='h-11 rounded-[6px]'
                onClick={() => setFeeDialogOpen(false)}
              >
                {m.cancel}
              </Button>
              <Button type='submit' className='h-11 rounded-[6px]' disabled={saving}>
                {saving ? m.saving : m.save}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog
        open={deleteState.open}
        onOpenChange={(open) => {
          if (!open) setDeleteState({ open: false })
        }}
      >
        <AlertDialogContent className='rounded-[8px]'>
          <AlertDialogHeader>
            <AlertDialogTitle>{m.confirmDeleteTitle}</AlertDialogTitle>
            <AlertDialogDescription>{deleteDescription()}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className='h-11 rounded-[6px]'>
              {m.cancel}
            </AlertDialogCancel>
            <AlertDialogAction
              className='h-11 rounded-[6px]'
              onClick={(event) => {
                event.preventDefault()
                void handleDelete()
              }}
            >
              {saving ? m.deleting : m.delete}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

export { LemiexTiersPage as LemiexTiers }

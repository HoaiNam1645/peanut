'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { SquarePen, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { useI18n } from '@/context/i18n-provider'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  createPayrollTier,
  deletePayrollTier,
  fetchPayrollTiers,
  type PayrollTier,
  updatePayrollTier,
} from '@/services/payroll/api'

const fallbackMessages = {
  title: 'Salary Tiers',
  subtitle: 'Manage payroll salary tiers',
  createTier: 'Create Tier',
  tierName: 'Tier Name',
  hourlyRate: 'Hourly Rate',
  currency: 'Currency',
  description: 'Description',
  actions: 'Actions',
  noTiers: 'No salary tiers available',
  createTitle: 'Create Salary Tier',
  editTitle: 'Edit Salary Tier',
  deleteTitle: 'Delete Salary Tier',
  namePlaceholder: 'Enter tier name',
  ratePlaceholder: '15.00',
  descriptionPlaceholder: 'Optional notes for this tier',
  create: 'Create',
  creating: 'Creating...',
  save: 'Save',
  saving: 'Saving...',
  cancel: 'Cancel',
  delete: 'Delete',
  deleting: 'Deleting...',
  confirmDelete: 'Are you sure you want to delete this tier?',
  fillTypeAmount: 'Please fill in tier name and hourly rate',
  tierCreated: 'Salary tier created successfully',
  tierUpdated: 'Salary tier updated successfully',
  tierDeleted: 'Salary tier deleted successfully',
  failedLoadTiers: 'Failed to load salary tiers',
  failedCreateTier: 'Failed to create salary tier',
  failedUpdateTier: 'Failed to update salary tier',
  failedDeleteTier: 'Failed to delete salary tier',
}

export function LemiexPayrollTiersPage() {
  const { messages } = useI18n()
  const m = messages.payrollTiersPage ?? fallbackMessages
  const [loading, setLoading] = useState(true)
  const [tiers, setTiers] = useState<PayrollTier[]>([])
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showEditModal, setShowEditModal] = useState(false)
  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [selectedTier, setSelectedTier] = useState<PayrollTier | null>(null)
  const [saving, setSaving] = useState(false)
  const [formData, setFormData] = useState({
    name: '',
    hourly_rate: '',
    currency: 'USD',
    description: '',
  })

  const loadTiers = useCallback(async () => {
    try {
      setLoading(true)
      const response = await fetchPayrollTiers()
      setTiers(response)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedLoadTiers)
    } finally {
      setLoading(false)
    }
  }, [m.failedLoadTiers])

  useEffect(() => {
    void loadTiers()
  }, [loadTiers])

  function resetForm() {
    setFormData({ name: '', hourly_rate: '', currency: 'USD', description: '' })
  }

  async function handleCreate(event: React.FormEvent) {
    event.preventDefault()
    if (!formData.name || !formData.hourly_rate) {
      toast.error(m.fillTypeAmount)
      return
    }

    try {
      setSaving(true)
      await createPayrollTier({
        name: formData.name,
        hourly_rate: Number.parseFloat(formData.hourly_rate),
        currency: formData.currency,
        description: formData.description,
      })
      toast.success(m.tierCreated)
      setShowCreateModal(false)
      resetForm()
      await loadTiers()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedCreateTier)
    } finally {
      setSaving(false)
    }
  }

  async function handleUpdate(event: React.FormEvent) {
    event.preventDefault()
    if (!selectedTier) return
    if (!formData.name || !formData.hourly_rate) {
      toast.error(m.fillTypeAmount)
      return
    }

    try {
      setSaving(true)
      await updatePayrollTier(selectedTier.id, {
        name: formData.name,
        hourly_rate: Number.parseFloat(formData.hourly_rate),
        currency: formData.currency,
        description: formData.description,
      })
      toast.success(m.tierUpdated)
      setShowEditModal(false)
      resetForm()
      await loadTiers()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedUpdateTier)
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete() {
    if (!selectedTier) return
    try {
      setSaving(true)
      await deletePayrollTier(selectedTier.id)
      toast.success(m.tierDeleted)
      setShowDeleteModal(false)
      await loadTiers()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedDeleteTier)
    } finally {
      setSaving(false)
    }
  }

  const content = useMemo(
    () => (
      <div className='overflow-x-auto rounded-[6px] border bg-card'>
        <Table className='min-w-[840px]'>
          <TableHeader>
            <TableRow>
              <TableHead>ID</TableHead>
              <TableHead>{m.tierName}</TableHead>
              <TableHead className='text-center'>{m.hourlyRate}</TableHead>
              <TableHead className='text-center'>{m.currency}</TableHead>
              <TableHead>{m.description}</TableHead>
              <TableHead className='text-center'>{m.actions}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={6} className='h-24 text-center text-muted-foreground'>
                  {m.failedLoadTiers.replace('Failed', 'Loading')}
                </TableCell>
              </TableRow>
            ) : tiers.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className='h-24 text-center'>
                  {m.noTiers}
                </TableCell>
              </TableRow>
            ) : (
              tiers.map((tier) => (
                <TableRow key={String(tier.id)}>
                  <TableCell>#{tier.id}</TableCell>
                  <TableCell className='font-semibold'>{tier.name || 'N/A'}</TableCell>
                  <TableCell className='text-center'>
                    <span className='rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary'>
                      ${Number(tier.hourly_rate || 0).toFixed(2)}/hr
                    </span>
                  </TableCell>
                  <TableCell className='text-center'>{tier.currency || 'USD'}</TableCell>
                  <TableCell className='text-muted-foreground'>
                    {tier.description || '-'}
                  </TableCell>
                  <TableCell>
                    <div className='flex justify-center gap-2'>
                      <Button
                        variant='outline'
                        size='icon'
                        className='rounded-[6px]'
                        onClick={() => {
                          setSelectedTier(tier)
                          setFormData({
                            name: tier.name || '',
                            hourly_rate: String(tier.hourly_rate || ''),
                            currency: tier.currency || 'USD',
                            description: tier.description || '',
                          })
                          setShowEditModal(true)
                        }}
                      >
                        <SquarePen className='size-4' />
                      </Button>
                      <Button
                        variant='outline'
                        size='icon'
                        className='rounded-[6px] text-rose-600'
                        onClick={() => {
                          setSelectedTier(tier)
                          setShowDeleteModal(true)
                        }}
                      >
                        <Trash2 className='size-4' />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    ),
    [loading, m.actions, m.currency, m.description, m.failedLoadTiers, m.hourlyRate, m.noTiers, m.tierName, tiers]
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
            <div className='space-y-1'>
              <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
              <p className='text-sm text-muted-foreground'>{m.subtitle}</p>
            </div>
            <Button className='h-10 rounded-[6px]' onClick={() => {
              resetForm()
              setShowCreateModal(true)
            }}>
              {m.createTier}
            </Button>
          </div>
          {content}
        </div>
      </Main>

      <Dialog open={showCreateModal} onOpenChange={setShowCreateModal}>
        <DialogContent className='rounded-[6px] sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>{m.createTitle}</DialogTitle>
          </DialogHeader>
          <form className='space-y-4' onSubmit={handleCreate}>
            <div className='space-y-2'>
              <Label>{m.tierName}</Label>
              <Input
                className='h-11 rounded-[6px]'
                placeholder={m.namePlaceholder}
                value={formData.name}
                onChange={(event) => setFormData((prev) => ({ ...prev, name: event.target.value }))}
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.hourlyRate}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                placeholder={m.ratePlaceholder}
                value={formData.hourly_rate}
                onChange={(event) =>
                  setFormData((prev) => ({ ...prev, hourly_rate: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.currency}</Label>
              <Input
                className='h-11 rounded-[6px]'
                value={formData.currency}
                onChange={(event) =>
                  setFormData((prev) => ({ ...prev, currency: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.description}</Label>
              <textarea
                className='min-h-[92px] w-full rounded-[6px] border bg-background px-3 py-2 text-sm'
                placeholder={m.descriptionPlaceholder}
                value={formData.description}
                onChange={(event) =>
                  setFormData((prev) => ({ ...prev, description: event.target.value }))
                }
              />
            </div>
            <DialogFooter>
              <Button type='button' variant='outline' className='h-11 rounded-[6px]' onClick={() => setShowCreateModal(false)}>
                {m.cancel}
              </Button>
              <Button type='submit' className='h-11 rounded-[6px]' disabled={saving}>
                {saving ? m.creating : m.create}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={showEditModal} onOpenChange={setShowEditModal}>
        <DialogContent className='rounded-[6px] sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>{m.editTitle}</DialogTitle>
          </DialogHeader>
          <form className='space-y-4' onSubmit={handleUpdate}>
            <div className='space-y-2'>
              <Label>{m.tierName}</Label>
              <Input
                className='h-11 rounded-[6px]'
                placeholder={m.namePlaceholder}
                value={formData.name}
                onChange={(event) => setFormData((prev) => ({ ...prev, name: event.target.value }))}
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.hourlyRate}</Label>
              <Input
                type='number'
                className='h-11 rounded-[6px]'
                placeholder={m.ratePlaceholder}
                value={formData.hourly_rate}
                onChange={(event) =>
                  setFormData((prev) => ({ ...prev, hourly_rate: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.currency}</Label>
              <Input
                className='h-11 rounded-[6px]'
                value={formData.currency}
                onChange={(event) =>
                  setFormData((prev) => ({ ...prev, currency: event.target.value }))
                }
              />
            </div>
            <div className='space-y-2'>
              <Label>{m.description}</Label>
              <textarea
                className='min-h-[92px] w-full rounded-[6px] border bg-background px-3 py-2 text-sm'
                placeholder={m.descriptionPlaceholder}
                value={formData.description}
                onChange={(event) =>
                  setFormData((prev) => ({ ...prev, description: event.target.value }))
                }
              />
            </div>
            <DialogFooter>
              <Button type='button' variant='outline' className='h-11 rounded-[6px]' onClick={() => setShowEditModal(false)}>
                {m.cancel}
              </Button>
              <Button type='submit' className='h-11 rounded-[6px]' disabled={saving}>
                {saving ? m.saving : m.save}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={showDeleteModal} onOpenChange={setShowDeleteModal}>
        <DialogContent className='rounded-[6px] sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>{m.deleteTitle}</DialogTitle>
          </DialogHeader>
          <p className='text-sm text-muted-foreground'>{m.confirmDelete}</p>
          <DialogFooter>
            <Button type='button' variant='outline' className='h-11 rounded-[6px]' onClick={() => setShowDeleteModal(false)}>
              {m.cancel}
            </Button>
            <Button type='button' className='h-11 rounded-[6px]' disabled={saving} onClick={() => void handleDelete()}>
              {saving ? m.deleting : m.delete}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

'use client'

import { useEffect, useMemo, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { useI18n } from '@/context/i18n-provider'
import type { ProductPricePayload, ProductTier, ProductVariantSummary } from '@/services/products/api'

const PRICE_TYPES = {
  production: [
    { key: 'base_cost', label: 'Base Cost' },
  ],
  shipping: [
    { key: 'seller_shipping', label: 'Seller Shipping' },
    { key: 'tiktok_shipping', label: 'TikTok Shipping' },
    { key: 'priority_shipping', label: 'Priority Shipping' },
    { key: 'additional_standard', label: 'Additional Standard' },
    { key: 'additional_priority', label: 'Additional Priority' },
  ],
} as const

type PriceMatrix = Record<string, Record<string, string>>

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  variant: ProductVariantSummary | null
  tiers: ProductTier[]
  readOnly?: boolean
  onSave: (variantId: string, prices: ProductPricePayload[]) => Promise<void>
}

const pricingFallbackMessages = {
  title: 'Tier Pricing',
  noVariant: 'No variant selected',
  readOnly: 'Read only',
  production: 'Production Costs',
  shipping: 'Shipping Costs',
  type: 'Type',
  close: 'Close',
  cancel: 'Cancel',
  saving: 'Saving...',
  save: 'Save Changes',
  failed: 'Failed to update tier pricing',
} as const

export function ProductTierPricingDialog({
  open,
  onOpenChange,
  variant,
  tiers,
  readOnly = false,
  onSave,
}: Props) {
  const { messages } = useI18n()
  const m = messages.productVariants.detail?.pricing || pricingFallbackMessages
  const [matrix, setMatrix] = useState<PriceMatrix>({})
  const [saving, setSaving] = useState(false)
  const [hasChanges, setHasChanges] = useState(false)

  const allPriceTypes = useMemo(
    () => [...PRICE_TYPES.production, ...PRICE_TYPES.shipping],
    []
  )

  useEffect(() => {
    if (!open || !variant) return
    const next: PriceMatrix = {}
    tiers.forEach((tier) => {
      const source = variant.tier_pricing?.[tier.name] || {}
      next[String(tier.id)] = {}
      allPriceTypes.forEach(({ key }) => {
        next[String(tier.id)][key] =
          source[key] === null || source[key] === undefined ? '' : String(source[key])
      })
    })
    setMatrix(next)
    setHasChanges(false)
  }, [open, variant, tiers, allPriceTypes])

  function setValue(tierId: number, key: string, value: string) {
    setMatrix((prev) => ({
      ...prev,
      [String(tierId)]: {
        ...(prev[String(tierId)] || {}),
        [key]: value,
      },
    }))
    setHasChanges(true)
  }

  async function handleSave() {
    if (!variant) return
    setSaving(true)
    try {
      const payload: ProductPricePayload[] = []
      Object.entries(matrix).forEach(([tierId, priceMap]) => {
        Object.entries(priceMap).forEach(([type, price]) => {
          if (price === '') return
          payload.push({
            tier_id: Number(tierId),
            type,
            price: Number(price),
          })
        })
      })
      await onSave(variant.variant_id, payload)
      setHasChanges(false)
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failed)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-hidden rounded-[8px] p-0 shadow-xl sm:max-w-4xl'>
        <DialogHeader className='border-b px-6 py-5'>
          <DialogTitle className='text-xl'>{m.title}</DialogTitle>
          <DialogDescription>
            {variant?.variant_id || m.noVariant}
            {readOnly ? ` • ${m.readOnly}` : ''}
          </DialogDescription>
        </DialogHeader>

        <div className='overflow-y-auto px-6 py-5'>
          {(['production', 'shipping'] as const).map((section) => (
            <div key={section} className='mb-8 last:mb-0'>
              <h3 className='mb-3 text-base font-semibold'>
                {section === 'production' ? m.production : m.shipping}
              </h3>
              <div className='overflow-x-auto rounded-[6px] border'>
                <table className='w-full text-sm'>
                  <thead className='bg-muted/30'>
                    <tr className='border-b'>
                      <th className='w-[160px] px-4 py-2.5 text-left text-[13px] font-medium'>{m.type}</th>
                      {tiers.map((tier) => (
                        <th key={tier.id} className='px-3 py-2.5 text-center text-[13px] font-medium'>
                          {tier.name}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {PRICE_TYPES[section].map((row) => (
                      <tr key={row.key} className='border-b last:border-b-0'>
                        <td className='px-4 py-2 text-[13px] font-medium'>{row.label}</td>
                        {tiers.map((tier) => (
                          <td key={tier.id} className='px-3 py-2'>
                            <Input
                              className='h-9 rounded-[6px] text-center text-[13px]'
                              type='number'
                              step='0.01'
                              min={0}
                              readOnly={readOnly}
                              value={matrix[String(tier.id)]?.[row.key] || ''}
                              onChange={(e) => setValue(tier.id, row.key, e.target.value)}
                              placeholder='0.00'
                            />
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ))}
        </div>

        <DialogFooter className='border-t px-6 py-4'>
          <Button
            type='button'
            variant='outline'
            className='rounded-[6px]'
            onClick={() => onOpenChange(false)}
          >
            {readOnly ? m.close : m.cancel}
          </Button>
          {!readOnly ? (
            <Button
              type='button'
              className='rounded-[6px]'
              onClick={handleSave}
              disabled={saving || !hasChanges}
            >
              {saving ? (
                <>
                  <Loader2 className='size-4 animate-spin' />
                  {m.saving}
                </>
              ) : (
                m.save
              )}
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

'use client'

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

export function PayrollHelpDialog({
  open,
  onOpenChange,
  title,
  steps,
  closeLabel,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  closeLabel: string
  steps: Array<{ icon: string; title: string; desc: string }>
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='rounded-[6px] sm:max-w-2xl'>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        <div className='grid gap-4'>
          {steps.map((step, index) => (
            <div key={`${step.title}-${index}`} className='flex gap-4 rounded-[6px] border p-4'>
              <div className='flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl'>
                {step.icon}
              </div>
              <div className='space-y-1'>
                <h3 className='font-semibold'>{step.title}</h3>
                <p className='text-sm text-muted-foreground'>{step.desc}</p>
              </div>
            </div>
          ))}
        </div>

        <DialogFooter>
          <Button className='h-11 rounded-[6px]' onClick={() => onOpenChange(false)}>
            {closeLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

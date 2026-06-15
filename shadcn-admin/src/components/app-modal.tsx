'use client'

import { X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

type AppModalProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  children: React.ReactNode
  footer?: React.ReactNode
  headerContent?: React.ReactNode
  contentClassName?: string
  bodyClassName?: string
  headerClassName?: string
}

export function AppModal({
  open,
  onOpenChange,
  title,
  children,
  footer,
  headerContent,
  contentClassName,
  bodyClassName,
  headerClassName,
}: AppModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        showCloseButton={false}
        className={cn(
          'max-h-[calc(100vh-3rem)] max-w-[calc(100%-2rem)] overflow-hidden rounded-2xl p-0 sm:max-w-4xl',
          contentClassName
        )}
      >
        <DialogHeader
          className={cn('border-b px-8 py-6 text-left', headerClassName)}
        >
          {headerContent ? (
            <>
              <DialogTitle className='sr-only'>{title}</DialogTitle>
              {headerContent}
            </>
          ) : (
            <DialogTitle className='text-2xl font-bold tracking-tight'>
              {title}
            </DialogTitle>
          )}
          <Button
            type='button'
            variant='ghost'
            size='icon'
            onClick={() => onOpenChange(false)}
            className='absolute right-6 top-5 rounded-full text-muted-foreground hover:text-foreground'
          >
            <X className='size-6' />
          </Button>
        </DialogHeader>

        <div className={cn('overflow-y-auto px-8 py-6', bodyClassName)}>{children}</div>

        {footer ? (
          <DialogFooter className='border-t px-8 py-5 sm:justify-end'>
            {footer}
          </DialogFooter>
        ) : null}
      </DialogContent>
    </Dialog>
  )
}

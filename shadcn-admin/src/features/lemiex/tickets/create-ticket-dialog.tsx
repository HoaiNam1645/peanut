'use client'
/* eslint-disable @next/next/no-img-element */

import { useEffect, useMemo, useState } from 'react'
import { X } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { createTicket } from '@/services/tickets/api'

const fallbackMessages = {
  createTitle: 'Create Support Ticket',
  subject: 'Subject',
  subjectPlaceholder: 'Brief description of the issue',
  message: 'Message',
  messagePlaceholder: 'Describe the issue in detail...',
  attachFile: 'Attach File (Optional)',
  clickToUpload: 'Click to upload',
  fileHint: 'JPG, PNG, GIF, PDF (max 10MB)',
  cancel: 'Cancel',
  creating: 'Creating...',
  createNew: 'Create Ticket',
  subjectRequired: 'Subject is required',
  messageRequired: 'Message is required',
  orderIdMissing: 'Order ID is missing. Please try again.',
  fileSizeError: 'File size must be less than 10MB',
  fileTypeError: 'Only JPG, PNG, GIF, and PDF files are allowed',
  createFailed: 'Failed to create ticket. Please try again.',
}

const ALLOWED_TYPES = [
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'application/pdf',
]

export function CreateTicketDialog({
  open,
  orderId,
  messages = fallbackMessages,
  onOpenChange,
  onSuccess,
}: {
  open: boolean
  orderId?: string | number | null
  messages?: typeof fallbackMessages
  onOpenChange: (open: boolean) => void
  onSuccess: () => void
}) {
  const ui = useMemo(() => messages ?? fallbackMessages, [messages])
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [formData, setFormData] = useState({
    subject: '',
    message: '',
  })

  useEffect(() => {
    if (!open) {
      setFormData({ subject: '', message: '' })
      setErrors({})
      setSelectedFile(null)
      setPreviewUrl(null)
      setLoading(false)
    }
  }, [open])

  function handleFileChange(file?: File | null) {
    if (!file) return

    if (file.size > 10 * 1024 * 1024) {
      toast.error(ui.fileSizeError)
      return
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
      toast.error(ui.fileTypeError)
      return
    }

    setSelectedFile(file)

    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onloadend = () => {
        setPreviewUrl(typeof reader.result === 'string' ? reader.result : null)
      }
      reader.readAsDataURL(file)
    } else {
      setPreviewUrl(null)
    }
  }

  function removeFile() {
    setSelectedFile(null)
    setPreviewUrl(null)
  }

  function validateForm() {
    const nextErrors: Record<string, string> = {}
    if (!formData.subject.trim()) nextErrors.subject = ui.subjectRequired
    if (!formData.message.trim()) nextErrors.message = ui.messageRequired
    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (!validateForm()) return
    if (!orderId) {
      toast.error(ui.orderIdMissing)
      return
    }

    try {
      setLoading(true)
      const payload = new FormData()
      payload.append('order_id', String(orderId))
      payload.append('subject', formData.subject.trim())
      payload.append('message', formData.message.trim())
      if (selectedFile) payload.append('file', selectedFile)

      await createTicket(payload)
      onSuccess()
      onOpenChange(false)
    } catch (error) {
      const err = error as Error & { errors?: Record<string, string[]> }
      if (err.errors) {
        setErrors({
          subject: err.errors.subject?.[0] || '',
          message: err.errors.message?.[0] || '',
          file: err.errors.file?.[0] || '',
        })
      }
      toast.error(err.message || ui.createFailed)
    } finally {
      setLoading(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='rounded-[6px] sm:max-w-2xl'>
        <DialogHeader>
          <DialogTitle>{ui.createTitle}</DialogTitle>
        </DialogHeader>

        <form className='space-y-5' onSubmit={handleSubmit}>
          <div className='space-y-2'>
            <Label>{ui.subject}</Label>
            <Input
              className='h-11 rounded-[6px]'
              placeholder={ui.subjectPlaceholder}
              value={formData.subject}
              onChange={(event) => {
                setFormData((prev) => ({ ...prev, subject: event.target.value }))
                if (errors.subject) setErrors((prev) => ({ ...prev, subject: '' }))
              }}
              disabled={loading}
            />
            {errors.subject ? <p className='text-xs text-destructive'>{errors.subject}</p> : null}
          </div>

          <div className='space-y-2'>
            <Label>{ui.message}</Label>
            <textarea
              className='min-h-[140px] w-full rounded-[6px] border bg-background px-3 py-2 text-sm'
              placeholder={ui.messagePlaceholder}
              value={formData.message}
              onChange={(event) => {
                setFormData((prev) => ({ ...prev, message: event.target.value }))
                if (errors.message) setErrors((prev) => ({ ...prev, message: '' }))
              }}
              disabled={loading}
            />
            {errors.message ? <p className='text-xs text-destructive'>{errors.message}</p> : null}
          </div>

          <div className='space-y-2'>
            <Label>{ui.attachFile}</Label>
            {!selectedFile ? (
              <label className='flex min-h-[120px] cursor-pointer flex-col items-center justify-center gap-2 rounded-[6px] border border-dashed bg-muted/20 p-6 text-center'>
                <span className='text-sm font-medium'>{ui.clickToUpload}</span>
                <span className='text-xs text-muted-foreground'>{ui.fileHint}</span>
                <input
                  type='file'
                  className='hidden'
                  accept='image/jpeg,image/jpg,image/png,image/gif,application/pdf'
                  onChange={(event) => handleFileChange(event.target.files?.[0] || null)}
                  disabled={loading}
                />
              </label>
            ) : (
              <div className='flex items-start gap-3 rounded-[6px] border p-3'>
                {previewUrl ? (
                  <img
                    src={previewUrl}
                    alt='Preview'
                    className='size-16 rounded-[6px] object-cover'
                  />
                ) : (
                  <div className='flex size-16 items-center justify-center rounded-[6px] bg-muted text-xl'>
                    PDF
                  </div>
                )}
                <div className='min-w-0 flex-1'>
                  <p className='truncate text-sm font-medium'>{selectedFile.name}</p>
                  <p className='text-xs text-muted-foreground'>
                    {(selectedFile.size / 1024).toFixed(2)} KB
                  </p>
                </div>
                <Button
                  type='button'
                  variant='outline'
                  size='icon'
                  className='rounded-[6px]'
                  onClick={removeFile}
                  disabled={loading}
                >
                  <X className='size-4' />
                </Button>
              </div>
            )}
            {errors.file ? <p className='text-xs text-destructive'>{errors.file}</p> : null}
          </div>

          <DialogFooter>
            <Button
              type='button'
              variant='outline'
              className='h-11 rounded-[6px]'
              onClick={() => onOpenChange(false)}
              disabled={loading}
            >
              {ui.cancel}
            </Button>
            <Button type='submit' className='h-11 rounded-[6px]' disabled={loading}>
              {loading ? ui.creating : ui.createNew}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

'use client'

import { useRef, useState } from 'react'
import { LoaderCircle, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { apiRequest } from '@/lib/client'
import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { useI18n } from '@/context/i18n-provider'

type LemiexFileUploadInputProps = {
  value?: string
  onChange: (value: string) => void
  type?: 'mockup' | 'design'
  placeholder?: string
  accept?: string
  required?: boolean
  hint?: string
  showPreview?: boolean
  orderId?: number | string | null
  itemId?: number | string | null
  /** Position label (e.g. "front", "back"). Used to build meta_key sent to backend. */
  position?: string | null
}

type UploadResponse = {
  success?: boolean
  status?: boolean
  message?: string
  data?: {
    url?: string | null
    filename?: string | null
  } | null
}

function resolvePreviewUrl(url?: string) {
  if (!url) return ''
  if (url.startsWith('//')) return `https:${url}`
  return url
}

export function LemiexFileUploadInput({
  value = '',
  onChange,
  type = 'mockup',
  placeholder = 'https://example.com/file',
  accept,
  required = false,
  hint,
  showPreview = false,
  orderId,
  itemId,
  position,
}: LemiexFileUploadInputProps) {
  const { messages } = useI18n()
  const uploadMessages = messages.orders.createForm.upload
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)

  const defaultAccept =
    type === 'mockup'
      ? 'image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp'
      : '.pes,.emb,.dst,.pdf'

  const handleFileSelect = async (
    event: React.ChangeEvent<HTMLInputElement>
  ) => {
    const file = event.target.files?.[0]
    if (!file) return

    setError('')
    setUploading(true)

    try {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', type)
      if (orderId) {
        formData.append('order_id', String(orderId))
      }
      if (itemId) {
        formData.append('item_id', String(itemId))
      }
      if (position) {
        // Backend expects meta_key like "front_pdf", "back_pdf" for design uploads
        const raw = String(position).trim().toLowerCase()
        const metaKey = raw.endsWith('_pdf') ? raw : `${raw}_pdf`
        formData.append('meta_key', metaKey)
      }

      const response = await apiRequest<UploadResponse>(
        `${API_BASE_URL}${API_ENDPOINTS.ORDER_UPLOAD_FILE}`,
        {
          method: 'POST',
          body: formData,
        }
      )

      if ((response.success ?? response.status) && response.data?.url) {
        onChange(response.data.url)
      } else {
        setError(response.message || uploadMessages.uploadFailed)
      }
    } catch (uploadError) {
      setError(
        uploadError instanceof Error ? uploadError.message : uploadMessages.uploadFailed
      )
    } finally {
      setUploading(false)
      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
    }
  }

  return (
    <div className='min-w-0 space-y-2'>
      <div className='grid min-w-0 gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start'>
        <Input
          type='url'
          value={value}
          onChange={(event) => {
            onChange(event.target.value)
            setError('')
          }}
          placeholder={placeholder}
          required={required}
          disabled={uploading}
          className='min-w-0 h-9 rounded-[6px] text-[13px]'
        />
        <Button
          type='button'
          variant='outline'
          className='h-9 w-full shrink-0 rounded-[6px] px-3 text-[13px] sm:w-[112px]'
          onClick={() => fileInputRef.current?.click()}
          disabled={uploading}
        >
          {uploading ? (
            <>
              <LoaderCircle className='h-3.5 w-3.5 animate-spin' />
              {uploadMessages.uploading}
            </>
          ) : (
            <>
              <Upload className='h-3.5 w-3.5' />
              {uploadMessages.upload}
            </>
          )}
        </Button>

        <input
          ref={fileInputRef}
          type='file'
          accept={accept || defaultAccept}
          onChange={handleFileSelect}
          className='hidden'
        />
      </div>

      {error ? (
        <p className='text-[12px] text-destructive'>{error}</p>
      ) : hint ? (
        <p className='text-[12px] text-muted-foreground'>{hint}</p>
      ) : null}

      {showPreview && value && type === 'mockup' ? (
        <div className='max-w-[150px] overflow-hidden rounded-[6px] border border-border/80 bg-background'>
          <img
            src={resolvePreviewUrl(value)}
            alt={uploadMessages.previewAlt}
            className='h-auto w-full object-cover'
            loading='lazy'
          />
        </div>
      ) : null}
    </div>
  )
}

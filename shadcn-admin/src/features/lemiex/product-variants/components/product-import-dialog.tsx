'use client'

import { useMemo, useRef, useState } from 'react'
import { toast } from 'sonner'
import { Download, FileSpreadsheet, Loader2, Upload } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import type { ProductImportPreview, ProductImportResult } from '@/services/products/api'
import {
  downloadCurrentProductImportData,
  downloadProductImportTemplate,
  importProductsFromCsv,
  previewProductImport,
} from '@/services/products/api'
import { useI18n } from '@/context/i18n-provider'

type ProductImportDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess: () => Promise<void> | void
}

type ImportStep = 'upload' | 'preview' | 'done'

function normalizeErrors(errors: unknown): string[] {
  if (!errors) return []
  if (Array.isArray(errors)) return errors.map(String)
  if (typeof errors === 'object') {
    const maybeApiError = errors as {
      message?: string
      errors?: string[] | Record<string, string[]>
    }

    if (maybeApiError.errors) {
      return normalizeErrors(maybeApiError.errors)
    }

    if (maybeApiError.message) {
      return [maybeApiError.message]
    }

    return Object.values(errors as Record<string, unknown>)
      .flat()
      .map(String)
  }
  return [String(errors)]
}

function triggerBlobDownload(blob: Blob, filename: string) {
  const url = window.URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  window.URL.revokeObjectURL(url)
  document.body.removeChild(anchor)
}

export function ProductImportDialog({
  open,
  onOpenChange,
  onSuccess,
}: ProductImportDialogProps) {
  const { messages } = useI18n()
  const productMessages = messages.productVariants
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  const [file, setFile] = useState<File | null>(null)
  const [loading, setLoading] = useState(false)
  const [step, setStep] = useState<ImportStep>('upload')
  const [preview, setPreview] = useState<ProductImportPreview | null>(null)
  const [errors, setErrors] = useState<string[]>([])
  const [importResult, setImportResult] = useState<ProductImportResult | null>(
    null
  )

  const canPreview = Boolean(file) && !loading
  const canImport = Boolean(file) && !loading

  function handleClose(nextOpen: boolean) {
    if (!nextOpen) {
      setFile(null)
      setLoading(false)
      setStep('upload')
      setPreview(null)
      setErrors([])
      setImportResult(null)
      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
    }

    onOpenChange(nextOpen)
  }

  async function handleDownloadTemplate() {
    setLoading(true)
    try {
      const blob = await downloadProductImportTemplate()
      triggerBlobDownload(blob, 'product_import_template.csv')
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Download failed')
    } finally {
      setLoading(false)
    }
  }

  async function handleDownloadCurrentData() {
    setLoading(true)
    try {
      const blob = await downloadCurrentProductImportData()
      triggerBlobDownload(blob, 'product_import_export.csv')
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Download failed')
    } finally {
      setLoading(false)
    }
  }

  async function handlePreview() {
    if (!file) return

    setLoading(true)
    setErrors([])

    try {
      const response = await previewProductImport(file)
      setPreview(response.data || null)
      setStep('preview')
    } catch (error) {
      setErrors(
        normalizeErrors(
          error instanceof Error ? error.message : productMessages.importDialog.previewFailed
        )
      )
    } finally {
      setLoading(false)
    }
  }

  async function handleImport() {
    if (!file) return

    setLoading(true)
    setErrors([])

    try {
      const response = await importProductsFromCsv(file)
      setImportResult(response.data || null)
      setStep('done')
      toast.success(productMessages.importDialog.importSuccess)
      await onSuccess()
    } catch (error) {
      setErrors(
        normalizeErrors(
          error instanceof Error ? error.message : productMessages.importDialog.importFailed
        )
      )
      setStep('preview')
    } finally {
      setLoading(false)
    }
  }

  const fileSizeLabel = useMemo(() => {
    if (!file) return ''
    return `${(file.size / 1024).toFixed(1)} KB`
  }, [file])

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className='sm:max-w-[760px]'>
        <DialogHeader>
          <DialogTitle>{productMessages.importDialog.title}</DialogTitle>
          <DialogDescription>
            {productMessages.importDialog.description}
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-5'>
          {step === 'upload' ? (
            <>
              <div className='flex flex-wrap gap-3'>
                <Button
                  type='button'
                  variant='outline'
                  className='rounded-[6px]'
                  disabled={loading}
                  onClick={handleDownloadTemplate}
                >
                  <Download className='size-4' />
                  {productMessages.importDialog.downloadTemplate}
                </Button>
                <Button
                  type='button'
                  variant='outline'
                  className='rounded-[6px]'
                  disabled={loading}
                  onClick={handleDownloadCurrentData}
                >
                  <Download className='size-4' />
                  {productMessages.importDialog.downloadCurrentData}
                </Button>
              </div>

              <label className='block cursor-pointer rounded-[6px] border border-dashed p-6 text-center'>
                <input
                  ref={fileInputRef}
                  type='file'
                  accept='.csv'
                  className='hidden'
                  onChange={(event) => {
                    const selected = event.target.files?.[0]
                    if (!selected) return
                    if (!selected.name.toLowerCase().endsWith('.csv')) {
                      toast.error(productMessages.importDialog.selectCsvFile)
                      return
                    }
                    setFile(selected)
                    setErrors([])
                    setPreview(null)
                  }}
                />
                <div className='mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-muted'>
                  <FileSpreadsheet className='size-6 text-muted-foreground' />
                </div>
                <p className='font-medium'>
                  {file
                    ? file.name
                    : productMessages.importDialog.clickToSelect}
                </p>
                <p className='mt-1 text-sm text-muted-foreground'>
                  {file
                    ? fileSizeLabel
                    : productMessages.importDialog.orDragDrop}
                </p>
              </label>
            </>
          ) : null}

          {step === 'preview' && preview ? (
            <div className='space-y-4'>
              <div className='grid gap-3 sm:grid-cols-2 xl:grid-cols-4'>
                <div className='rounded-[6px] border p-4'>
                  <div className='text-2xl font-semibold'>
                    {preview.total_products || 0}
                  </div>
                  <div className='text-sm text-muted-foreground'>
                    {productMessages.importDialog.products}
                  </div>
                </div>
                <div className='rounded-[6px] border p-4'>
                  <div className='text-2xl font-semibold'>
                    {preview.total_variants || 0}
                  </div>
                  <div className='text-sm text-muted-foreground'>
                    {productMessages.columns.variants}
                  </div>
                </div>
                <div className='rounded-[6px] border p-4'>
                  <div className='text-2xl font-semibold text-emerald-600'>
                    {preview.new_products || 0}
                  </div>
                  <div className='text-sm text-muted-foreground'>
                    {productMessages.importDialog.newProducts}
                  </div>
                </div>
                <div className='rounded-[6px] border p-4'>
                  <div className='text-2xl font-semibold text-amber-600'>
                    {preview.existing_products || 0}
                  </div>
                  <div className='text-sm text-muted-foreground'>
                    {productMessages.importDialog.existingProducts}
                  </div>
                </div>
              </div>

              <div className='max-h-[280px] space-y-2 overflow-y-auto rounded-[6px] border p-3'>
                {(preview.products || []).map((product, index) => (
                  <div
                    key={`${product.name || 'product'}-${index}`}
                    className='flex items-center justify-between rounded-[6px] border p-3'
                  >
                    <div>
                      <div className='font-medium'>{product.name}</div>
                      <div className='text-sm text-muted-foreground'>
                        {[product.style, product.brand].filter(Boolean).join(' • ')}
                      </div>
                    </div>
                    <div className='text-right'>
                      <div className='text-sm font-medium'>
                        {product.variants_count || 0} {productMessages.columns.variants}
                      </div>
                      <div className='text-xs text-muted-foreground'>
                        {product.is_new
                          ? productMessages.importDialog.newTag
                          : productMessages.importDialog.updateTag}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {step === 'done' && importResult ? (
            <div className='grid gap-3 sm:grid-cols-2'>
              <div className='rounded-[6px] border p-4'>
                <div className='text-2xl font-semibold text-emerald-600'>
                  {importResult.imported || 0}
                </div>
                <div className='text-sm text-muted-foreground'>
                  {productMessages.importDialog.imported}
                </div>
              </div>
              <div className='rounded-[6px] border p-4'>
                <div className='text-2xl font-semibold text-rose-600'>
                  {importResult.failed || 0}
                </div>
                <div className='text-sm text-muted-foreground'>
                  {productMessages.importDialog.failed}
                </div>
              </div>
            </div>
          ) : null}

          {errors.length ? (
            <div className='rounded-[6px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700'>
              <div className='mb-2 font-medium'>
                {productMessages.importDialog.errors}
              </div>
              <ul className='space-y-1'>
                {errors.slice(0, 8).map((error, index) => (
                  <li key={`${error}-${index}`}>• {error}</li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>

        <DialogFooter>
          <Button type='button' variant='outline' onClick={() => handleClose(false)}>
            {messages.profile.cancel}
          </Button>

          {step === 'upload' ? (
            <Button type='button' disabled={!canPreview} onClick={handlePreview}>
              {loading ? <Loader2 className='size-4 animate-spin' /> : <Upload className='size-4' />}
              {productMessages.importDialog.preview}
            </Button>
          ) : null}

          {step === 'preview' ? (
            <Button type='button' disabled={!canImport} onClick={handleImport}>
              {loading ? <Loader2 className='size-4 animate-spin' /> : <Upload className='size-4' />}
              {productMessages.importDialog.import}
            </Button>
          ) : null}

          {step === 'done' ? (
            <Button type='button' onClick={() => handleClose(false)}>
              {productMessages.importDialog.done}
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

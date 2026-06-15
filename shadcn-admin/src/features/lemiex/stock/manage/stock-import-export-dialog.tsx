'use client'

import { useEffect, useMemo, useRef, useState } from 'react'
import {
  Download,
  FileSpreadsheet,
  FileUp,
  Loader2,
  Upload,
} from 'lucide-react'
import { toast } from 'sonner'
import {
  exportStock,
  importStock,
  type ImportStockResult,
} from '@/services/stock/api'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

type StockImportExportMessages = {
  title: string
  import: string
  export: string
  importInstructions: string
  instructionFile: string
  instructionId: string
  instructionFields: string
  instructionUpdate: string
  stockOperationType: string
  setStock: string
  addStock: string
  subtractStock: string
  hintSet: string
  hintAdd: string
  hintSubtract: string
  selectCsvFile: string
  chooseFile: string
  downloadTemplate: string
  skuImport: string
  variantImport: string
  fullImport: string
  skuTemplateHint: string
  variantTemplateHint: string
  fullTemplateHint: string
  importing: string
  importBtn: string
  importResults: string
  success: string
  failed: string
  errors: string
  moreErrors: string
  exportStockData: string
  exportDesc: string
  exportFields1: string
  exportFields2: string
  exportFields3: string
  exportFields4: string
  exportPreview1: string
  exportPreview2: string
  exporting: string
  exportToCsv: string
  pleaseSelectCsv: string
  pleaseSelectFile: string
  importSuccess: string
  importFailed: string
  failedToImport: string
  exportSuccess: string
  exportFailed: string
  failedToExport: string
}

function downloadTemplate(templateType: 'sku' | 'variant' | 'full') {
  let csvContent = ''
  let fileName = ''

  switch (templateType) {
    case 'sku':
      csvContent = 'SKU,Stock\nABC123,100\nDEF456,50\nGHI789,75'
      fileName = 'stock_import_sku_template.csv'
      break
    case 'variant':
      csvContent = 'Variant ID,Stock\n12345,100\n67890,50\n11223,75'
      fileName = 'stock_import_variant_template.csv'
      break
    default:
      csvContent =
        'Variant ID,SKU,Stock,Product,Style,Color,Size\n12345,ABC123,100,Product Name,T-Shirt,Red,M\n67890,DEF456,50,Product Name 2,Hoodie,Blue,L\n11223,GHI789,75,Product Name 3,Polo,Green,XL'
      fileName = 'stock_import_full_template.csv'
  }

  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  link.click()
  window.URL.revokeObjectURL(url)
}

export function StockImportExportDialog({
  open,
  messages,
  onOpenChange,
  onImportSuccess,
}: {
  open: boolean
  messages: StockImportExportMessages
  onOpenChange: (open: boolean) => void
  onImportSuccess: () => void
}) {
  const [activeTab, setActiveTab] = useState<'import' | 'export'>('import')
  const [file, setFile] = useState<File | null>(null)
  const [stockType, setStockType] = useState<
    'set' | 'add_stock' | 'subtract_stock'
  >('set')
  const [submitting, setSubmitting] = useState(false)
  const [importResult, setImportResult] = useState<ImportStockResult | null>(
    null
  )
  const resetTimerRef = useRef<number | null>(null)

  const currentHint = useMemo(() => {
    if (stockType === 'add_stock') return messages.hintAdd
    if (stockType === 'subtract_stock') return messages.hintSubtract
    return messages.hintSet
  }, [messages.hintAdd, messages.hintSet, messages.hintSubtract, stockType])

  useEffect(() => {
    return () => {
      if (resetTimerRef.current) {
        window.clearTimeout(resetTimerRef.current)
      }
    }
  }, [])

  function resetState() {
    if (resetTimerRef.current) {
      window.clearTimeout(resetTimerRef.current)
      resetTimerRef.current = null
    }

    setActiveTab('import')
    setFile(null)
    setStockType('set')
    setSubmitting(false)
    setImportResult(null)
  }

  function handleClose(nextOpen: boolean) {
    if (!nextOpen) {
      resetState()
    }
    onOpenChange(nextOpen)
  }

  async function handleImport() {
    if (!file) {
      toast.error(messages.pleaseSelectFile)
      return
    }

    if (!file.name.endsWith('.csv')) {
      toast.error(messages.pleaseSelectCsv)
      return
    }

    setSubmitting(true)
    setImportResult(null)

    try {
      const result = await importStock(file, stockType)

      if (result.success) {
        setImportResult(result.data || null)
        toast.success(result.message || messages.importSuccess)
        resetTimerRef.current = window.setTimeout(() => {
          setFile(null)
          setStockType('set')
          onImportSuccess()
        }, 3000)
      } else {
        setImportResult(result.data || null)
        toast.error(result.message || messages.importFailed)
      }
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : messages.failedToImport
      )
    } finally {
      setSubmitting(false)
    }
  }

  async function handleExport() {
    setSubmitting(true)
    try {
      await exportStock()
      toast.success(messages.exportSuccess)
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : messages.failedToExport
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className='max-h-[90vh] max-w-4xl gap-0 overflow-hidden p-0'>
        <DialogHeader className='border-b px-6 py-5'>
          <DialogTitle className='text-[32px] leading-none font-semibold tracking-tight'>
            {messages.title}
          </DialogTitle>
          <DialogDescription className='pt-1 text-sm text-muted-foreground'>
            {activeTab === 'import'
              ? messages.importInstructions
              : messages.exportStockData}
          </DialogDescription>
        </DialogHeader>

        <Tabs
          value={activeTab}
          onValueChange={(value) => setActiveTab(value as 'import' | 'export')}
          className='flex max-h-[calc(90vh-7rem)] flex-col'
        >
          <div className='border-b px-6 py-4'>
            <TabsList className='grid h-12 w-full grid-cols-2 rounded-xl bg-muted/60 p-1'>
              <TabsTrigger value='import' className='rounded-[10px] text-base'>
                {messages.import}
              </TabsTrigger>
              <TabsTrigger value='export' className='rounded-[10px] text-base'>
                {messages.export}
              </TabsTrigger>
            </TabsList>
          </div>

          <div className='flex-1 overflow-y-auto px-6 py-5'>
            <TabsContent value='import' className='mt-0 space-y-5'>
              <Alert className='rounded-2xl border-border/80 bg-muted/20 p-5'>
                <FileSpreadsheet className='size-4' />
                <AlertTitle className='text-base'>
                  {messages.importInstructions}
                </AlertTitle>
                <AlertDescription className='mt-2 space-y-2 text-sm'>
                  <p>{messages.instructionFile}</p>
                  <p>{messages.instructionId}</p>
                  <p>{messages.instructionFields}</p>
                  <p>{messages.instructionUpdate}</p>
                </AlertDescription>
              </Alert>

              <div className='grid gap-5 lg:grid-cols-2'>
                <div className='space-y-2'>
                  <label className='text-sm font-medium'>
                    {messages.stockOperationType}
                  </label>
                  <Select
                    value={stockType}
                    onValueChange={(value) =>
                      setStockType(value as 'set' | 'add_stock' | 'subtract_stock')
                    }
                    disabled={submitting}
                  >
                    <SelectTrigger className='h-12'>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='set'>{messages.setStock}</SelectItem>
                      <SelectItem value='add_stock'>{messages.addStock}</SelectItem>
                      <SelectItem value='subtract_stock'>
                        {messages.subtractStock}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <p className='min-h-10 text-sm text-muted-foreground'>
                    {currentHint}
                  </p>
                </div>

                <div className='space-y-2'>
                  <label className='text-sm font-medium'>
                    {messages.selectCsvFile}
                  </label>
                  <div className='rounded-2xl border border-dashed border-border/80 bg-muted/10 p-4'>
                    <Input
                      id='stock-import-file'
                      type='file'
                      accept='.csv,text/csv'
                      className='hidden'
                      disabled={submitting}
                      onChange={(event) => {
                        const selected = event.target.files?.[0] || null
                        if (!selected) {
                          setFile(null)
                          return
                        }

                        if (
                          !selected.name.endsWith('.csv') &&
                          selected.type !== 'text/csv' &&
                          selected.type !== 'text/plain'
                        ) {
                          toast.error(messages.pleaseSelectCsv)
                          return
                        }

                        setFile(selected)
                        setImportResult(null)
                      }}
                    />
                    <label
                      htmlFor='stock-import-file'
                      className='flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border bg-background px-4 py-5 text-center'
                    >
                      <FileUp className='mb-3 size-5 text-muted-foreground' />
                      <div className='max-w-full truncate text-sm font-medium'>
                        {file?.name || messages.selectCsvFile}
                      </div>
                      <div className='mt-1 text-xs text-muted-foreground'>
                        {messages.chooseFile}
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <div className='space-y-3'>
                <div className='text-sm font-medium'>{messages.downloadTemplate}</div>
                <div className='grid gap-3 sm:grid-cols-3'>
                  <Button
                    type='button'
                    variant='outline'
                    className='h-12 justify-start rounded-xl'
                    onClick={() => downloadTemplate('sku')}
                    disabled={submitting}
                  >
                    <FileSpreadsheet className='mr-2 size-4' />
                    {messages.skuImport}
                  </Button>
                  <Button
                    type='button'
                    variant='outline'
                    className='h-12 justify-start rounded-xl'
                    onClick={() => downloadTemplate('variant')}
                    disabled={submitting}
                  >
                    <FileSpreadsheet className='mr-2 size-4' />
                    {messages.variantImport}
                  </Button>
                  <Button
                    type='button'
                    variant='outline'
                    className='h-12 justify-start rounded-xl'
                    onClick={() => downloadTemplate('full')}
                    disabled={submitting}
                  >
                    <FileSpreadsheet className='mr-2 size-4' />
                    {messages.fullImport}
                  </Button>
                </div>
              </div>

              {importResult ? (
                <div className='rounded-2xl border p-5'>
                  <div className='mb-4 text-sm font-medium'>
                    {messages.importResults}
                  </div>
                  <div className='grid gap-3 sm:grid-cols-2'>
                    <div className='rounded-xl bg-emerald-500/10 p-4 text-sm'>
                      <div className='text-muted-foreground'>{messages.success}</div>
                      <div className='mt-1 text-2xl font-semibold'>
                        {importResult.success_count || 0}
                      </div>
                    </div>
                    <div className='rounded-xl bg-rose-500/10 p-4 text-sm'>
                      <div className='text-muted-foreground'>{messages.failed}</div>
                      <div className='mt-1 text-2xl font-semibold'>
                        {importResult.failed_count || 0}
                      </div>
                    </div>
                  </div>

                  {importResult.errors?.length ? (
                    <div className='mt-4 space-y-2 text-sm'>
                      <div className='font-medium'>{messages.errors}</div>
                      <ul className='space-y-1 text-muted-foreground'>
                        {importResult.errors.slice(0, 10).map((error, index) => (
                          <li key={`${index}-${error}`}>{error}</li>
                        ))}
                        {importResult.errors.length > 10 ? (
                          <li>
                            {messages.moreErrors.replace(
                              '{count}',
                              String(importResult.errors.length - 10)
                            )}
                          </li>
                        ) : null}
                      </ul>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </TabsContent>

            <TabsContent value='export' className='mt-0 space-y-5'>
              <Alert className='rounded-2xl border-border/80 bg-muted/20 p-5'>
                <Download className='size-4' />
                <AlertTitle className='text-base'>
                  {messages.exportStockData}
                </AlertTitle>
                <AlertDescription className='mt-2 space-y-2 text-sm'>
                  <p>{messages.exportDesc}</p>
                  <p>{messages.exportFields1}</p>
                  <p>{messages.exportFields2}</p>
                  <p>{messages.exportFields3}</p>
                  <p>{messages.exportFields4}</p>
                </AlertDescription>
              </Alert>

              <div className='rounded-2xl border p-5 text-sm text-muted-foreground'>
                <p>{messages.exportPreview1}</p>
                <p className='mt-2'>{messages.exportPreview2}</p>
              </div>
            </TabsContent>
          </div>
        </Tabs>

        <DialogFooter className='border-t px-6 py-4'>
          {activeTab === 'import' ? (
            <Button
              className='min-w-36 rounded-xl'
              onClick={handleImport}
              disabled={!file || submitting}
            >
              {submitting ? (
                <>
                  <Loader2 className='mr-2 size-4 animate-spin' />
                  {messages.importing}
                </>
              ) : (
                <>
                  <Upload className='mr-2 size-4' />
                  {messages.importBtn}
                </>
              )}
            </Button>
          ) : (
            <Button
              className='min-w-36 rounded-xl'
              onClick={handleExport}
              disabled={submitting}
            >
              {submitting ? (
                <>
                  <Loader2 className='mr-2 size-4 animate-spin' />
                  {messages.exporting}
                </>
              ) : (
                <>
                  <Download className='mr-2 size-4' />
                  {messages.exportToCsv}
                </>
              )}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

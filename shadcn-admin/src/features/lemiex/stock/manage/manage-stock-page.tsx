'use client'

import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import {
  Box,
  Boxes,
  CheckCircle2,
  History,
  Loader2,
  Package,
  Pencil,
  Upload,
  X,
} from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { useI18n } from '@/context/i18n-provider'
import {
  bulkUpdateStockVariants,
  fetchStockFilterOptions,
  fetchStockList,
  fetchStockSummary,
  updateStockVariant,
  type StockFilters,
  type StockProduct,
  type StockSummary,
  type StockVariant,
} from '@/services/stock/api'
import { StockHistoryDialog } from './stock-history-dialog'
import { StockImportExportDialog } from './stock-import-export-dialog'

const EMPTY_FILTERS: StockFilters = {
  variant_id: '',
  sku: '',
  style: '',
  color: '',
  size: '',
  stock_level: '',
  active_status: '',
}

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'Stock Management',
  importExport: 'Import/Export',
  loading: 'Loading stock data...',
  loadError: 'Failed to load stock data',
  summary: {
    totalStock: 'Total Stock',
    reserved: 'Reserved',
    available: 'Available',
    lowStockItems: 'Low Stock Items',
  },
  filters: {
    variantId: 'Variant ID',
    sku: 'SKU',
    style: 'Style',
    color: 'Color',
    size: 'Size',
    stockLevel: 'Stock Level',
    status: 'Status',
    searchPlaceholder: 'Search...',
    allStyles: 'All Styles',
    allColors: 'All Colors',
    allSizes: 'All Sizes',
    all: 'All',
    lowStock: 'Low Stock (< 5)',
    outOfStock: 'Out of Stock',
    active: 'Active',
    inactive: 'Inactive',
    reset: 'Reset',
  },
  empty: {
    title: 'No products found',
    description: 'Try adjusting your filters',
  },
  tabs: {
    variants: 'variants',
  },
  bulk: {
    selected: '{count} variants selected',
    hint: 'Choose an operation to apply to all selected variants',
    clearSelection: 'Clear selection',
    operation: 'Operation',
    selectOperation: 'Select operation...',
    stockOperations: 'Stock Operations',
    statusOperations: 'Status Operations',
    addStock: 'Add to Current Stock',
    subtractStock: 'Subtract from Current Stock',
    setStock: 'Set Stock Level',
    activate: 'Activate',
    deactivate: 'Deactivate',
    amountToAdd: 'Amount to Add',
    amountToSubtract: 'Amount to Subtract',
    newStockLevel: 'New Stock Level',
    enterValue: 'Enter value...',
    reason: 'Reason (Optional)',
    reasonPlaceholder: 'e.g., New shipment arrived...',
    applyTo: 'Apply to {count} variant(s)',
    selectVariantsAndAction: 'Please select variants and action',
    enterValidStock: 'Please enter a valid stock value (0 or greater)',
    success: '{count} variants updated successfully',
  },
  table: {
    variantId: 'Variant ID',
    sku: 'SKU',
    style: 'Style',
    color: 'Color',
    size: 'Size',
    stock: 'Stock',
    reserved: 'Reserved',
    available: 'Available',
    active: 'Active',
    actions: 'Actions',
    save: 'Save',
    cancel: 'Cancel',
    edit: 'Edit',
    history: 'History',
    noVariants: 'No variants found for this product',
    stockCannotBeNegative: 'Stock cannot be negative',
    noChangesToSave: 'No changes to save',
    variantUpdated: 'Variant updated successfully',
    updateFailed: 'Failed to update variant',
    variantStatusUpdated: 'Variant status updated',
  },
  historyDialog: {
    title: 'Stock History',
    currentStock: 'Current Stock',
    loading: 'Loading history...',
    noRecords: 'No history records found',
    increase: 'Increase',
    decrease: 'Decrease',
    adjust: 'Adjust',
    import: 'Import',
    skuUpdated: 'SKU Updated',
    styleUpdated: 'Style Updated',
    activated: 'Activated',
    deactivated: 'Deactivated',
    bulkUpdate: 'Bulk Update',
    bulkOperation: 'Bulk Operation',
    operation: 'Operation',
    showingLast: 'Showing last 20 changes',
    sku: 'SKU',
    style: 'STYLE',
    active: 'ACTIVE',
    empty: '(empty)',
    variantId: 'Variant ID',
  },
  importExportDialog: {
    title: 'Stock Import/Export',
    import: 'Import',
    export: 'Export',
    importInstructions: 'Import Instructions:',
    instructionFile: 'File must be CSV format',
    instructionId: 'Required: At least one identifier (Variant ID or SKU)',
    instructionFields: 'Optional fields: Stock, Style, Color, Size, Product',
    instructionUpdate: 'Only fields present in CSV will be updated',
    stockOperationType: 'Stock Operation Type',
    setStock: 'Set Stock (Replace)',
    addStock: 'Add Stock (Increase)',
    subtractStock: 'Subtract Stock (Decrease)',
    hintSet: 'Replace current stock with values from file',
    hintAdd: 'Add values from file to current stock',
    hintSubtract: 'Subtract values from file from current stock',
    selectCsvFile: 'Select CSV File',
    chooseFile: 'Choose file...',
    downloadTemplate: 'Download Template',
    skuImport: 'SKU Import',
    variantImport: 'Variant Import',
    fullImport: 'Full Import',
    skuTemplateHint: 'Download SKU template (SKU, Stock)',
    variantTemplateHint: 'Download Variant template (Variant ID, Stock)',
    fullTemplateHint: 'Download Full template (All fields)',
    importing: 'Importing...',
    importBtn: 'Import',
    importResults: 'Import Results',
    success: 'Success:',
    failed: 'Failed:',
    errors: 'Errors:',
    moreErrors: '... and {count} more errors',
    exportStockData: 'Export Stock Data:',
    exportDesc: 'Export all stock data to CSV file including:',
    exportFields1: 'Variant ID, SKU, Product Name',
    exportFields2: 'Style, Color, Size',
    exportFields3: 'Stock, Reserved, Available',
    exportFields4: 'Status (Active/Inactive)',
    exportPreview1:
      'The export will include all variants with current stock information.',
    exportPreview2:
      'Export time depends on the number of variants in your inventory.',
    exporting: 'Exporting...',
    exportToCsv: 'Export to CSV',
    pleaseSelectCsv: 'Please select a CSV file',
    pleaseSelectFile: 'Please select a file',
    importSuccess: 'Import completed successfully',
    importFailed: 'Import failed',
    failedToImport: 'Failed to import stock',
    exportSuccess: 'Export completed successfully',
    exportFailed: 'Export failed',
    failedToExport: 'Failed to export stock',
  },
} as const

type EditableValues = {
  sku: string
  style: string
  stock: number
}

function formatMessage(template: string, values: Record<string, string | number>) {
  return Object.entries(values).reduce((acc, [key, value]) => {
    return acc.replace(`{${key}}`, String(value))
  }, template)
}

function getColorSwatch(color?: string | null) {
  const map: Record<string, string> = {
    black: '#000000',
    white: '#ffffff',
    gray: '#6b7280',
    grey: '#6b7280',
    navy: '#1e3a8a',
    blue: '#2563eb',
    red: '#dc2626',
    green: '#16a34a',
    brown: '#7c2d12',
  }

  return map[(color || '').toLowerCase()] || '#94a3b8'
}

function summaryCards(
  summary: StockSummary | null,
  messages: {
    totalStock: string
    reserved: string
    available: string
    lowStockItems: string
  }
) {
  return [
    {
      key: 'total-stock',
      title: messages.totalStock,
      value: summary?.total_stock || 0,
      icon: Boxes,
    },
    {
      key: 'reserved',
      title: messages.reserved,
      value: summary?.reserved || 0,
      icon: Package,
    },
    {
      key: 'available',
      title: messages.available,
      value: summary?.available || 0,
      icon: CheckCircle2,
    },
    {
      key: 'low-stock',
      title: messages.lowStockItems,
      value: summary?.low_stock_items || 0,
      icon: Box,
    },
  ]
}

export function LemiexManageStockPage() {
  const { messages } = useI18n()
  const m = messages.stock?.manage || fallbackMessages

  const [loading, setLoading] = useState(true)
  const [updating, setUpdating] = useState(false)
  const [summary, setSummary] = useState<StockSummary | null>(null)
  const [products, setProducts] = useState<StockProduct[]>([])
  const [filters, setFilters] = useState<StockFilters>(EMPTY_FILTERS)
  const [filterOptions, setFilterOptions] = useState<{
    styles: string[]
    colors: string[]
    sizes: string[]
  } | null>(null)
  const [selectedVariants, setSelectedVariants] = useState<number[]>([])
  const [activeProductId, setActiveProductId] = useState<number | null>(null)
  const [activeTabIndex, setActiveTabIndex] = useState(0)
  const [editingVariantId, setEditingVariantId] = useState<number | null>(null)
  const [editValues, setEditValues] = useState<EditableValues | null>(null)
  const [savingVariantId, setSavingVariantId] = useState<number | null>(null)
  const [historyVariant, setHistoryVariant] = useState<StockVariant | null>(null)
  const [bulkAction, setBulkAction] = useState('')
  const [bulkStockValue, setBulkStockValue] = useState('')
  const [bulkReason, setBulkReason] = useState('')
  const [importExportOpen, setImportExportOpen] = useState(false)
  const [tablePage, setTablePage] = useState(1)
  const [tablePageSize, setTablePageSize] = useState(10)

  useEffect(() => {
    let active = true

    async function loadInitialData() {
      setLoading(true)
      try {
        const options = await fetchStockFilterOptions()
        if (!active) return
        setFilterOptions(options)
      } catch (error) {
        toast.error(error instanceof Error ? error.message : m.loadError)
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadInitialData()

    return () => {
      active = false
    }
  }, [m.loadError])

  useEffect(() => {
    let active = true

    async function loadStock() {
      try {
        const next = await fetchStockList(filters)
        if (!active) return
        setProducts([...next])

        if (next.length > 0) {
          setActiveProductId((prev) => {
            if (prev !== null) return prev
            setActiveTabIndex(0)
            return next[0].id
          })
        }
      } catch (error) {
        toast.error(error instanceof Error ? error.message : m.loadError)
      }
    }

    void loadStock()

    return () => {
      active = false
    }
  }, [filters, m.loadError])

  useEffect(() => {
    let active = true

    async function loadSummary() {
      if (!activeProductId) return

      try {
        const next = await fetchStockSummary(activeProductId)
        if (!active) return
        setSummary(next)
      } catch {
        // Keep existing summary state.
      }
    }

    void loadSummary()

    return () => {
      active = false
    }
  }, [activeProductId])

  const activeProduct = products[activeTabIndex]
  const variants = useMemo(() => {
    return (activeProduct?.variants || []).slice().sort((a, b) => {
      const colorA = (a.color || '').toLowerCase()
      const colorB = (b.color || '').toLowerCase()
      return colorA.localeCompare(colorB)
    })
  }, [activeProduct])

  useEffect(() => {
    setTablePage(1)
  }, [activeProductId, filters])

  function patchVariantInProducts(
    variantId: number,
    updater: (variant: StockVariant) => StockVariant
  ) {
    setProducts((prevProducts) =>
      prevProducts.map((product) => ({
        ...product,
        variants: product.variants.map((variant) =>
          variant.id === variantId ? updater(variant) : variant
        ),
      }))
    )
  }

  function handleFilterChange(field: keyof StockFilters, value: string) {
    setFilters((prev) => ({ ...prev, [field]: value }))
  }

  function handleTabChange(productId: number, index: number) {
    setActiveProductId(productId)
    setActiveTabIndex(index)
    setSelectedVariants([])
  }

  function handleSelectAll() {
    if (selectedVariants.length === variants.length) {
      setSelectedVariants([])
      return
    }

    setSelectedVariants(variants.map((variant) => variant.id))
  }

  function handleSelectVariant(variantId: number) {
    setSelectedVariants((prev) =>
      prev.includes(variantId)
        ? prev.filter((id) => id !== variantId)
        : [...prev, variantId]
    )
  }

  function handleEdit(variant: StockVariant) {
    setEditingVariantId(variant.id)
    setEditValues({
      sku: variant.sku || '',
      style: variant.style || '',
      stock: variant.stock || 0,
    })
  }

  function handleCancelEdit() {
    setEditingVariantId(null)
    setEditValues(null)
  }

  async function refreshListAndSummary() {
    try {
      const [nextProducts, nextSummary] = await Promise.all([
        fetchStockList(filters),
        activeProductId ? fetchStockSummary(activeProductId) : Promise.resolve(null),
      ])

      setProducts([...nextProducts])
      setSummary(nextSummary)
    } catch {
      // Silent refresh failure.
    }
  }

  async function handleSave(variant: StockVariant) {
    if (!editValues) return

    if (editValues.stock < 0) {
      toast.warning(m.table.stockCannotBeNegative)
      return
    }

    const hasChanges =
      editValues.sku !== (variant.sku || '') ||
      editValues.style !== (variant.style || '') ||
      editValues.stock !== (variant.stock || 0)

    if (!hasChanges) {
      toast.info(m.table.noChangesToSave)
      setEditingVariantId(null)
      setEditValues(null)
      return
    }

    setSavingVariantId(variant.id)
    setUpdating(true)

    try {
      const updatedVariant = await updateStockVariant(variant.id, {
        sku: editValues.sku,
        style: editValues.style,
        stock: editValues.stock,
      })

      patchVariantInProducts(variant.id, (current) => ({
        ...current,
        ...updatedVariant,
      }))

      setEditingVariantId(null)
      setEditValues(null)
      toast.success(m.table.variantUpdated)
      void refreshListAndSummary()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.table.updateFailed)
    } finally {
      setSavingVariantId(null)
      setUpdating(false)
    }
  }

  async function handleToggleActive(variant: StockVariant) {
    setUpdating(true)
    try {
      const updatedVariant = await updateStockVariant(variant.id, {
        active: !variant.active,
      })

      patchVariantInProducts(variant.id, (current) => ({
        ...current,
        ...updatedVariant,
      }))

      toast.success(m.table.variantStatusUpdated)
      void refreshListAndSummary()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.table.updateFailed)
    } finally {
      setUpdating(false)
    }
  }

  async function handleBulkAction() {
    if (!bulkAction || selectedVariants.length === 0) {
      toast.warning(m.bulk.selectVariantsAndAction)
      return
    }

    const stockActions = ['add_stock', 'subtract_stock', 'set_stock']
    let stockValue: number | null = null

    if (stockActions.includes(bulkAction)) {
      const value = Number.parseInt(bulkStockValue, 10)
      if (Number.isNaN(value) || value < 0) {
        toast.warning(m.bulk.enterValidStock)
        return
      }
      stockValue = value
    }

    setUpdating(true)

    try {
      await bulkUpdateStockVariants({
        variantIds: selectedVariants,
        action: bulkAction,
        stockValue,
        reason: bulkReason || null,
      })

      toast.success(
        formatMessage(m.bulk.success, { count: selectedVariants.length })
      )

      setSelectedVariants([])
      setBulkAction('')
      setBulkStockValue('')
      setBulkReason('')

      await refreshListAndSummary()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.table.updateFailed)
    } finally {
      setUpdating(false)
    }
  }

  const selectAllState =
    variants.length > 0
      ? selectedVariants.length === variants.length
        ? true
        : selectedVariants.length > 0
          ? 'indeterminate'
          : false
      : false

  const pagedVariants = useMemo(() => {
    const start = (tablePage - 1) * tablePageSize
    return variants.slice(start, start + tablePageSize)
  }, [tablePage, tablePageSize, variants])

  const columns: ColumnDef<StockVariant>[] = [
      {
        id: 'select',
        header: () => (
          <Checkbox
            checked={selectAllState}
            onCheckedChange={handleSelectAll}
            aria-label='Select all variants'
          />
        ),
        cell: ({ row }) => (
          <Checkbox
            checked={selectedVariants.includes(row.original.id)}
            onCheckedChange={() => handleSelectVariant(row.original.id)}
            aria-label={`Select ${row.original.variant_id}`}
          />
        ),
        meta: {
          thClassName: 'w-12',
          tdClassName: 'w-12',
        },
      },
      {
        accessorKey: 'variant_id',
        header: m.table.variantId,
        cell: ({ row }) => (
          <code className='rounded-md bg-muted px-2 py-1 text-xs font-semibold'>
            {row.original.variant_id}
          </code>
        ),
      },
      {
        id: 'sku',
        header: m.table.sku,
        cell: ({ row }) =>
          editingVariantId === row.original.id && editValues ? (
            <Input
              value={editValues.sku}
              onChange={(event) =>
                setEditValues((prev) =>
                  prev ? { ...prev, sku: event.target.value } : prev
                )
              }
              className='h-10'
            />
          ) : (
            row.original.sku || '-'
          ),
      },
      {
        id: 'style',
        header: m.table.style,
        cell: ({ row }) =>
          editingVariantId === row.original.id && editValues ? (
            <Input
              value={editValues.style}
              onChange={(event) =>
                setEditValues((prev) =>
                  prev ? { ...prev, style: event.target.value } : prev
                )
              }
              className='h-10'
            />
          ) : (
            row.original.style || '-'
          ),
      },
      {
        id: 'color',
        header: m.table.color,
        cell: ({ row }) =>
          row.original.color ? (
            <div className='flex items-center gap-2'>
              <span
                className='size-3 rounded-full border'
                style={{ backgroundColor: getColorSwatch(row.original.color) }}
              />
              <span>{row.original.color}</span>
            </div>
          ) : (
            '-'
          ),
      },
      {
        id: 'size',
        header: m.table.size,
        cell: ({ row }) => (
          <Badge variant='outline'>{row.original.size || '-'}</Badge>
        ),
      },
      {
        id: 'stock',
        header: m.table.stock,
        cell: ({ row }) => {
          const isLowStock = (row.original.stock || 0) < 20
          const isOutOfStock = (row.original.stock || 0) === 0

          return editingVariantId === row.original.id && editValues ? (
            <Input
              type='number'
              min='0'
              className='mx-auto h-10 max-w-24'
              value={editValues.stock}
              onChange={(event) =>
                setEditValues((prev) =>
                  prev
                    ? {
                        ...prev,
                        stock:
                          Number.parseInt(event.target.value || '0', 10) || 0,
                      }
                    : prev
                )
              }
            />
          ) : (
            <span
              className={cn(
                'font-medium',
                isOutOfStock
                  ? 'text-rose-600'
                  : isLowStock
                    ? 'text-amber-600'
                    : 'text-emerald-600'
              )}
            >
              {row.original.stock || 0}
            </span>
          )
        },
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'reserved',
        header: m.table.reserved,
        cell: ({ row }) => row.original.reserved || 0,
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'available',
        header: m.table.available,
        cell: ({ row }) => {
          const available = row.original.available ?? 0
          return (
            <span
              className={cn(
                'font-medium',
                available <= 0 ? 'text-rose-600' : 'text-emerald-600'
              )}
            >
              {available}
            </span>
          )
        },
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'active',
        header: m.table.active,
        cell: ({ row }) => (
          <div className='flex justify-center'>
            <Switch
              checked={Boolean(row.original.active)}
              disabled={editingVariantId === row.original.id || updating}
              onCheckedChange={() => handleToggleActive(row.original)}
            />
          </div>
        ),
        meta: {
          thClassName: 'text-center',
          tdClassName: 'text-center',
        },
      },
      {
        id: 'actions',
        header: m.table.actions,
        cell: ({ row }) => {
          const isEditing = editingVariantId === row.original.id
          const isSaving = savingVariantId === row.original.id

          return (
            <div className='flex justify-end gap-2'>
              {isEditing ? (
                <>
                  <Button
                    type='button'
                    size='icon'
                    onClick={() => handleSave(row.original)}
                    disabled={isSaving}
                  >
                    {isSaving ? (
                      <Loader2 className='size-4 animate-spin' />
                    ) : (
                      <CheckCircle2 className='size-4' />
                    )}
                  </Button>
                  <Button
                    type='button'
                    variant='outline'
                    size='icon'
                    onClick={handleCancelEdit}
                    disabled={isSaving}
                  >
                    <X className='size-4' />
                  </Button>
                </>
              ) : (
                <>
                  <Button
                    type='button'
                    variant='outline'
                    size='icon'
                    onClick={() => handleEdit(row.original)}
                  >
                    <Pencil className='size-4' />
                  </Button>
                  <Button
                    type='button'
                    variant='outline'
                    size='icon'
                    onClick={() => setHistoryVariant(row.original)}
                  >
                    <History className='size-4' />
                  </Button>
                </>
              )}
            </div>
          )
        },
        meta: {
          thClassName: 'text-right',
          tdClassName: 'text-right',
        },
      },
    ]

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

      <Main
        fluid
        className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'
      >
        <div className='space-y-6'>
          <div className='flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between'>
            <div>
              <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            </div>

            <Button onClick={() => setImportExportOpen(true)}>
              <Upload className='mr-2 size-4' />
              {m.importExport}
            </Button>
          </div>

          <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
            {summaryCards(summary, m.summary).map((item) => {
              const Icon = item.icon

              return (
                <Card key={item.key} className='shadow-sm'>
                  <CardContent className='flex items-center gap-4 px-5 py-4'>
                    <div className='rounded-xl bg-primary/10 p-3 text-primary'>
                      <Icon className='size-5' />
                    </div>
                    <div className='min-w-0'>
                      <div className='text-sm text-muted-foreground'>
                        {item.title}
                      </div>
                      <div className='mt-1 text-2xl font-semibold tracking-tight'>
                        {item.value.toLocaleString()}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )
            })}
          </div>

          <Card className='shadow-sm'>
            <CardContent className='p-5'>
              <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-8'>
                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>
                    {m.filters.variantId}
                  </label>
                  <Input
                    className='h-10 w-full'
                    value={filters.variant_id}
                    placeholder={m.filters.searchPlaceholder}
                    onChange={(event) =>
                      handleFilterChange('variant_id', event.target.value)
                    }
                  />
                </div>

                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>{m.filters.sku}</label>
                  <Input
                    className='h-10 w-full'
                    value={filters.sku}
                    placeholder={m.filters.searchPlaceholder}
                    onChange={(event) =>
                      handleFilterChange('sku', event.target.value)
                    }
                  />
                </div>

                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>{m.filters.style}</label>
                  <Select
                    value={filters.style || ALL_VALUE}
                    onValueChange={(value) =>
                      handleFilterChange('style', value === ALL_VALUE ? '' : value)
                    }
                  >
                    <SelectTrigger className='h-10 w-full'>
                      <SelectValue placeholder={m.filters.allStyles} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={ALL_VALUE}>{m.filters.allStyles}</SelectItem>
                      {filterOptions?.styles.filter(Boolean).map((style) => (
                        <SelectItem key={style} value={style}>
                          {style}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>{m.filters.color}</label>
                  <Select
                    value={filters.color || ALL_VALUE}
                    onValueChange={(value) =>
                      handleFilterChange('color', value === ALL_VALUE ? '' : value)
                    }
                  >
                    <SelectTrigger className='h-10 w-full'>
                      <SelectValue placeholder={m.filters.allColors} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={ALL_VALUE}>{m.filters.allColors}</SelectItem>
                      {filterOptions?.colors.filter(Boolean).map((color) => (
                        <SelectItem key={color} value={color}>
                          {color}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>{m.filters.size}</label>
                  <Select
                    value={filters.size || ALL_VALUE}
                    onValueChange={(value) =>
                      handleFilterChange('size', value === ALL_VALUE ? '' : value)
                    }
                  >
                    <SelectTrigger className='h-10 w-full'>
                      <SelectValue placeholder={m.filters.allSizes} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={ALL_VALUE}>{m.filters.allSizes}</SelectItem>
                      {filterOptions?.sizes.filter(Boolean).map((size) => (
                        <SelectItem key={size} value={size}>
                          {size}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>
                    {m.filters.stockLevel}
                  </label>
                  <Select
                    value={filters.stock_level || ALL_VALUE}
                    onValueChange={(value) =>
                      handleFilterChange(
                        'stock_level',
                        value === ALL_VALUE ? '' : value
                      )
                    }
                  >
                    <SelectTrigger className='h-10 w-full'>
                      <SelectValue placeholder={m.filters.all} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={ALL_VALUE}>{m.filters.all}</SelectItem>
                      <SelectItem value='low'>{m.filters.lowStock}</SelectItem>
                      <SelectItem value='out'>{m.filters.outOfStock}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2 xl:col-span-1'>
                  <label className='text-sm font-medium'>{m.filters.status}</label>
                  <Select
                    value={filters.active_status || ALL_VALUE}
                    onValueChange={(value) =>
                      handleFilterChange(
                        'active_status',
                        value === ALL_VALUE ? '' : value
                      )
                    }
                  >
                    <SelectTrigger className='h-10 w-full'>
                      <SelectValue placeholder={m.filters.all} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={ALL_VALUE}>{m.filters.all}</SelectItem>
                      <SelectItem value='active'>{m.filters.active}</SelectItem>
                      <SelectItem value='inactive'>{m.filters.inactive}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

              </div>
            </CardContent>
          </Card>

          {loading ? (
            <Card className='shadow-sm'>
              <CardContent className='flex min-h-48 items-center justify-center text-sm text-muted-foreground'>
                <Loader2 className='mr-2 size-4 animate-spin' />
                {m.loading}
              </CardContent>
            </Card>
          ) : products.length === 0 ? (
            <Card className='shadow-sm'>
              <CardContent className='flex min-h-56 flex-col items-center justify-center text-center'>
                <Package className='mb-4 size-10 text-muted-foreground' />
                <h3 className='text-lg font-semibold'>{m.empty.title}</h3>
                <p className='mt-2 text-sm text-muted-foreground'>
                  {m.empty.description}
                </p>
              </CardContent>
            </Card>
          ) : (
            <>
              <div className='flex gap-3 overflow-x-auto pb-2'>
                {products.map((product, index) => {
                  const active = activeTabIndex === index
                  return (
                    <button
                      key={product.id}
                      type='button'
                      onClick={() => handleTabChange(product.id, index)}
                      className={cn(
                        'min-w-48 rounded-2xl border px-4 py-3 text-left transition-colors',
                        active
                          ? 'border-primary bg-primary text-primary-foreground'
                          : 'bg-card hover:bg-accent'
                      )}
                    >
                      <div className='font-medium'>{product.name}</div>
                      <div
                        className={cn(
                          'mt-1 text-sm',
                          active
                            ? 'text-primary-foreground/80'
                            : 'text-muted-foreground'
                        )}
                      >
                        {product.style || 'N/A'}
                      </div>
                      <div
                        className={cn(
                          'mt-2 text-xs',
                          active
                            ? 'text-primary-foreground/70'
                            : 'text-muted-foreground'
                        )}
                      >
                        {product.variants?.length || 0} {m.tabs.variants}
                      </div>
                    </button>
                  )
                })}
              </div>

              {selectedVariants.length > 0 ? (
                <Card className='border-primary/40 shadow-sm'>
                  <CardContent className='space-y-5 p-5'>
                    <div className='flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'>
                      <div className='flex gap-3'>
                        <div className='rounded-xl bg-primary/10 p-3 text-primary'>
                          <CheckCircle2 className='size-5' />
                        </div>
                        <div>
                          <div className='font-medium'>
                            {formatMessage(m.bulk.selected, {
                              count: selectedVariants.length,
                            })}
                          </div>
                          <div className='text-sm text-muted-foreground'>
                            {m.bulk.hint}
                          </div>
                        </div>
                      </div>

                      <Button
                        type='button'
                        variant='ghost'
                        size='icon'
                        onClick={() => setSelectedVariants([])}
                        title={m.bulk.clearSelection}
                      >
                        <X className='size-4' />
                      </Button>
                    </div>

                    <div className='grid gap-4 lg:grid-cols-[1.1fr_1fr_1.4fr_auto]'>
                      <div className='space-y-2'>
                        <label className='text-sm font-medium'>
                          {m.bulk.operation}
                        </label>
                        <Select
                          value={bulkAction || ALL_VALUE}
                          onValueChange={(value) => {
                            setBulkAction(value === ALL_VALUE ? '' : value)
                            setBulkStockValue('')
                          }}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder={m.bulk.selectOperation} />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                              {m.bulk.selectOperation}
                            </SelectItem>
                            <SelectItem value='add_stock'>
                              {m.bulk.addStock}
                            </SelectItem>
                            <SelectItem value='subtract_stock'>
                              {m.bulk.subtractStock}
                            </SelectItem>
                            <SelectItem value='set_stock'>
                              {m.bulk.setStock}
                            </SelectItem>
                            <SelectItem value='activate'>
                              {m.bulk.activate}
                            </SelectItem>
                            <SelectItem value='deactivate'>
                              {m.bulk.deactivate}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </div>

                      {['add_stock', 'subtract_stock', 'set_stock'].includes(
                        bulkAction
                      ) ? (
                        <div className='space-y-2'>
                          <label className='text-sm font-medium'>
                            {bulkAction === 'add_stock'
                              ? m.bulk.amountToAdd
                              : bulkAction === 'subtract_stock'
                                ? m.bulk.amountToSubtract
                                : m.bulk.newStockLevel}
                          </label>
                          <Input
                            type='number'
                            min='0'
                            value={bulkStockValue}
                            placeholder={m.bulk.enterValue}
                            onChange={(event) => setBulkStockValue(event.target.value)}
                          />
                        </div>
                      ) : null}

                      <div className='space-y-2'>
                        <label className='text-sm font-medium'>{m.bulk.reason}</label>
                        <Textarea
                          value={bulkReason}
                          placeholder={m.bulk.reasonPlaceholder}
                          onChange={(event) => setBulkReason(event.target.value)}
                          className='min-h-10'
                        />
                      </div>

                      <div className='flex items-end'>
                        <Button
                          type='button'
                          className='w-full lg:w-auto'
                          onClick={handleBulkAction}
                          disabled={!bulkAction || updating}
                        >
                          {updating ? (
                            <Loader2 className='mr-2 size-4 animate-spin' />
                          ) : null}
                          {formatMessage(m.bulk.applyTo, {
                            count: selectedVariants.length,
                          })}
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ) : null}

              <div className='relative z-10'>
                <LemiexDataTable
                  columns={columns}
                  data={pagedVariants}
                  page={tablePage}
                  pageSize={tablePageSize}
                  total={variants.length}
                  loading={false}
                  emptyText={m.table.noVariants}
                  onPageChange={setTablePage}
                  onPageSizeChange={(nextPageSize) => {
                    setTablePageSize(nextPageSize)
                    setTablePage(1)
                  }}
                  getRowId={(row) => String(row.id)}
                  className='min-h-0'
                />
              </div>
            </>
          )}
        </div>
      </Main>

      <StockHistoryDialog
        open={Boolean(historyVariant)}
        variant={historyVariant}
        messages={m.historyDialog}
        onOpenChange={(open) => {
          if (!open) {
            setHistoryVariant(null)
          }
        }}
      />

      <StockImportExportDialog
        open={importExportOpen}
        messages={m.importExportDialog}
        onOpenChange={setImportExportOpen}
        onImportSuccess={() => {
          void refreshListAndSummary()
        }}
      />
    </>
  )
}

'use client'

import {
  type ColumnDef,
  type PaginationState,
  flexRender,
  functionalUpdate,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { cn } from '@/lib/utils'
import { DataTablePagination } from '@/components/data-table'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

type TableColumnMeta = {
  className?: string
  thClassName?: string
  tdClassName?: string
}

type LemiexDataTableProps<TData> = {
  columns: ColumnDef<TData, unknown>[]
  data: TData[]
  page: number
  pageSize: number
  total: number
  loading?: boolean
  loadingText?: string
  className?: string
  emptyText?: string
  getRowId?: (originalRow: TData, index: number) => string
  onPageChange: (page: number) => void
  onPageSizeChange: (pageSize: number) => void
  pageSizeOptions?: number[]
  /** Where to render the pagination bar relative to the table. Defaults to 'bottom'. */
  paginationPosition?: 'top' | 'bottom'
}

export function LemiexDataTable<TData>({
  columns,
  data,
  page,
  pageSize,
  total,
  loading = false,
  loadingText = 'Loading...',
  className,
  emptyText = 'No results.',
  getRowId,
  onPageChange,
  onPageSizeChange,
  pageSizeOptions,
  paginationPosition = 'bottom',
}: LemiexDataTableProps<TData>) {
  const pagination: PaginationState = {
    pageIndex: Math.max(0, page - 1),
    pageSize,
  }

  const pageCount = Math.max(1, Math.ceil(total / pageSize))

  const table = useReactTable({
    data,
    columns,
    state: { pagination },
    manualPagination: true,
    pageCount,
    getCoreRowModel: getCoreRowModel(),
    getRowId,
    onPaginationChange: (updater) => {
      const next = functionalUpdate(updater, pagination)

      if (next.pageSize !== pagination.pageSize) {
        onPageSizeChange(next.pageSize)
      }

      if (next.pageIndex !== pagination.pageIndex) {
        onPageChange(next.pageIndex + 1)
      }
    },
  })

  return (
    <div className={cn('flex flex-1 flex-col gap-4', className)}>
      {paginationPosition === 'top' ? (
        <DataTablePagination
          table={table}
          className='pt-1'
          pageSizeOptions={pageSizeOptions}
        />
      ) : null}

      <div className='overflow-x-auto overflow-y-visible rounded-[6px] border bg-card'>
        <Table className='min-w-max'>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => {
                  const meta = header.column.columnDef.meta as
                    | TableColumnMeta
                    | undefined

                  return (
                    <TableHead
                      key={header.id}
                      colSpan={header.colSpan}
                      className={cn(
                        'whitespace-nowrap bg-muted/30',
                        meta?.className,
                        meta?.thClassName
                      )}
                    >
                      {header.isPlaceholder
                        ? null
                        : flexRender(
                            header.column.columnDef.header,
                            header.getContext()
                          )}
                    </TableHead>
                  )
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: Math.min(pageSize, 8) }).map((_, index) => (
                <TableRow key={`loading-${index}`}>
                  <TableCell
                    colSpan={columns.length}
                    className='h-14 animate-pulse text-sm text-muted-foreground'
                  >
                    {loadingText}
                  </TableCell>
                </TableRow>
              ))
            ) : table.getRowModel().rows.length > 0 ? (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id}>
                  {row.getVisibleCells().map((cell) => {
                    const meta = cell.column.columnDef.meta as
                      | TableColumnMeta
                      | undefined

                    return (
                      <TableCell
                        key={cell.id}
                        className={cn(
                          'align-middle',
                          meta?.className,
                          meta?.tdClassName
                        )}
                      >
                        {flexRender(
                          cell.column.columnDef.cell,
                          cell.getContext()
                        )}
                      </TableCell>
                    )
                  })}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className='h-24 text-center'>
                  {emptyText}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      {paginationPosition === 'bottom' ? (
        <DataTablePagination
          table={table}
          className='mt-auto pb-2'
          pageSizeOptions={pageSizeOptions}
        />
      ) : null}
    </div>
  )
}

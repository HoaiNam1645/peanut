'use client'

import * as React from 'react'
import { Check, ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

type LemiexMultiSelectFilterProps = {
  title: string
  value: string[]
  options: {
    label: string
    value: string
  }[]
  onChange: (value: string[]) => void
}

export function LemiexMultiSelectFilter({
  title,
  value,
  options,
  onChange,
}: LemiexMultiSelectFilterProps) {
  const selectedValues = new Set(value)

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          className='h-10 w-full justify-between border-dashed font-normal'
        >
          <span className='truncate'>{title}</span>
          <div className='ms-2 flex items-center gap-1'>
            {selectedValues.size > 0 && (
              <Badge variant='secondary' className='rounded-sm px-1.5'>
                {selectedValues.size}
              </Badge>
            )}
            <ChevronDown className='size-4 opacity-60' />
          </div>
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-[260px] p-0' align='start'>
        <Command>
          <CommandInput placeholder={title} />
          <CommandList>
            <CommandEmpty>No results found.</CommandEmpty>
            <CommandGroup>
              {options.map((option) => {
                const isSelected = selectedValues.has(option.value)

                return (
                  <CommandItem
                    key={option.value}
                    onSelect={() => {
                      const nextValues = new Set(selectedValues)

                      if (isSelected) {
                        nextValues.delete(option.value)
                      } else {
                        nextValues.add(option.value)
                      }

                      onChange(Array.from(nextValues))
                    }}
                  >
                    <div
                      className={cn(
                        'me-2 flex size-4 items-center justify-center rounded-sm border border-primary',
                        isSelected
                          ? 'bg-primary text-primary-foreground'
                          : 'opacity-50'
                      )}
                    >
                      <Check
                        className={cn(
                          'size-3',
                          isSelected ? 'opacity-100' : 'opacity-0'
                        )}
                      />
                    </div>
                    <span className='truncate'>{option.label}</span>
                  </CommandItem>
                )
              })}
            </CommandGroup>
            {value.length > 0 ? (
              <div className='border-t p-2'>
                <Button
                  type='button'
                  variant='ghost'
                  className='w-full justify-center'
                  onClick={() => onChange([])}
                >
                  Clear
                </Button>
              </div>
            ) : null}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

'use client'

import { createContext, useContext } from 'react'
import { Checkbox } from '@/components/ui/checkbox'

type OrdersSelectionContextValue = {
  selectedOrderIds: Array<number | string>
  currentOrderIds: Array<number | string>
  onToggleOrder: (orderId: number | string) => void
  onToggleAllOrders: (checked: boolean) => void
}

const OrdersSelectionContext = createContext<OrdersSelectionContextValue | null>(
  null
)

export function OrdersSelectionProvider({
  value,
  children,
}: {
  value: OrdersSelectionContextValue
  children: React.ReactNode
}) {
  return (
    <OrdersSelectionContext.Provider value={value}>
      {children}
    </OrdersSelectionContext.Provider>
  )
}

function useOrdersSelection() {
  const context = useContext(OrdersSelectionContext)

  if (!context) {
    throw new Error('OrdersSelectionContext is missing')
  }

  return context
}

export function SelectAllOrdersCheckbox() {
  const { currentOrderIds, selectedOrderIds, onToggleAllOrders } =
    useOrdersSelection()

  const allSelected =
    currentOrderIds.length > 0 &&
    currentOrderIds.every((id) => selectedOrderIds.includes(id))

  return (
    <Checkbox
      checked={allSelected}
      onCheckedChange={(checked) => onToggleAllOrders(checked === true)}
    />
  )
}

export function SelectOrderCheckbox({
  orderId,
}: {
  orderId: number | string
}) {
  const { selectedOrderIds, onToggleOrder } = useOrdersSelection()

  return (
    <Checkbox
      checked={selectedOrderIds.includes(orderId)}
      onCheckedChange={() => onToggleOrder(orderId)}
    />
  )
}

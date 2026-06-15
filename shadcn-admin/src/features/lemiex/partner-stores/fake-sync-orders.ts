'use client'

import type { PartnerStoreRecord } from '@/services/partner-stores/api'

const STORAGE_KEY = 'lemiex_partner_synced_orders_v1'

export type PartnerSyncedOrder = {
  id: string
  store_id: number
  store_name: string
  customer_name: string
  customer_address: string
  customer_phone: string
  user_name: string
  partner_order_no: string
  tracking_label: string
  item_name: string
  item_sku: string
  item_image: string
  quantity: number
  discount: number
  total: number
  status: 'Pending' | 'Paid' | 'Cancelled'
  fulfillment: 'No fulfillment' | 'Ready' | 'Shipped'
  note: string
  synced_at: string
}

type PartnerSyncedOrderStorage = {
  orders: PartnerSyncedOrder[]
}

function readStorage(): PartnerSyncedOrderStorage {
  if (typeof window === 'undefined') return { orders: [] }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) return { orders: [] }
    const parsed = JSON.parse(raw) as PartnerSyncedOrderStorage
    if (!Array.isArray(parsed.orders)) return { orders: [] }
    return { orders: parsed.orders }
  } catch {
    return { orders: [] }
  }
}

function writeStorage(storage: PartnerSyncedOrderStorage) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(storage))
}

function randomBetween(min: number, max: number) {
  return Math.floor(Math.random() * (max - min + 1)) + min
}

function pick<T>(items: T[]) {
  return items[Math.floor(Math.random() * items.length)]
}

function makeMaskedPhone() {
  return `(+1)${randomBetween(200, 999)}*****${randomBetween(10, 99)}`
}

function makeMaskedAddress() {
  return `${randomBetween(10, 99)}*** ******** **, ${pick([
    'Colorado',
    'Texas',
    'California',
    'Florida',
    'Nevada',
  ])}`
}

export function getPartnerSyncedOrders() {
  return readStorage().orders.sort((a, b) => (a.synced_at < b.synced_at ? 1 : -1))
}

export function getPartnerStoreSyncedOrderCount(storeId: number) {
  return getPartnerSyncedOrders().filter((order) => order.store_id === storeId).length
}

export function applySyncedOrderCounts(stores: PartnerStoreRecord[]) {
  return stores.map((store) => ({
    ...store,
    total_order: getPartnerStoreSyncedOrderCount(store.id),
  }))
}

export function createFakeOrdersForStore(store: PartnerStoreRecord) {
  const storage = readStorage()
  const existing = storage.orders.filter((order) => order.store_id === store.id)
  const batchSize = randomBetween(3, 6)
  const productCatalog = [
    {
      name: 'Embroidered Little Ray Pitch Black Tee',
      sku: 'SWT-BLACK-L',
      image:
        'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=120&q=80',
    },
    {
      name: 'Pressify Varsity Hoodie',
      sku: 'HD-GRY-M',
      image:
        'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?auto=format&fit=crop&w=120&q=80',
    },
    {
      name: 'Vintage Tote Bag Signature',
      sku: 'TOTE-CREAM-OS',
      image:
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=120&q=80',
    },
    {
      name: 'Crewneck Sweatshirt Ocean Blue',
      sku: 'SWT-BLUE-XL',
      image:
        'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=120&q=80',
    },
  ]
  const customers = [
    'Carri Trujillo',
    'Olivia Bennett',
    'Noah Foster',
    'Ethan Brooks',
    'Sofia Rivera',
    'Emma Collins',
  ]
  const statuses: PartnerSyncedOrder['status'][] = ['Pending', 'Paid', 'Cancelled']
  const fulfillments: PartnerSyncedOrder['fulfillment'][] = [
    'No fulfillment',
    'Ready',
    'Shipped',
  ]

  const newOrders = Array.from({ length: batchSize }, (_, index) => {
    const product = pick(productCatalog)
    const quantity = randomBetween(1, 3)
    const subtotal = randomBetween(18, 65)
    const discount = randomBetween(0, 12)
    const total = Math.max(0, subtotal - discount)
    const customer = pick(customers)
    const sequence = existing.length + index + 1

    return {
      id: `${store.id}-${Date.now()}-${sequence}`,
      store_id: store.id,
      store_name: store.name,
      customer_name: customer,
      customer_address: makeMaskedAddress(),
      customer_phone: makeMaskedPhone(),
      user_name: store.user?.username || 'usertest',
      partner_order_no: `${randomBetween(5100000000000000, 5999999999999999)}`,
      tracking_label: quantity > 1 ? 'Buy Labels' : 'Buy Label',
      item_name: product.name,
      item_sku: product.sku,
      item_image: product.image,
      quantity,
      discount,
      total,
      status: pick(statuses),
      fulfillment: pick(fulfillments),
      note: pick(['Rush sync', 'First import batch', 'Partner app test', 'Auto synced']),
      synced_at: new Date().toISOString(),
    } satisfies PartnerSyncedOrder
  })

  writeStorage({
    orders: [...newOrders, ...storage.orders],
  })

  return {
    count: existing.length + newOrders.length,
    newOrders,
  }
}

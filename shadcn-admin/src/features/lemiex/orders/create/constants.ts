export const ORDER_STATUS_OPTIONS = [
  {
    value: 'new_order',
    label: 'New Order',
    description: 'Standard new order',
  },
  {
    value: 'test_order',
    label: 'Test Order',
    description: 'Test order (no production)',
  },
] as const

export const SHIPPING_SERVICE_OPTIONS = [
  {
    value: 'USPS',
    label: 'USPS',
    description: 'United States Postal Service',
  },
  {
    value: 'FedEx',
    label: 'FedEx',
    description: 'Federal Express',
  },
  {
    value: 'UPS',
    label: 'UPS',
    description: 'United Parcel Service',
  },
] as const

export const DESIGN_POSITION_OPTIONS = [
  { value: 'front', label: 'Front' },
  { value: 'back', label: 'Back' },
  { value: 'neck', label: 'Neck' },
] as const

export const COUNTRY_OPTIONS = [
  { value: 'US', label: 'United States' },
  { value: 'CA', label: 'Canada' },
  { value: 'GB', label: 'United Kingdom' },
  { value: 'AU', label: 'Australia' },
  { value: 'DE', label: 'Germany' },
  { value: 'FR', label: 'France' },
  { value: 'JP', label: 'Japan' },
  { value: 'VN', label: 'Vietnam' },
] as const


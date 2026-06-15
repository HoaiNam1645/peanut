import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Shipping Policy | DragonBug LLC',
}

export default function ShippingPolicyPage() {
  return (
    <>
      <h1>Shipping Policy</h1>
      <p>Last updated: April 27, 2026</p>
      <p>
        Shipping timelines, fees, and available carriers depend on destination,
        selected service level, and third-party logistics conditions.
      </p>
      <p>
        Tracking information is provided once orders are processed by the
        carrier. Delivery delays caused by customs, weather, or carriers are
        outside DragonBug LLC&apos;s direct control.
      </p>
      <p>
        For shipping support, contact contact@dragonbugllc.com with your order
        ID and tracking code.
      </p>
    </>
  )
}

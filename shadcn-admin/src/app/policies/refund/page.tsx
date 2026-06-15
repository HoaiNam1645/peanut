import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Refund Policy | DragonBug LLC',
}

export default function RefundPolicyPage() {
  return (
    <>
      <h1>Refund Policy</h1>
      <p>Last updated: April 27, 2026</p>
      <p>
        Refund requests are reviewed case-by-case based on service type,
        delivery status, and contract terms.
      </p>
      <p>
        To request a refund, please submit your order details and reason within
        7 calendar days from the charge date.
      </p>
      <p>
        Approved refunds are returned via the original payment method. Processing
        times depend on your payment provider.
      </p>
    </>
  )
}

import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Contact & Support | DragonBug LLC',
}

export default function ContactPolicyPage() {
  return (
    <>
      <h1>Contact & Support</h1>
      <p>Business Entity: DragonBug LLC</p>
      <p>Registered Address: 30 N Gould St Ste N Sheridan, WY 82801, USA</p>
      <p>Email: contact@dragonbugllc.com</p>
      <p>Phone: +1 (307) 248-5984</p>
      <p>Registered Agent: Nguyen Dang Ngoc Thanh</p>
      <p>EIN: 98-1932106</p>
      <p>
        For compliance, legal, or customer support requests, please contact us
        through email with relevant account or order details.
      </p>
    </>
  )
}

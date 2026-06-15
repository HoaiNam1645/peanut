import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Privacy Policy | DragonBug LLC',
}

export default function PrivacyPolicyPage() {
  return (
    <>
      <h1>Privacy Policy</h1>
      <p>Last updated: April 27, 2026</p>
      <p>
        DragonBug LLC collects only the information needed to provide and
        improve services, including account details, transaction records, and
        support communications.
      </p>
      <p>
        We process data for account management, fraud prevention, legal
        compliance, and service operations. We do not sell personal data.
      </p>
      <p>
        You may request access, correction, or deletion of personal data by
        contacting us at contact@dragonbugllc.com.
      </p>
    </>
  )
}

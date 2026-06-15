import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Terms of Service | DragonBug LLC',
}

export default function TermsPage() {
  return (
    <>
      <h1>Terms of Service</h1>
      <p>Last updated: April 27, 2026</p>
      <p>
        By using DragonBug LLC services, you agree to comply with applicable
        laws and provide accurate account and transaction information.
      </p>
      <p>
        Misuse of the platform, unauthorized access attempts, or illegal
        activity may result in account suspension or termination.
      </p>
      <p>
        Services are provided on an as-available basis. Liability is limited to
        the maximum extent permitted by law.
      </p>
    </>
  )
}

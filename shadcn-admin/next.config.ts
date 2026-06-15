import type { NextConfig } from 'next'

const backendApiOrigin =
  process.env.BACKEND_API_ORIGIN || 'http://127.0.0.1:8000/api'

const nextConfig: NextConfig = {
  async rewrites() {
    return [
      {
        source: '/api/:path*',
        destination: `${backendApiOrigin}/:path*`,
      },
    ]
  },
}

export default nextConfig

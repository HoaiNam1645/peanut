import { API_BASE_URL } from '@/config/api'

/**
 * Trigger a file download via backend proxy.
 * Falls back to opening the URL in a new tab if proxy fails.
 */
export async function downloadFromUrl(url: string, filename: string): Promise<void> {
  try {
    const proxyUrl = `${API_BASE_URL}/proxy/download?url=${encodeURIComponent(url)}&filename=${encodeURIComponent(filename)}`
    const response = await fetch(proxyUrl)

    if (!response.ok) {
      throw new Error('Download failed')
    }

    const blob = await response.blob()
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(objectUrl)
  } catch {
    window.open(url, '_blank', 'noopener,noreferrer')
  }
}

/**
 * Open the URL in a new tab AND trigger a background download.
 * Useful when the user wants instant visual feedback (the view) while the
 * download completes asynchronously.
 */
export function openAndDownload(url: string, filename: string): void {
  window.open(url, '_blank', 'noopener,noreferrer')
  void downloadFromUrl(url, filename)
}

/**
 * Derive a filename from a URL's pathname, falling back to a default.
 */
export function filenameFromUrl(url: string, fallback: string): string {
  try {
    const u = new URL(url)
    const last = u.pathname.split('/').filter(Boolean).pop()
    return last && last.includes('.') ? decodeURIComponent(last) : fallback
  } catch {
    return fallback
  }
}

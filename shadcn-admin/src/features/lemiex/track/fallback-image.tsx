'use client'

import { useState, type ImgHTMLAttributes } from 'react'

type FallbackImageProps = Omit<
  ImgHTMLAttributes<HTMLImageElement>,
  'src' | 'onError' | 'onLoad'
> & {
  src?: string | null
  fallbackUrls?: string[]
  onValidUrl?: (url: string) => void
}

export function FallbackImage({
  src,
  fallbackUrls = [],
  alt = '',
  className = '',
  onValidUrl,
  ...rest
}: FallbackImageProps) {
  const [currentIndex, setCurrentIndex] = useState(0)
  const [hasError, setHasError] = useState(false)

  const allUrls = src
    ? [src, ...fallbackUrls.filter((u) => u !== src)]
    : fallbackUrls

  const handleError = () => {
    if (currentIndex < allUrls.length - 1) {
      setCurrentIndex(currentIndex + 1)
    } else {
      setHasError(true)
    }
  }

  const handleLoad = () => {
    const url = allUrls[currentIndex]
    if (url && onValidUrl) onValidUrl(url)
  }

  if (allUrls.length === 0 || hasError) return null

  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={allUrls[currentIndex]}
      alt={alt}
      className={className}
      onError={handleError}
      onLoad={handleLoad}
      {...rest}
    />
  )
}

import { useState } from 'react'

/**
 * Product photo with a graceful fallback. Seeded images are hot-linked from
 * Unsplash and admins can paste any URL, so a broken link must not leave an
 * empty hole in the layout.
 */
export default function ProductImage({ src, alt, className = '' }) {
  const [failedSrc, setFailedSrc] = useState(null)

  if (!src || failedSrc === src) {
    return (
      <div className={`product-image product-image-fallback ${className}`} role="img" aria-label={alt}>
        <span aria-hidden="true">🍽️</span>
      </div>
    )
  }

  return (
    <img
      className={`product-image ${className}`}
      src={src}
      alt={alt}
      loading="lazy"
      onError={() => setFailedSrc(src)}
    />
  )
}

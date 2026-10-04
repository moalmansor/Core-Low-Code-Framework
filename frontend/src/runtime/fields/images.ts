/** Client-side image processing before upload: decode, crop and downscale on a canvas. */

export function loadImage(blob: Blob): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve(img)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('image'))
    }
    img.src = url
  })
}

export interface Rect {
  x: number
  y: number
  width: number
  height: number
}

/** Scale that fits width × height inside the limits (never enlarges). */
export function fitScale(width: number, height: number, maxWidth: number | null, maxHeight: number | null): number {
  let scale = 1
  if (maxWidth && width > maxWidth) scale = Math.min(scale, maxWidth / width)
  if (maxHeight && height > maxHeight) scale = Math.min(scale, maxHeight / height)
  return scale
}

/** Crops (optional) and downscales an image; keeps PNG for PNG sources, JPEG otherwise. */
export async function processImage(file: Blob, crop: Rect | null, maxWidth: number | null, maxHeight: number | null): Promise<Blob> {
  if (!crop && !maxWidth && !maxHeight) return file
  const img = await loadImage(file)
  const area = crop ?? { x: 0, y: 0, width: img.naturalWidth, height: img.naturalHeight }
  const scale = fitScale(area.width, area.height, maxWidth, maxHeight)
  if (!crop && scale === 1) return file
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(area.width * scale))
  canvas.height = Math.max(1, Math.round(area.height * scale))
  canvas.getContext('2d')!.drawImage(img, area.x, area.y, area.width, area.height, 0, 0, canvas.width, canvas.height)
  const type = file.type === 'image/png' ? 'image/png' : 'image/jpeg'
  return await new Promise<Blob>((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('canvas'))), type, 0.9))
}

// Shrinks a phone photo in the browser before it is uploaded (Brian, 2026-11-27).
//
// Drivers' phones sent the camera original (2.4–8 MB, 3.5 MB typical on 2026-11-27), so one photo took
// ~10 s on mobile data. A 2048 px long side at JPEG 0.82 is ~0.3–0.7 MB: still sharp enough to zoom into a
// shelf or a label, without being print size.
//
// Never throws and never makes things worse: anything it cannot or need not shrink (video, PDF, GIF, SVG, a
// format the browser cannot decode such as HEIC on Chrome, a photo already small) goes up unchanged, and so
// does a result that is not smaller than the original.

export const MAX_SIDE_PX = 2048;
export const JPEG_QUALITY = 0.82;
// Below this a photo within MAX_SIDE_PX is left alone: re-encoding it gains nothing worth a quality loss.
const SMALL_ENOUGH_BYTES = 700 * 1024;

const SHRINKABLE = /^image\/(jpeg|jpg|png|webp|heic|heif)$/i;

export function isShrinkable(file) {
  return !!file && SHRINKABLE.test(file.type || '');
}

async function decode(file) {
  // createImageBitmap applies the photo's EXIF rotation ('from-image'), so portrait shots stay upright.
  if (typeof createImageBitmap === 'function') {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      // Older Safari rejects the options bag; fall through to an <img>, which browsers also rotate by EXIF.
    }
  }
  const url = URL.createObjectURL(file);
  try {
    const img = new Image();
    img.src = url;
    await img.decode();
    return img;
  } finally {
    URL.revokeObjectURL(url);
  }
}

function jpegName(name) {
  const base = (name || 'photo').replace(/\.[^./\\]+$/, '');
  return `${base}.jpg`;
}

export async function shrinkImage(file) {
  if (!isShrinkable(file)) return file;

  let source;
  try {
    source = await decode(file);
    const width = source.width;
    const height = source.height;
    if (!width || !height) return file;

    const scale = Math.min(1, MAX_SIDE_PX / Math.max(width, height));
    if (scale === 1 && file.size <= SMALL_ENOUGH_BYTES) return file;

    const w = Math.round(width * scale);
    const h = Math.round(height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) return file;
    // JPEG has no transparency: a PNG's clear areas become white, not black.
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(source, 0, 0, w, h);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
    // Free the canvas memory at once; phones hold several 12 MP bitmaps badly.
    canvas.width = 0;
    canvas.height = 0;
    if (!blob || blob.size >= file.size) return file;

    return new File([blob], jpegName(file.name), { type: 'image/jpeg', lastModified: file.lastModified || Date.now() });
  } catch {
    return file;
  } finally {
    if (source && typeof source.close === 'function') source.close();
  }
}

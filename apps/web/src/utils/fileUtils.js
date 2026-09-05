const SUPABASE_STORAGE_BASE_URL = "https://vbabvrcfqcmvvjwmzuwx.supabase.co/storage/v1/object/public";

/**
 * Resolves a full URL for a file path stored in Supabase.
 * @param {string|string[]|null|undefined} filePath - The path to the file or an already resolved URL.
 * @param {string} bucket - The Supabase storage bucket name.
 * @returns {string} The resolved file URL.
 */
export function getFileUrl(filePath, bucket = "cash_advances") {
  if (!filePath) return "";
  const resolved = Array.isArray(filePath) ? (filePath[0] ?? "") : filePath;
  if (!resolved) return "";
  const pathStr = String(resolved);
  if (
    pathStr.startsWith("http://") ||
    pathStr.startsWith("https://") ||
    pathStr.startsWith("blob:")
  ) {
    return pathStr;
  }
  return `${SUPABASE_STORAGE_BASE_URL}/${bucket}/${pathStr.replace(/^\/+/, "")}`;
}


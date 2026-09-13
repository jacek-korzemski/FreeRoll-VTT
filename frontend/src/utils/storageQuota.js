export function formatBytes(bytes) {
  const n = Number(bytes) || 0
  if (n < 1024) return `${n} B`
  if (n < 1048576) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / 1048576).toFixed(1)} MB`
}

export function quotaPercent(storage) {
  const limit = Number(storage?.limit) || 0
  if (limit <= 0) return 0
  const used = Math.max(0, Number(storage?.used) || 0)
  return Math.min(100, Math.round((used / limit) * 100))
}

export function quotaErrorMessage(data, t) {
  if (data?.code === 'quota_exceeded') {
    return t('upload.quotaExceeded', {
      used: formatBytes(data.storage?.used ?? 0),
      limit: formatBytes(data.storage?.limit ?? 0),
    })
  }
  const fromDetails = Array.isArray(data?.errors)
    ? data.errors.find((item) => item?.code === 'quota_exceeded')
    : null
  if (fromDetails) {
    return t('upload.quotaExceeded', {
      used: formatBytes(data.storage?.used ?? 0),
      limit: formatBytes(data.storage?.limit ?? 0),
    })
  }
  return data?.error || null
}

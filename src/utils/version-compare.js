/**
 * 比较两段版本号，判断 current 是否 **低于** latest（需要更新）。
 * 支持 1.2.3、2.1.xyandh.05 等带点分段；纯数字段按数值比，否则按 localeCompare(numeric)。
 */
export function isVersionLower(current, latest) {
  const a = String(current ?? '').trim();
  const b = String(latest ?? '').trim();
  if (!a || !b) return false;
  if (a === b) return false;

  const partsA = a.split('.');
  const partsB = b.split('.');
  const n = Math.max(partsA.length, partsB.length);

  for (let i = 0; i < n; i++) {
    const segA = partsA[i] ?? '0';
    const segB = partsB[i] ?? '0';

    const numA = /^\d+$/.test(segA) ? parseInt(segA, 10) : null;
    const numB = /^\d+$/.test(segB) ? parseInt(segB, 10) : null;

    if (numA !== null && numB !== null) {
      if (numA < numB) return true;
      if (numA > numB) return false;
      continue;
    }

    const cmp = segA.localeCompare(segB, undefined, { numeric: true, sensitivity: 'base' });
    if (cmp < 0) return true;
    if (cmp > 0) return false;
  }

  return false;
}

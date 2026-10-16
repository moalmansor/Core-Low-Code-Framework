/** A list of names read as the user's language writes it ("a, b and c" / "أ وب وج"). */
export function joinList(items: string[], locale: string, type: 'conjunction' | 'unit' = 'unit'): string {
  const list = items.filter((s) => s !== '')
  try {
    return new Intl.ListFormat(locale, { style: 'long', type }).format(list)
  } catch {
    return list.join(locale.startsWith('ar') ? '، ' : ', ')
  }
}

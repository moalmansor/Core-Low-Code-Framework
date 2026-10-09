/**
 * Input masks (field behavior `mask`): `9` a digit, `a` a letter, `A` a
 * letter shown upper-case, `*` a letter or digit, `#` a digit or sign; every
 * other character is a literal inserted as the user types.
 */
const TOKENS: Record<string, RegExp> = { '9': /\p{Nd}/u, a: /\p{L}/u, A: /\p{L}/u, '*': /[\p{L}\p{Nd}]/u, '#': /[\p{Nd}+-]/u }

export function applyMask(mask: string, raw: string): string {
  if (!mask) return raw
  const chars = [...raw].filter((c) => /[\p{L}\p{Nd}+-]/u.test(c))
  let out = ''
  let i = 0
  for (const m of mask) {
    if (i >= chars.length) break
    const token = TOKENS[m]
    if (token === undefined) {
      out += m
      if (chars[i] === m) i++
      continue
    }
    while (i < chars.length && !token.test(chars[i]!)) i++
    if (i >= chars.length) break
    out += m === 'A' ? chars[i]!.toLocaleUpperCase() : chars[i]!
    i++
  }
  return out
}

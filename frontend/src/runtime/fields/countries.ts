/** ITU-T E.164 country calling codes by ISO 3166-1 alpha-2 code. Names come from Intl.DisplayNames in the reader's language. */
const RAW =
  'AD376 AE971 AF93 AG1 AL355 AM374 AO244 AR54 AT43 AU61 AZ994 BA387 BB1 BD880 BE32 BF226 BG359 BH973 BI257 BJ229 BN673 BO591 BR55 BS1 BT975 BW267 BY375 BZ501 ' +
  'CA1 CD243 CF236 CG242 CH41 CI225 CL56 CM237 CN86 CO57 CR506 CU53 CV238 CY357 CZ420 DE49 DJ253 DK45 DM1 DO1 DZ213 EC593 EE372 EG20 ER291 ES34 ET251 FI358 FJ679 FR33 ' +
  'GA241 GB44 GD1 GE995 GH233 GM220 GN224 GQ240 GR30 GT502 GW245 GY592 HK852 HN504 HR385 HT509 HU36 ID62 IE353 IN91 IQ964 IR98 IS354 IT39 JM1 JO962 JP81 KE254 KG996 KH855 ' +
  'KM269 KN1 KP850 KR82 KW965 KZ7 LA856 LB961 LC1 LI423 LK94 LR231 LS266 LT370 LU352 LV371 LY218 MA212 MC377 MD373 ME382 MG261 MK389 ML223 MM95 MN976 MO853 MR222 MT356 MU230 ' +
  'MV960 MW265 MX52 MY60 MZ258 NA264 NE227 NG234 NI505 NL31 NO47 NP977 NZ64 OM968 PA507 PE51 PG675 PH63 PK92 PL48 PS970 PT351 PY595 QA974 RO40 RS381 RU7 RW250 SA966 SC248 ' +
  'SD249 SE46 SG65 SI386 SK421 SL232 SM378 SN221 SO252 SR597 SS211 ST239 SV503 SY963 SZ268 TD235 TG228 TH66 TJ992 TL670 TM993 TN216 TO676 TR90 TT1 TW886 TZ255 UA380 UG256 US1 ' +
  'UY598 UZ998 VA39 VC1 VE58 VN84 YE967 ZA27 ZM260 ZW263'

export const DIAL_CODES: Record<string, string> = Object.fromEntries(RAW.split(' ').map((e) => [e.slice(0, 2), e.slice(2)]))

export function countryName(code: string, locale: string): string {
  try {
    return new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code
  } catch {
    return code
  }
}

/** Splits an E.164 number into the national part for a country (when its calling code prefixes the number). */
export function nationalPart(number: string, country: string | null): string {
  const dial = country ? DIAL_CODES[country] : undefined
  if (dial && number.startsWith(`+${dial}`)) return number.slice(dial.length + 1)
  return number.replace(/^\+/, '')
}

/** E.164 number from a country and the digits typed (a leading trunk 0 is dropped). */
export function toE164(country: string | null, national: string): string {
  const digits = national.replace(/\D/g, '')
  const dial = country ? DIAL_CODES[country] : undefined
  if (!dial) return digits ? `+${digits}` : ''
  return digits ? `+${dial}${digits.replace(/^0+/, '')}` : ''
}

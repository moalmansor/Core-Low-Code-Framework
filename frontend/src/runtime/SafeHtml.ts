import { defineComponent, h, type PropType, type VNode, type VNodeChild } from 'vue'

/**
 * Renders admin-authored or sanitized rich text without `v-html`: the HTML is
 * parsed into an inert document and rebuilt as virtual nodes from an
 * allowlist of tags and attributes. Scripts, styles, event handlers, forms,
 * frames and non-http(s)/mailto/relative links never reach the page.
 */

const TAGS = new Set([
  'p',
  'br',
  'hr',
  'strong',
  'b',
  'em',
  'i',
  'u',
  's',
  'del',
  'ins',
  'mark',
  'small',
  'sub',
  'sup',
  'code',
  'pre',
  'blockquote',
  'ul',
  'ol',
  'li',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'a',
  'span',
  'div',
  'table',
  'thead',
  'tbody',
  'tfoot',
  'tr',
  'th',
  'td',
  'caption',
])
const DROP = new Set(['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'template', 'noscript', 'svg', 'math', 'link', 'meta'])

export function safeHref(href: string | null): string | null {
  if (href === null) return null
  const s = href.trim()
  if (/^(https?:|mailto:)/i.test(s)) return s
  if (s.startsWith('/') && !s.startsWith('//')) return s
  if (s.startsWith('#')) return s
  return null
}

function convert(node: Node): VNodeChild {
  if (node.nodeType === Node.TEXT_NODE) return node.textContent ?? ''
  if (node.nodeType !== Node.ELEMENT_NODE) return null
  const el = node as Element
  const tag = el.tagName.toLowerCase()
  if (DROP.has(tag)) return null
  const children = [...el.childNodes].map(convert).filter((c) => c !== null && c !== '')
  if (!TAGS.has(tag)) return children as VNodeChild
  const attrs: Record<string, string> = {}
  if (tag === 'a') {
    const href = safeHref(el.getAttribute('href'))
    if (href !== null) attrs.href = href
    if (href !== null && /^https?:/i.test(href)) {
      attrs.target = '_blank'
      attrs.rel = 'noopener noreferrer'
    }
  }
  const dir = el.getAttribute('dir')
  if (dir === 'rtl' || dir === 'ltr' || dir === 'auto') attrs.dir = dir
  if ((tag === 'td' || tag === 'th') && /^\d{1,2}$/.test(el.getAttribute('colspan') ?? '')) attrs.colspan = el.getAttribute('colspan')!
  if ((tag === 'td' || tag === 'th') && /^\d{1,2}$/.test(el.getAttribute('rowspan') ?? '')) attrs.rowspan = el.getAttribute('rowspan')!
  return h(tag, attrs, children as VNodeChild[])
}

export function htmlToVNodes(html: string): VNodeChild[] {
  const doc = new DOMParser().parseFromString(`<!doctype html><body>${html}</body>`, 'text/html')
  return [...doc.body.childNodes].map(convert)
}

function escape(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function inline(text: string): string {
  return escape(text)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/__([^_]+)__/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    .replace(/~~([^~]+)~~/g, '<del>$1</del>')
    .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_m, label: string, href: string) => `<a href="${href}">${label}</a>`)
}

/** Markdown subset (headings, emphasis, code, quotes, lists, links, rules) as HTML for SafeHtml. */
export function markdownToHtml(source: string): string {
  const lines = source.replace(/\r\n?/g, '\n').split('\n')
  const out: string[] = []
  let list: 'ul' | 'ol' | null = null
  let code = false
  let paragraph: string[] = []
  const flush = () => {
    if (paragraph.length) out.push(`<p>${paragraph.map(inline).join('<br>')}</p>`)
    paragraph = []
  }
  const closeList = () => {
    if (list) out.push(`</${list}>`)
    list = null
  }
  for (const line of lines) {
    if (line.startsWith('```')) {
      flush()
      closeList()
      out.push(code ? '</code></pre>' : '<pre><code>')
      code = !code
      continue
    }
    if (code) {
      out.push(`${escape(line)}\n`)
      continue
    }
    const heading = /^(#{1,6})\s+(.*)$/.exec(line)
    const bullet = /^\s*[-*+]\s+(.*)$/.exec(line)
    const ordered = /^\s*\d+[.)]\s+(.*)$/.exec(line)
    if (heading) {
      flush()
      closeList()
      out.push(`<h${heading[1]!.length}>${inline(heading[2]!)}</h${heading[1]!.length}>`)
    } else if (bullet || ordered) {
      flush()
      const kind = bullet ? 'ul' : 'ol'
      if (list !== kind) {
        closeList()
        out.push(`<${kind}>`)
        list = kind
      }
      out.push(`<li>${inline((bullet ?? ordered)![1]!)}</li>`)
    } else if (/^\s*(---|\*\*\*)\s*$/.test(line)) {
      flush()
      closeList()
      out.push('<hr>')
    } else if (line.startsWith('>')) {
      flush()
      closeList()
      out.push(`<blockquote>${inline(line.replace(/^>\s?/, ''))}</blockquote>`)
    } else if (line.trim() === '') {
      flush()
      closeList()
    } else {
      closeList()
      paragraph.push(line)
    }
  }
  flush()
  closeList()
  if (code) out.push('</code></pre>')
  return out.join('')
}

/** Serializes the allowlisted structure of HTML back to a clean HTML string (rich text input). */
export function cleanHtml(html: string): string {
  const doc = new DOMParser().parseFromString(`<!doctype html><body>${html}</body>`, 'text/html')
  const walk = (node: Node): string => {
    if (node.nodeType === Node.TEXT_NODE) return escape(node.textContent ?? '')
    if (node.nodeType !== Node.ELEMENT_NODE) return ''
    const el = node as Element
    const tag = el.tagName.toLowerCase()
    if (DROP.has(tag)) return ''
    const inner = [...el.childNodes].map(walk).join('')
    if (!TAGS.has(tag)) return inner
    if (tag === 'br' || tag === 'hr') return `<${tag}>`
    let attrs = ''
    if (tag === 'a') {
      const href = safeHref(el.getAttribute('href'))
      if (href !== null) attrs = ` href="${escape(href)}"`
    }
    return `<${tag}${attrs}>${inner}</${tag}>`
  }
  return [...doc.body.childNodes].map(walk).join('')
}

export default defineComponent({
  name: 'SafeHtml',
  props: {
    html: { type: String, default: '' },
    markdown: { type: Boolean, default: false },
    tag: { type: String as PropType<string>, default: 'div' },
  },
  setup(props) {
    return (): VNode => h(props.tag, { class: 'lcf-rich' }, htmlToVNodes(props.markdown ? markdownToHtml(props.html) : props.html))
  },
})

/**
 * Loads the Monaco editor for the extension screens (specification §4.19)
 * on demand. Monaco adds <style> elements at run time: each gets the page's
 * CSP nonce, as PrimeVue's do. Its workers are bundled files of this
 * application.
 */
import type * as Monaco from 'monaco-editor/esm/vs/editor/editor.api'

let loading: Promise<typeof Monaco> | null = null

function nonceStyles(): void {
  const nonce = document.querySelector<HTMLMetaElement>('meta[name="csp-nonce"]')?.content
  if (!nonce || (document as unknown as { __lcfNonced?: boolean }).__lcfNonced) return
  ;(document as unknown as { __lcfNonced?: boolean }).__lcfNonced = true
  const create = document.createElement.bind(document)
  document.createElement = ((tag: string, options?: ElementCreationOptions) => {
    const el = create(tag, options)
    if (tag.toLowerCase() === 'style') el.setAttribute('nonce', nonce)
    return el
  }) as typeof document.createElement
}

export function loadMonaco(): Promise<typeof Monaco> {
  loading ??= (async () => {
    nonceStyles()
    const [{ default: EditorWorker }, { default: TsWorker }] = await Promise.all([
      import('monaco-editor/esm/vs/editor/editor.worker?worker'),
      import('monaco-editor/esm/vs/language/typescript/ts.worker?worker'),
    ])
    ;(self as unknown as { MonacoEnvironment: unknown }).MonacoEnvironment = {
      getWorker: (_: string, label: string) => (label === 'typescript' || label === 'javascript' ? new TsWorker() : new EditorWorker()),
    }
    const monaco = await import('monaco-editor/esm/vs/editor/editor.api')
    await Promise.all([
      import('monaco-editor/esm/vs/basic-languages/php/php.contribution'),
      import('monaco-editor/esm/vs/language/typescript/monaco.contribution'),
      import('monaco-editor/esm/vs/basic-languages/typescript/typescript.contribution'),
      import('monaco-editor/esm/vs/editor/contrib/find/browser/findController'),
      import('monaco-editor/esm/vs/editor/contrib/suggest/browser/suggestController'),
      import('monaco-editor/esm/vs/editor/contrib/hover/browser/hoverContribution'),
      import('monaco-editor/esm/vs/editor/contrib/bracketMatching/browser/bracketMatching'),
      import('monaco-editor/esm/vs/editor/contrib/comment/browser/comment'),
    ])
    // Client extensions see the frame's API as the global "lcf".
    monaco.languages.typescript.typescriptDefaults.addExtraLib(LCF_TYPES, 'file:///lcf.d.ts')
    monaco.languages.typescript.typescriptDefaults.setCompilerOptions({ target: monaco.languages.typescript.ScriptTarget.ES2020, strict: true, noEmit: true, allowNonTsExtensions: true })
    return monaco
  })()
  return loading
}

const LCF_TYPES = `
interface LcfFieldApi { value: unknown; readonly: boolean; locale: string; dir: 'ltr' | 'rtl'; setValue(value: unknown): void }
interface LcfScriptResult { set?: Record<string, unknown>; messages?: { text: string; severity?: 'info' | 'warn' | 'error' }[] }
declare const lcf: {
  validator(fn: (values: Record<string, unknown>) => Record<string, string> | null | Promise<Record<string, string> | null>): void
  script(def: { onChange(values: Record<string, unknown>, changed: string[]): LcfScriptResult | void | Promise<LcfScriptResult | void> }): void
  field(def: { mount(el: HTMLElement, api: LcfFieldApi): void; update?(value: unknown): void }): void
  resize(): void
}
declare module 'vue' { export const createApp: any; export const h: any; export const ref: any; export const computed: any; export const watch: any }
`

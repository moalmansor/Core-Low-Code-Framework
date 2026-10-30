import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import { createApp } from 'vue'
import 'primeicons/primeicons.css'
import App from './App.vue'
import { i18n } from './i18n'
import { router } from './router'
import './style.css'
import { ProductPreset } from './theme/preset'

// Styles PrimeVue injects at runtime carry the per-request CSP nonce that the
// server renders into the page (no 'unsafe-inline' needed).
const nonce = document.querySelector<HTMLMetaElement>('meta[name="csp-nonce"]')?.content

createApp(App)
  .use(createPinia())
  .use(i18n)
  .use(router)
  .use(PrimeVue, {
    theme: { preset: ProductPreset, options: { darkModeSelector: '.app-dark', cssLayer: false } },
    csp: nonce ? { nonce } : undefined,
    ripple: false,
    // WCAG 2.1 (axe "aria-hidden-focus"): Listbox's hidden focus-redirect spans are aria-hidden yet focusable.
    // Taking them out of the tab order and making the list itself the tab stop keeps keyboard use the same.
    pt: { listbox: { hiddenFirstFocusableEl: { tabindex: -1 }, hiddenLastFocusableEl: { tabindex: -1 }, list: { tabindex: 0 } } },
  })
  .use(ToastService)
  .use(ConfirmationService)
  .directive('tooltip', Tooltip)
  .mount('#app')

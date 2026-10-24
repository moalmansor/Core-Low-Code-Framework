/*
 * The runtime of client extensions (specification §4.19, ADR-0051). It runs
 * inside a sandboxed frame with an opaque origin: no cookies, no session, no
 * network (the frame's policy allows none). The only way out is a message
 * to the page that holds the frame, which decides what to do with it.
 *
 * An extension registers itself with one of:
 *   lcf.validator(function (values) { return { field_key: 'message' } })
 *   lcf.script({ onChange: function (values, changed) { return { set: {}, messages: [] } } })
 *   lcf.field({ mount: function (el, api) {}, update: function (value) {} })
 * where api = { value, readonly, locale, dir, setValue(v) }. Field components
 * may import { createApp, h, ref } from "vue" and render with h().
 */
(function () {
  'use strict'
  var parentWindow = window.parent
  var handlers = {}
  var context = { locale: 'en', dir: 'ltr', readonly: false }
  var mounted = false

  function post(message) {
    message.lcf = 1
    parentWindow.postMessage(message, '*')
  }
  function plain(value) {
    try {
      return JSON.parse(JSON.stringify(value === undefined ? null : value))
    } catch (e) {
      return null
    }
  }
  function report(error) {
    post({ type: 'error', message: String((error && error.message) || error).slice(0, 500) })
  }
  function settle(result, done) {
    if (result && typeof result.then === 'function') result.then(done, report)
    else done(result)
  }
  function resize() {
    post({ type: 'height', px: Math.ceil(document.documentElement.scrollHeight) })
  }

  // Builds import "vue" as an external module: it is the frame's own copy of Vue's runtime build.
  window.require = function (name) {
    if (name === 'vue' && window.Vue) return window.Vue
    throw new Error('Only "vue" can be imported in an extension.')
  }

  window.lcf = {
    validator: function (fn) {
      handlers.validate = fn
    },
    script: function (def) {
      handlers.script = def
    },
    field: function (def) {
      handlers.field = def
    },
    resize: resize,
  }

  window.addEventListener('message', function (event) {
    if (event.source !== parentWindow) return
    var m = event.data || {}
    try {
      if (m.type === 'init') {
        context = { locale: m.locale || 'en', dir: m.dir === 'rtl' ? 'rtl' : 'ltr', readonly: !!m.readonly }
        document.documentElement.lang = context.locale
        document.documentElement.dir = context.dir
        if (handlers.field && !mounted) {
          mounted = true
          handlers.field.mount(document.getElementById('app'), {
            value: m.value,
            readonly: context.readonly,
            locale: context.locale,
            dir: context.dir,
            setValue: function (v) {
              post({ type: 'value', value: plain(v) })
            },
          })
          resize()
        }
      } else if (m.type === 'value' && handlers.field && handlers.field.update) {
        handlers.field.update(m.value)
        resize()
      } else if (m.type === 'validate') {
        if (!handlers.validate) return post({ type: 'result', id: m.id, errors: {} })
        settle(handlers.validate(m.values || {}), function (errors) {
          post({ type: 'result', id: m.id, errors: plain(errors) || {} })
        })
      } else if (m.type === 'change') {
        if (!handlers.script || !handlers.script.onChange) return post({ type: 'apply', id: m.id, set: {}, messages: [] })
        settle(handlers.script.onChange(m.values || {}, m.changed || []), function (out) {
          out = plain(out) || {}
          post({ type: 'apply', id: m.id, set: out.set || {}, messages: out.messages || [] })
        })
      }
    } catch (e) {
      report(e)
      if (m.id !== undefined) post({ type: m.type === 'validate' ? 'result' : 'apply', id: m.id, errors: {}, set: {}, messages: [], failed: true })
    }
  })

  window.addEventListener('error', function (e) {
    report(e.error || e.message)
  })
  window.addEventListener('DOMContentLoaded', function () {
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(resize).observe(document.documentElement)
    post({ type: 'ready', kinds: Object.keys(handlers) })
  })
})()

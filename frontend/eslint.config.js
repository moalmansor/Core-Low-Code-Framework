import js from '@eslint/js'
import prettier from 'eslint-config-prettier'
import vue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import vueParser from 'vue-eslint-parser'

export default tseslint.config(
  { ignores: ['dist/**', 'node_modules/**', 'playwright-report/**', 'test-results/**'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    files: ['**/*.vue', '**/*.ts'],
    languageOptions: {
      parser: vueParser,
      parserOptions: { parser: tseslint.parser, sourceType: 'module', extraFileExtensions: ['.vue'] },
    },
    rules: {
      // TypeScript checks identifiers; the core rule does not know DOM types.
      'no-undef': 'off',
      'vue/multi-word-component-names': 'off',
      // v-html is never used: server content is rendered as text only.
      'vue/no-v-html': 'error',
      'no-restricted-syntax': ['error', { selector: "CallExpression[callee.name='eval']", message: 'eval is not allowed (CSP).' }],
    },
  },
  prettier,
)

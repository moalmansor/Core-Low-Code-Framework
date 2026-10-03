/** Save-time error codes of expression-language.md §10. */
export type StaticErrorCode = 'SYNTAX' | 'TYPE' | 'UNKNOWN_FUNCTION' | 'ARITY' | 'PATH_DEPTH' | 'DEPTH' | 'PRECISION'

/**
 * A save-time rejection of an expression (expression-language.md §10 static
 * codes): SYNTAX, TYPE, UNKNOWN_FUNCTION, ARITY, PATH_DEPTH, DEPTH, PRECISION.
 * Twin of backend/app/Expressions/StaticError.php.
 */
export class StaticError extends Error {
  readonly errorCode: StaticErrorCode
  /** Code-point offset in the source text (parser errors). */
  readonly position: number | null
  /** Node path (type checker errors). */
  readonly node: string | null

  constructor(errorCode: StaticErrorCode, message: string, position: number | null = null, node: string | null = null) {
    super(message)
    this.name = 'StaticError'
    this.errorCode = errorCode
    this.position = position
    this.node = node
  }

  toJSON(): { code: StaticErrorCode; message: string; position: number | null; node: string | null } {
    return { code: this.errorCode, message: this.message, position: this.position, node: this.node }
  }
}

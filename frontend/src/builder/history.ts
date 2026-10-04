/**
 * Undo/redo over serialized document states. Every change to the document,
 * structural or a property edit, is recorded as a new state; undo and redo
 * move between states. Recording a state after an undo discards the redo
 * branch, as in every editor.
 */
export class History {
  private past: string[] = []
  private future: string[] = []
  private present: string
  private readonly limit: number

  constructor(initial: string, limit = 150) {
    this.present = initial
    this.limit = limit
  }

  get current(): string {
    return this.present
  }

  get canUndo(): boolean {
    return this.past.length > 0
  }

  get canRedo(): boolean {
    return this.future.length > 0
  }

  get depth(): { undo: number; redo: number } {
    return { undo: this.past.length, redo: this.future.length }
  }

  /** Records a state; returns false when it equals the current one. */
  record(state: string): boolean {
    if (state === this.present) return false
    this.past.push(this.present)
    if (this.past.length > this.limit) this.past.shift()
    this.present = state
    this.future = []
    return true
  }

  /** The previous state, or null when there is nothing to undo. */
  undo(): string | null {
    const previous = this.past.pop()
    if (previous === undefined) return null
    this.future.push(this.present)
    this.present = previous
    return previous
  }

  /** The next state, or null when there is nothing to redo. */
  redo(): string | null {
    const next = this.future.pop()
    if (next === undefined) return null
    this.past.push(this.present)
    this.present = next
    return next
  }

  /** Starts over from a state (after loading or reloading a draft). */
  reset(state: string): void {
    this.past = []
    this.future = []
    this.present = state
  }
}

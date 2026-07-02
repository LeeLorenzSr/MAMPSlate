# AGENTS.md

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

### 5. Deterministic First

**Codex is for judgment calls. Plain code does everything else.**

Fetching, filtering, routing, persisting, dispatching — none of it is a language task. Don't ask the model to "decide if we should retry" when a status code already answers. Use the model for: classification, drafting, summarization, extraction from unstructured text. That's the whole list.

The failure mode without this rule: the model makes a routing decision one week, a different routing decision the next, and you've reinvented flaky if-else at $0.003/token.

### 6. Declare Budgets, Halt On Breach

**No silent overruns. Ever.**

Every AI step runs under a token budget: per-step, per-pipeline, per-day. Exceeding any of the three halts the pipeline immediately, logs the breach, and surfaces it to the operator. Budgets live in config, not in prompts.

```yaml
budgets:
  per_step_tokens: 2048
  per_pipeline_tokens: 10000
  per_day_tokens: 100000
```

The failure mode without this rule: a runaway loop burns $40 overnight and you find out from the invoice.

### 7. Human-In-The-Loop Is A First-Class Step Type

**Label destructive actions. Require approval. No exceptions via flags.**

Anything touching the outside world — sending an email, updating a CRM, posting a message — is an `approval` step, not an `ai` step. The approval is routed to an operator channel (Slack, Telegram, whatever) with approve/edit/reject controls. The pipeline blocks until a decision is recorded.

```yaml
- name: approve-send
  type: approval
  mode: hitl
  channel: telegram
```

The failure mode without this rule: a hallucinated follow-up email goes to a real customer.

### 8. Validate AI Output Against A Schema

**Unstructured strings don't belong in deterministic downstream code.**

Every AI step declares an output schema. The runtime rejects anything that doesn't match — missing fields, wrong types, out-of-range numbers. Rejected outputs trigger a retry (under budget) or halt.

```yaml
output_schema:
  type: object
  required: [match, reason, score]
  properties:
    match:  { type: boolean }
    reason: { type: string, maxLength: 280 }
    score:  { type: integer, minimum: 0, maximum: 100 }
```

The failure mode without this rule: a boolean comes back as the string `"maybe"` and a downstream `if` branches the wrong way.

For generated React and Next.js code, I use [agentproof-react](https://github.com/renezander030/agentproof-react) as a small deterministic review gate before shipping.

### 9. Sanitize Operator Input Before It Reaches A Prompt

**User-supplied text is not trusted.**

Before any operator or external input enters a prompt, strip role markers (`system:`, `assistant:`, `<|im_start|>` variants), enforce length limits, and normalize markdown so formatting can't break prompt boundaries. This is prompt-injection defense, not input validation — the goal is to stop an attacker from pivoting the model mid-run.

### 10. Log Rejections Silently

**Don't narrate to the attacker.**

When input is rejected for sanitization or schema violations, log internally — never echo the rejection reason back to the source. A detailed error message is a free signal that tells the attacker which pattern to try next.

## The "working if" test

The full ten rules are working if:

- Diffs are smaller and more targeted (rules 1–4).
- Pipeline runs have predictable token costs (rule 6).
- No AI output ever reaches a production side-effect without a human approval record (rule 7).
- Downstream code never branches on a malformed AI response (rule 8).
- Operator-channel logs show silent rejections rather than echoed errors (rules 9–10).

If even one of those is failing, the rule isn't enforced — it's aspirational.


**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.
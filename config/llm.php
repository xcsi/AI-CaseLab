<?php

// Per-tier settings for the Discussion Engine's provider-agnostic LLM layer
// (docs/13-ai-discussion-engine-design.md §1.4). The fallback chain's tier
// order itself is NOT configurable here -- it's fixed in code
// (LlmClientFactory, §1.4.1) precisely because that fixed order is the
// cost-safety guarantee, not a preference. Every tier below is
// independently optional to configure except Ollama (tier 1, always
// attempted -- an unreachable local instance just fails its liveness check
// and falls through, §1.4.3).

return [

    /*
    |--------------------------------------------------------------------------
    | Reply length discipline (§4.4)
    |--------------------------------------------------------------------------
    |
    | Applies to every persona, every tier. Short on purpose -- a senior
    | engineer challenging you in a review sends two sharp sentences and a
    | question, not an essay. Also a direct cost control (§12).
    |
    | This budget has to cover more than just the visible reply on a
    | "reasoning" model (docs/15 §2.2 flagged this as a risk for the
    | OpenRouter tier's free model): those models spend part of max_tokens
    | on an internal `reasoning` field before ever writing to `content`.
    | Reproduced live 2026-08-03 at the old default (300): OpenRouter's
    | nvidia/nemotron-nano-9b-v2:free hit finish_reason "length" with
    | content null and ~379 reasoning tokens already spent -- the model
    | never got to write an answer at all, so every turn fell back to the
    | "couldn't be read" placeholder even though nothing was malformed.
    | 1000 leaves real headroom for a full reasoning pass plus the short
    | reply itself, on a still-free tier.
    */

    'max_tokens' => (int) env('LLM_MAX_TOKENS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Tier 1 — Local Ollama ($0, always a candidate)
    |--------------------------------------------------------------------------
    */

    'ollama' => [
        'base_url' => env('LLM_OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
        'model' => env('LLM_OLLAMA_MODEL', 'qwen2.5:7b'),
        // Small local models served through Ollama often don't reliably
        // support native tool-calling/JSON-schema output (§1.4.6) -- off by
        // default until a specific model has actually been verified.
        'supports_structured_output' => (bool) env('LLM_OLLAMA_SUPPORTS_STRUCTURED_OUTPUT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier 2 — OpenRouter, a free-tier model ($0, candidate only when configured)
    |--------------------------------------------------------------------------
    */

    'openrouter' => [
        'base_url' => env('LLM_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('LLM_OPENROUTER_API_KEY'),
        'model' => env('LLM_OPENROUTER_FREE_MODEL', 'meta-llama/llama-3.1-8b-instruct:free'),
        'supports_structured_output' => (bool) env('LLM_OPENROUTER_SUPPORTS_STRUCTURED_OUTPUT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier 3 — Gemini, a free-tier model ($0, candidate only when configured)
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'api_key' => env('LLM_GEMINI_API_KEY'),
        'model' => env('LLM_GEMINI_MODEL', 'gemini-1.5-flash'),
        'supports_structured_output' => (bool) env('LLM_GEMINI_SUPPORTS_STRUCTURED_OUTPUT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier 4 — Paid (OpenAI or Anthropic), structurally absent unless enabled
    |--------------------------------------------------------------------------
    |
    | "allowed" is the entire mechanism behind "never silently spend money"
    | (§1.4.4). When false (the shipped default), LlmClientFactory never
    | constructs a paid-tier client and never adds one to the chain's
    | candidate array -- there is no code path at request time that could
    | reach it, not even a disabled/skipped branch. A stale API key below is
    | inert on its own; both this flag AND a matching "provider" choice are
    | required before either paid client is ever touched.
    |
    */

    'paid_fallback' => [
        'allowed' => (bool) env('LLM_ALLOW_PAID_FALLBACK', false),
        'provider' => env('LLM_PAID_FALLBACK_PROVIDER'), // "openai" or "anthropic" — pick one
    ],

    'openai' => [
        'base_url' => env('LLM_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('LLM_OPENAI_API_KEY'),
        'model' => env('LLM_OPENAI_MODEL', 'gpt-4o-mini'),
        'supports_structured_output' => (bool) env('LLM_OPENAI_SUPPORTS_STRUCTURED_OUTPUT', true),
    ],

    'anthropic' => [
        'api_key' => env('LLM_ANTHROPIC_API_KEY'),
        'model' => env('LLM_ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
        'supports_structured_output' => (bool) env('LLM_ANTHROPIC_SUPPORTS_STRUCTURED_OUTPUT', true),
    ],

];

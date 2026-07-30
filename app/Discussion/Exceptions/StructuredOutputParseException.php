<?php

namespace App\Discussion\Exceptions;

use RuntimeException;

/**
 * Thrown by StructuredOutputParser when a model's raw reply doesn't decode
 * to valid JSON, or is missing/has an invalid value for a required field
 * (docs/13-ai-discussion-engine-design.md §1.4.6, §4.3). Deliberately never
 * thrown for optional fields — those are normalized to a sensible default by
 * the parser itself, which is a different thing from inferring a *required*
 * field that's actually missing.
 */
class StructuredOutputParseException extends RuntimeException
{
}

<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Log;

/**
 * ResponseParser
 *
 * Extracts the `recommendations` array from an {@see AiResponse} produced
 * by an {@see AiProviderInterface}, after validating the top-level shape
 * declared in {@see PromptBuilder::RECOMMENDATION_SCHEMA}.
 *
 * Responsibilities:
 *   - Guarantee that `$response->parsed` is a JSON object (associative array).
 *     If it is not, raise {@see AiProviderException} so the Recommendation_Engine
 *     can mark the {@code Ai_Analysis} as `failed` per Requirement 6.9 (and
 *     downstream cleanup applies: no Usage_Record, no orphan Recommendations).
 *   - Guarantee that the `recommendations` key exists and is an array. An
 *     empty array is a valid, healthy outcome and MUST be returned as such.
 *   - Drop individual recommendation items missing any of the required
 *     top-level keys declared in the schema (`action_type`, `severity`,
 *     `rationale`, `payload`). Such drops are logged at WARNING level but
 *     they do NOT abort the whole analysis — payload-level validation is
 *     the responsibility of {@see RecommendationValidator} (Requirement 6.6).
 *
 * The caller (Recommendation_Engine) takes the filtered array, runs each
 * item through {@see RecommendationValidator::validate()}, and persists the
 * survivors with `status = pending` (Requirements 6.6, 6.7).
 *
 * Validates: Requirements 6.6, 6.7
 */
final class ResponseParser
{
    /**
     * Required top-level keys per recommendation item, mirroring
     * {@see PromptBuilder::RECOMMENDATION_SCHEMA} `items.required`.
     *
     * Hard-coded as the fallback when the supplied schema does not declare
     * `recommendations.items.required` explicitly.
     *
     * @var list<string>
     */
    private const REQUIRED_ITEM_KEYS = ['action_type', 'severity', 'rationale', 'payload'];

    /**
     * Validate the response shape and extract the `recommendations` items.
     *
     * @param  AiResponse              $response The DTO returned by the Ai_Provider.
     * @param  array<string,mixed>     $schema   Typically {@see PromptBuilder::RECOMMENDATION_SCHEMA}.
     *                                           Only the `properties.recommendations.items.required`
     *                                           path is consulted; missing => fall back to the
     *                                           canonical {@see self::REQUIRED_ITEM_KEYS}.
     * @return array<int,array<string,mixed>>    Filtered recommendation items. Empty array is valid.
     *
     * @throws AiProviderException When the response is not a JSON object or
     *                             the `recommendations` key is missing / not
     *                             an array (Requirement 6.9 trigger).
     */
    public function parse(AiResponse $response, array $schema): array
    {
        $parsed = $response->parsed;

        // Defensive: $parsed is typed `array` on the DTO, but a list (sequential)
        // is not a JSON object. Reject both non-arrays and pure lists.
        if (!is_array($parsed) || $this->isList($parsed)) {
            throw new AiProviderException(
                'AI response is not a JSON object',
                0,
                null,
                [
                    'model'       => $response->model,
                    'raw_excerpt' => substr($response->raw, 0, 500),
                ]
            );
        }

        $recs = $parsed['recommendations'] ?? null;
        if (!is_array($recs)) {
            throw new AiProviderException(
                'AI response is not a JSON object',
                0,
                null,
                [
                    'model'       => $response->model,
                    'raw_excerpt' => substr($response->raw, 0, 500),
                ]
            );
        }

        $requiredKeys = $this->resolveRequiredKeys($schema);
        $filtered = [];

        foreach ($recs as $index => $rec) {
            if (!is_array($rec)) {
                Log::warning('[MasterAds] Skipping recommendation: not an object.', [
                    'index' => $index,
                    'model' => $response->model,
                ]);
                continue;
            }

            $missing = [];
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $rec)) {
                    $missing[] = $key;
                }
            }

            if ($missing !== []) {
                Log::warning('[MasterAds] Skipping recommendation: missing required keys.', [
                    'index'   => $index,
                    'missing' => $missing,
                    'model'   => $response->model,
                ]);
                continue;
            }

            $filtered[] = $rec;
        }

        return $filtered;
    }

    /**
     * Pull the required item keys from the supplied schema, falling back to
     * the canonical {@see self::REQUIRED_ITEM_KEYS} when the schema omits them.
     *
     * @param  array<string,mixed> $schema
     * @return list<string>
     */
    private function resolveRequiredKeys(array $schema): array
    {
        $required = $schema['properties']['recommendations']['items']['required'] ?? null;
        if (is_array($required) && $required !== []) {
            return array_values(array_filter($required, 'is_string'));
        }
        return self::REQUIRED_ITEM_KEYS;
    }

    /**
     * Detect whether an associative array is actually a JSON list (sequential
     * 0..N-1 integer keys). PHP 8.1 ships `array_is_list`, but we keep a
     * dependency-free implementation for clarity.
     *
     * @param  array<int|string,mixed> $arr
     */
    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $expected = 0;
        foreach ($arr as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }
}

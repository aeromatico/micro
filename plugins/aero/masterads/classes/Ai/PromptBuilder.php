<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

/**
 * PromptBuilder
 *
 * Composes the system and user prompts fed to the Ai_Provider by the
 * Recommendation_Engine. Embeds the `RECOMMENDATION_SCHEMA` so the LLM is
 * forced to emit responses that survive `ResponseParser` and
 * `RecommendationValidator` (Requirement 6.5) and surfaces the
 * `lookback_days` aggregate persisted in `Ai_Analysis.metrics_snapshot`
 * for reproducibility (Requirement 6.4).
 *
 * Both prompts always return non-empty strings; the engine stores them
 * verbatim in `Ai_Analysis.prompt_payload` (Requirement 6.10).
 *
 * Validates: Requirements 6.4, 6.5
 */
final class PromptBuilder
{
    /** Action types accepted in AI responses (must match Recommendation enum). */
    public const ACTION_TYPES = [
        'adjust_budget','pause','resume','scale','change_audience','change_creative',
    ];

    public const SEVERITIES = ['low','medium','high','critical'];

    /**
     * JSON schema the LLM must produce (Requirement 6.5).
     */
    public const RECOMMENDATION_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'overall_health' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
            'recommendations' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['action_type','severity','rationale','payload'],
                    'properties' => [
                        'action_type' => ['type' => 'string', 'enum' => self::ACTION_TYPES],
                        'severity' => ['type' => 'string', 'enum' => self::SEVERITIES],
                        'rationale' => ['type' => 'string', 'minLength' => 10],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        'expected_impact_pct' => ['type' => 'number'],
                        'payload' => ['type' => 'object'],
                    ],
                ],
            ],
        ],
        'required' => ['recommendations'],
    ];

    /**
     * Build the system prompt that fixes the LLM role and output contract.
     *
     * The prompt embeds the canonical action_type / severity enums and the
     * `RECOMMENDATION_SCHEMA` so the model never invents fields that the
     * downstream `RecommendationValidator` would discard (Requirement 6.5).
     *
     * @param  string $targetType One of `campaign|adset|ad`.
     * @return string             Non-empty multi-line instruction block.
     */
    public function system(string $targetType): string
    {
        $type = $this->normalizeTargetType($targetType);
        $actionList = implode(', ', self::ACTION_TYPES);
        $severityList = implode(', ', self::SEVERITIES);
        $schemaJson = json_encode(
            self::RECOMMENDATION_SCHEMA,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return <<<TEXT
You are a senior Meta Ads (Facebook/Instagram) performance optimization expert
analysing a single {$type}. Your sole task is to inspect the KPI snapshot
supplied by the user and emit data-driven recommendations.

OPERATING RULES
- Reason exclusively from the metrics provided. Do not invent numbers nor
  assume context that is absent from the snapshot.
- Use ONLY these action_type values: {$actionList}.
- Use ONLY these severity values: {$severityList}.
- Severity MUST be proportional to the magnitude of `expected_impact_pct`:
    * |impact| < 5    -> low
    * 5  <= |impact| < 15 -> medium
    * 15 <= |impact| < 30 -> high
    * |impact| >= 30   -> critical
- Every recommendation MUST include a `rationale` (>= 10 chars) that cites
  the specific KPI(s) (CTR, CPC, ROAS, CPA, spend, conversions...) backing it.
- `payload` MUST contain the operational fields the action needs (e.g.
  `daily_budget` for adjust_budget, target audience descriptors for
  change_audience). Never leave it empty.
- If the data is insufficient or healthy, return an empty `recommendations`
  array and explain the situation in `summary`. Never fabricate actions.
- `overall_health` is an integer 0-100 reflecting global account/{$type} health.

OUTPUT CONTRACT
- Respond with JSON ONLY. No prose, no markdown fences, no commentary.
- The JSON MUST validate against this schema:
{$schemaJson}
TEXT;
    }

    /**
     * Build the user prompt carrying the target metadata, the lookback
     * aggregate (Requirement 6.4) and the derived KPIs computed by
     * `MetricsAggregator::finalize()`.
     *
     * @param  mixed                $target   Eloquent model or array with at least `id`, `name`, `status`.
     * @param  array<string,mixed>  $snapshot Aggregated raw metrics (impressions, clicks, spend, conversions, revenue, lookback_days...).
     * @param  array<string,mixed>  $derived  Derived KPIs (CTR, CPC, CPM, ROAS, CPA, conversion_rate).
     * @param  array<string,mixed>  $options  Engine options, notably `lookback_days` (default 14) and optional `notes`.
     * @return string                         Non-empty markdown payload ready to send to the LLM.
     */
    public function user($target, array $snapshot, array $derived, array $options = []): string
    {
        $lookbackDays = (int) ($options['lookback_days'] ?? $snapshot['lookback_days'] ?? $snapshot['period'] ?? 14);
        if ($lookbackDays <= 0) {
            $lookbackDays = 14;
        }

        $meta = $this->extractTargetMeta($target);
        $rawTable = $this->renderMetricsTable('Raw metrics ('.$lookbackDays.' day lookback)', [
            'Impressions'  => $this->formatInt($snapshot['impressions']  ?? null),
            'Clicks'       => $this->formatInt($snapshot['clicks']       ?? null),
            'Spend (USD)'  => $this->formatMoney($snapshot['spend']      ?? null),
            'Conversions' => $this->formatInt($snapshot['conversions']  ?? null),
            'Revenue (USD)' => $this->formatMoney($snapshot['revenue']    ?? null),
            'Reach'        => $this->formatInt($snapshot['reach']        ?? null),
            'Frequency'    => $this->formatDecimal($snapshot['frequency'] ?? null, 2),
        ]);

        $derivedTable = $this->renderMetricsTable('Derived KPIs', [
            'CTR (%)'             => $this->formatDecimal($derived['ctr']             ?? null, 2),
            'CPC (USD)'           => $this->formatMoney($derived['cpc']               ?? null),
            'CPM (USD)'           => $this->formatMoney($derived['cpm']               ?? null),
            'ROAS'                => $this->formatDecimal($derived['roas']            ?? null, 2),
            'CPA (USD)'           => $this->formatMoney($derived['cpa']               ?? null),
            'Conversion rate (%)' => $this->formatDecimal($derived['conversion_rate'] ?? null, 2),
        ]);

        $notes = isset($options['notes']) && is_string($options['notes']) && $options['notes'] !== ''
            ? "\n\nADDITIONAL NOTES\n".trim($options['notes'])
            : '';

        $targetType = $this->normalizeTargetType((string) ($meta['target_type'] ?? 'campaign'));

        return <<<TEXT
ANALYSIS REQUEST
Please analyse the following Meta Ads {$targetType} over the last {$lookbackDays} days
and emit recommendations following the system contract.

TARGET METADATA
- Type:      {$targetType}
- ID:        {$meta['id']}
- Name:      {$meta['name']}
- Status:    {$meta['status']}
- Objective: {$meta['objective']}
- Daily budget (USD): {$meta['daily_budget']}

{$rawTable}

{$derivedTable}

INSTRUCTIONS
- Consider the {$lookbackDays}-day lookback window when judging trends and volume sufficiency.
- Flag underperforming KPIs against typical Meta Ads benchmarks (e.g. CTR < 1%,
  ROAS < 1, CPA above target).
- Prefer fewer, higher-impact recommendations over many low-impact ones.
- Use `payload` to encode concrete operational changes (target values, deltas,
  audiences, creative directions).
- Return JSON ONLY, matching the schema declared in the system prompt.{$notes}
TEXT;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Coerce arbitrary target representations to a flat metadata array.
     *
     * Accepts Eloquent models, plain arrays or stdClass.
     *
     * @param  mixed $target
     * @return array{target_type:string,id:string,name:string,status:string,objective:string,daily_budget:string}
     */
    private function extractTargetMeta($target): array
    {
        $get = static function ($subject, string $key) {
            if (is_array($subject)) {
                return $subject[$key] ?? null;
            }
            if (is_object($subject)) {
                if (isset($subject->{$key})) {
                    return $subject->{$key};
                }
                // Eloquent: getAttribute fallback
                if (method_exists($subject, 'getAttribute')) {
                    try {
                        return $subject->getAttribute($key);
                    } catch (\Throwable $e) {
                        return null;
                    }
                }
            }
            return null;
        };

        $targetType = $get($target, 'target_type');
        if ($targetType === null) {
            $targetType = $get($target, 'targetType');
        }
        if ($targetType === null) {
            // Derive from class name when possible (Campaign / AdSet / Ad).
            if (is_object($target)) {
                $short = strtolower((new \ReflectionClass($target))->getShortName());
                $targetType = match (true) {
                    str_contains($short, 'adset')    => 'adset',
                    str_contains($short, 'campaign') => 'campaign',
                    str_contains($short, 'ad')       => 'ad',
                    default                          => 'campaign',
                };
            } else {
                $targetType = 'campaign';
            }
        }

        return [
            'target_type'  => (string) $targetType,
            'id'           => (string) ($get($target, 'id') ?? $get($target, 'meta_id') ?? 'n/a'),
            'name'         => (string) ($get($target, 'name') ?? 'n/a'),
            'status'       => (string) ($get($target, 'status') ?? 'n/a'),
            'objective'    => (string) ($get($target, 'objective') ?? 'n/a'),
            'daily_budget' => $this->formatMoney($get($target, 'daily_budget')),
        ];
    }

    /**
     * Render a compact markdown table for a key/value pair set.
     *
     * @param  array<string,string> $rows
     */
    private function renderMetricsTable(string $title, array $rows): string
    {
        $out  = $title.':'."\n";
        $out .= '| Metric | Value |'."\n";
        $out .= '|---|---|'."\n";
        foreach ($rows as $label => $value) {
            $out .= '| '.$label.' | '.$value.' |'."\n";
        }
        return rtrim($out, "\n");
    }

    private function normalizeTargetType(string $targetType): string
    {
        $normalized = strtolower(trim($targetType));
        return in_array($normalized, ['campaign', 'adset', 'ad'], true)
            ? $normalized
            : 'campaign';
    }

    private function formatInt($value): string
    {
        if ($value === null || $value === '') {
            return 'n/a';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        return number_format((float) $value, 0, '.', ',');
    }

    private function formatDecimal($value, int $decimals): string
    {
        if ($value === null || $value === '') {
            return 'n/a';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        return number_format((float) $value, $decimals, '.', ',');
    }

    private function formatMoney($value): string
    {
        if ($value === null || $value === '') {
            return 'n/a';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        return number_format((float) $value, 2, '.', ',');
    }
}

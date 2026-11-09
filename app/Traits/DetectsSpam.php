<?php

namespace App\Traits;

trait DetectsSpam
{
    /**
     * Default threshold score at or above which content is marked as spam.
     */
    protected int $spamThreshold = 50;

    /**
     * Check whether the given text or the model attributes contain spam.
     */
    public function isSpam(?string $text = null): bool
    {
        return $this->detectSpam($text)['is_spam'];
    }

    /**
     * Alias for isSpam().
     */
    public function containsSpam(?string $text = null): bool
    {
        return $this->isSpam($text);
    }

    /**
     * Calculate and return the spam score for the text or model.
     */
    public function getSpamScore(?string $text = null): int
    {
        return $this->detectSpam($text)['score'];
    }

    /**
     * Perform detailed spam analysis on the provided text or model attributes.
     *
     * @return array{
     *     is_spam: bool,
     *     score: int,
     *     threshold: int,
     *     reasons: list<string>,
     *     matches: list<string>,
     *     checked_text: string
     * }
     */
    public function detectSpam(?string $text = null): array
    {
        $content = $text ?? $this->resolveTextForSpamCheck();

        $score = 0;
        $reasons = [];
        $matches = [];
        $threshold = property_exists($this, 'spamThreshold') ? $this->spamThreshold : 50;

        if (trim($content) === '') {
            return [
                'is_spam' => false,
                'score' => 0,
                'threshold' => $threshold,
                'reasons' => [],
                'matches' => [],
                'checked_text' => '',
            ];
        }

        // 1. Keyword check (weighted 50 points per high-confidence keyword match)
        $matchedKeywords = $this->findSpamKeywords($content);
        if (! empty($matchedKeywords)) {
            $score += count($matchedKeywords) * 50;
            $matches = array_merge($matches, $matchedKeywords);
            $reasons[] = 'Contains suspicious or blacklisted keywords: '.implode(', ', $matchedKeywords);
        }

        // 2. Excessive repeated characters / exclamation / dollar signs (e.g. "!!!!!", "$$$$$")
        if (preg_match('/([!?$*#%])\1{3,}/i', $content, $puncMatch)) {
            $score += 25;
            $reasons[] = 'Contains excessive repeated punctuation: '.$puncMatch[0];
            $matches[] = $puncMatch[0];
        }

        if (preg_match('/([a-zA-Z])\1{5,}/i', $content, $charMatch)) {
            $score += 25;
            $reasons[] = 'Contains excessive repeated letters: '.$charMatch[0];
            $matches[] = $charMatch[0];
        }

        // 3. Excessive URLs or suspicious links
        $linkCount = preg_match_all('/https?:\/\/[^\s]+/i', $content, $urlMatches);
        if ($linkCount >= 3) {
            $score += 30;
            $reasons[] = "Contains an excessive number of links ({$linkCount} links)";
        }

        // Check for suspicious TLDs or IP addresses in URLs
        if (preg_match('/https?:\/\/(?:\d{1,3}\.){3}\d{1,3}|https?:\/\/[^\s\/]+?\.(?:top|xyz|click|loan|work|fit|gdn|cam|stream|racing|download|review|accountant|faith)\b/i', $content, $badUrl)) {
            $score += 40;
            $reasons[] = 'Contains links with suspicious or high-risk domain patterns';
            $matches[] = $badUrl[0];
        }

        // 4. Excessive uppercase ratio for text with meaningful length (> 25 characters)
        $cleanContent = preg_replace('/[^a-zA-Z]/', '', $content) ?? '';
        if (strlen($cleanContent) >= 25) {
            $uppercaseCount = strlen(preg_replace('/[^A-Z]/', '', $cleanContent) ?? '');
            $ratio = $uppercaseCount / strlen($cleanContent);
            if ($ratio >= 0.75) {
                $score += 30;
                $reasons[] = 'Excessive uppercase text detected ('.round($ratio * 100).'%)';
            }
        }

        // 5. Zero-width / hidden unicode characters commonly used to bypass filters
        if (preg_match('/[\x{200B}-\x{200D}\x{FEFF}]/u', $content)) {
            $score += 35;
            $reasons[] = 'Contains zero-width or hidden unicode characters used to bypass spam filters';
        }

        $isSpam = $score >= $threshold;

        return [
            'is_spam' => $isSpam,
            'score' => $score,
            'threshold' => $threshold,
            'reasons' => $reasons,
            'matches' => array_values(array_unique($matches)),
            'checked_text' => $content,
        ];
    }

    /**
     * Resolve the combined text from model attributes to inspect.
     */
    protected function resolveTextForSpamCheck(): string
    {
        $fields = $this->getSpamCheckAttributes();
        $parts = [];

        foreach ($fields as $field) {
            $value = $this->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Get the list of model attributes to inspect for spam.
     *
     * @return list<string>
     */
    public function getSpamCheckAttributes(): array
    {
        if (property_exists($this, 'spamCheckAttributes') && is_array($this->spamCheckAttributes) && ! empty($this->spamCheckAttributes)) {
            return $this->spamCheckAttributes;
        }

        // Default fallback to inspectable fillable string fields if defined
        $candidates = ['award_name', 'award_by', 'description', 'title', 'name', 'content', 'comment', 'body', 'message'];
        $fillable = property_exists($this, 'fillable') && is_array($this->fillable) ? $this->fillable : [];

        $intersect = array_values(array_intersect($candidates, $fillable));

        return ! empty($intersect) ? $intersect : ['award_name', 'award_by', 'description'];
    }

    /**
     * Find matched spam keywords in the given text.
     *
     * @return list<string>
     */
    public function findSpamKeywords(string $text): array
    {
        $keywords = $this->getSpamKeywords();
        $matches = [];

        foreach ($keywords as $keyword) {
            $pattern = '/\b'.preg_quote($keyword, '/').'\b/i';
            if (preg_match($pattern, $text)) {
                $matches[] = $keyword;
            }
        }

        return $matches;
    }

    /**
     * Get the list of spam keywords.
     *
     * @return list<string>
     */
    public function getSpamKeywords(): array
    {
        $defaults = [
            'casino',
            'online slots',
            'free spins',
            'poker online',
            'viagra',
            'cialis',
            'cheap pills',
            'crypto giveaway',
            'free bitcoin',
            'whatsapp loan',
            'fast loan approval',
            'buy followers',
            'buy backlinks',
            'replica watches',
            'escort service',
            'adult dating',
            'earn money fast',
            'work from home make millions',
        ];

        $custom = property_exists($this, 'spamKeywords') && is_array($this->spamKeywords)
            ? $this->spamKeywords
            : [];

        return array_values(array_unique(array_merge($defaults, $custom)));
    }
}

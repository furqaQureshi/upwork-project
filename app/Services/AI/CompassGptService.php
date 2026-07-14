<?php

namespace App\Services\AI;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

class CompassGptService
{

    /**
     * @param  array<int, array<string, string>>  $history
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function chat(string $query, array $history = [], array $context = []): array
    {
        $trimmedQuery    = trim($query);
        $languageMode    = $this->detectLanguageMode($trimmedQuery);
        $normalizedQuery = $this->normalizeMultilingualQuery($trimmedQuery);
        $contextualQuery = $this->buildContextualQuery($normalizedQuery, $history);

        // ── Step 1: Pure local structured intent extraction ───────────────────
        $extracted     = $this->extractIntent($trimmedQuery, $normalizedQuery, $history);
        $intentClass   = $extracted['intent_class'];

        // Pure conversational messages — no search needed
        if (in_array($intentClass, ['greeting', 'chitchat', 'thanks', 'capability', 'farewell'], true)) {
            return [
                'query'               => $trimmedQuery,
                'effective_query'     => $contextualQuery,
                'parsed_intent'       => ['intent_class' => $intentClass],
                'clarifying_question' => ($extracted['follow_up'] ?? '') !== '' ? $extracted['follow_up'] : null,
                'summary'             => $this->handleConversational($intentClass, $trimmedQuery, $history, $languageMode),
                'recommendations'     => [],
                'provider'            => 'local',
            ];
        }

        // ── Step 2: Sell intent ───────────────────────────────────────────────
        if ($intentClass === 'sell') {
            return [
                'query'               => $trimmedQuery,
                'effective_query'     => $contextualQuery,
                'parsed_intent'       => array_merge($extracted, ['intent_class' => 'sell']),
                'clarifying_question' => ($extracted['follow_up'] ?? '') !== '' ? $extracted['follow_up'] : null,
                'summary'             => $this->buildSellGuidance($extracted, $languageMode),
                'recommendations'     => [],
                'provider'            => 'local',
            ];
        }

        // ── Step 3: Advice messages ───────────────────────────────────────────
        if (in_array($intentClass, ['buying_advice', 'selling_advice', 'price_advice', 'comparison', 'listing_help', 'emi'], true)) {
            $advice = $this->handleAdvice($intentClass, $normalizedQuery, $contextualQuery, $languageMode);
            $intent = $this->mergeExtractedIntent($extracted, $context);
            $recommendations = [];
            if (in_array($intentClass, ['buying_advice', 'price_advice'], true) &&
                (($intent['item_type'] ?? '') !== '' || ($intent['property_type'] ?? '') !== '')) {
                $recommendations = $this->searchListings($intent, $contextualQuery);
            }

            return [
                'query'               => $trimmedQuery,
                'effective_query'     => $contextualQuery,
                'parsed_intent'       => array_merge($intent, ['intent_class' => $intentClass]),
                'clarifying_question' => null,
                'summary'             => $advice,
                'recommendations'     => $recommendations,
                'provider'            => 'local',
            ];
        }

        // ── Step 4: Search intent ─────────────────────────────────────────────
        $intent          = $this->mergeExtractedIntent($extracted, $context);
        $recommendations = $this->searchListings($intent, $contextualQuery);

        $clarifyingQuestion = ($extracted['follow_up'] ?? '') !== '' ? $extracted['follow_up'] : null;
        if ($clarifyingQuestion === null && $recommendations === []) {
            $hasItem  = ($intent['item_type'] ?? '') !== '' || ($intent['property_type'] ?? '') !== '';
            $isGlobal = (bool) ($intent['global_search'] ?? false);
            if (! $hasItem) {
                $clarifyingQuestion = $this->localizedPrompt('item', $languageMode);
            } elseif (($intent['budget_max'] ?? null) === null) {
                $clarifyingQuestion = $this->localizedPrompt('budget', $languageMode);
            } elseif (($intent['location'] ?? '') === '' && ! $isGlobal) {
                $clarifyingQuestion = $this->localizedPrompt('location', $languageMode);
            }
        }

        $summary = $this->buildSearchSummary($trimmedQuery, $intent, $recommendations, $history, $contextualQuery, $languageMode);

        return [
            'query'               => $trimmedQuery,
            'effective_query'     => $contextualQuery,
            'parsed_intent'       => array_merge($intent, ['intent_class' => $intentClass]),
            'clarifying_question' => $clarifyingQuestion,
            'summary'             => $summary,
            'recommendations'     => $recommendations,
            'provider'            => 'local',
        ];
    }

    // ── Local Structured Intent Extraction ───────────────────────────────────

    /**
     * Pure local intent extractor — no external API calls.
     * Combines classifyIntent() + parseIntent() into one structured result
     * that mirrors the system prompt output format:
     *
     *   intent_class  string   search|sell|greeting|thanks|…
     *   keywords      string   main product/item keywords
     *   category      string   inferred category label
     *   price_min     float|null
     *   price_max     float|null
     *   location      string
     *   condition     string   new|used|''
     *   item_type     string
     *   property_type string
     *   follow_up     string   clarifying question if needed
     *   global_search bool
     *
     * @param  array<int, array<string, string>>  $history
     * @return array<string, mixed>
     */
    private function extractIntent(string $raw, string $normalized, array $history = []): array
    {
        $intentClass = $this->classifyIntent($normalized, $history);

        // For non-search intents we don't need full parsing.
        if (in_array($intentClass, ['greeting', 'chitchat', 'thanks', 'capability', 'farewell'], true)) {
            return ['intent_class' => $intentClass, 'follow_up' => ''];
        }

        // Detect sell intent from raw query (normalizer maps "bechna" → "sell").
        $isSell = preg_match(
            '/\b(sell|bechna|bechna\s+hai|i\s+want\s+to\s+sell|want\s+to\s+sell|post\s+ad|post\s+my\s+ad|list\s+my|selling\s+my)\b/i',
            $normalized
        ) === 1;
        if ($isSell) {
            $intentClass = 'sell';
        }

        // Extract budget.
        $budgetMax = null;
        $budgetMin = null;
        if (preg_match('/under\s+([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)?/i', $raw, $m) === 1) {
            $budgetMax = $this->amountToNumber($m[1] ?? '', $m[2] ?? '');
        } elseif (preg_match('/([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)\s*(?:max|budget)?/i', $raw, $m) === 1) {
            $budgetMax = $this->amountToNumber($m[1] ?? '', $m[2] ?? '');
        }
        if (preg_match('/(?:above|over|min|minimum|from)\s+([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)?/i', $raw, $m) === 1) {
            $budgetMin = $this->amountToNumber($m[1] ?? '', $m[2] ?? '');
        }

        // Extract condition.
        $condition = '';
        if (preg_match('/\b(new|brand\s*new)\b/i', $raw) === 1) {
            $condition = 'new';
        } elseif (preg_match('/\b(used|second\s*hand|secondhand|old|pre-?owned)\b/i', $raw) === 1) {
            $condition = 'used';
        }

        // Extract location.
        $location = '';
        if (preg_match('/\bin\s+([a-zA-Z]+(?:\s+[a-zA-Z]+)?)/i', $raw, $m) === 1) {
            $loc = trim((string) ($m[1] ?? ''));
            $loc = preg_replace('/\s+\b(under|over|max|near|with|and|for|find|show|buy|sell|get|the|a|an|is|are)\b.*$/i', '', $loc) ?? $loc;
            $loc = preg_replace('/\s+\d.*$/', '', $loc) ?? $loc;
            $loc = trim($loc);
            $nonPlace = ['find', 'show', 'search', 'buy', 'sell', 'the', 'get', 'need', 'want', 'help'];
            if (! in_array(strtolower($loc), $nonPlace, true) && str_word_count($loc) <= 3) {
                $location = $loc;
            }
        }
        if ($location === '') {
            $location = $this->inferLocationFromQuery($raw);
        }

        // Detect global search.
        $globalSearch = preg_match(
            '/\b(all\s+india|pan\s+india|anywhere|globally|any\s+city|any\s+location|nationwide|sabhi\s+jagah|poore\s+india)\b/i',
            $raw
        ) === 1;
        if ($globalSearch) {
            $location = '';
        }

        // Item / property type.
        $focusLower = strtolower($raw);
        $itemType   = $this->resolveItemType($focusLower, '');
        $propertyType = $this->resolvePropertyType($focusLower, '');

        // Bedrooms.
        $bedrooms = null;
        if (preg_match('/(\d+)\s*(bhk|bed|bedroom)/i', $raw, $m) === 1) {
            $bedrooms = (int) ($m[1] ?? 0);
        }

        // Keywords: everything meaningful from the raw query.
        $keywords = implode(' ', $this->extractSearchKeywords($raw, [
            'location' => $location,
        ]));

        // Infer a human-readable category label.
        $category = $this->inferCategoryLabel($itemType, $propertyType, $normalized);

        // Sell-specific fields.
        $sellTitle = '';
        $sellPrice = null;
        if ($intentClass === 'sell') {
            // Try to extract what they're selling.
            if (preg_match('/\bsell(?:ing)?\s+(?:my\s+)?(.+?)(?:\s+for\s+|\s+at\s+|\s+price\s+|$)/i', $raw, $m) === 1) {
                $sellTitle = ucfirst(trim((string) ($m[1] ?? '')));
            }
            $sellPrice = $budgetMax ?? $budgetMin;
        }

        // Follow-up question for unclear queries.
        $followUp = '';
        if ($intentClass === 'search' && $itemType === '' && $propertyType === '' && $keywords === '') {
            $followUp = 'What are you looking for? For example: car, phone, flat, bike.';
        }

        return [
            'intent_class'  => $intentClass,
            'keywords'      => $keywords,
            'category'      => $category,
            'price_min'     => $budgetMin,
            'price_max'     => $budgetMax,
            'budget_min'    => $budgetMin,
            'budget_max'    => $budgetMax,
            'location'      => $location,
            'condition'     => $condition,
            'item_type'     => $itemType,
            'property_type' => $propertyType,
            'bedrooms'      => $bedrooms,
            'global_search' => $globalSearch,
            'follow_up'     => $followUp,
            // sell-specific
            'title'         => $sellTitle,
            'price'         => $sellPrice,
        ];
    }

    /**
     * Infer a human-readable category label for display / logging.
     */
    private function inferCategoryLabel(string $itemType, string $propertyType, string $query): string
    {
        if ($propertyType !== '') {
            return ucfirst($propertyType).'s / Real Estate';
        }
        return match ($itemType) {
            'car'     => 'Cars',
            'bike'    => 'Bikes & Scooters',
            'phone'   => 'Mobiles & Phones',
            'laptop'  => 'Laptops & Computers',
            'tv'      => 'TVs & Electronics',
            'fridge'  => 'Appliances',
            'ac'      => 'Appliances',
            'truck'   => 'Commercial Vehicles',
            'tractor' => 'Farm Equipment',
            'sofa'    => 'Furniture',
            default   => '',
        };
    }

    /**
     * Merge locally-extracted intent with device/context data (location label, etc.)
     *
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function mergeExtractedIntent(array $extracted, array $context = []): array
    {
        $location = trim((string) ($extracted['location'] ?? ''));

        if ($location === '' && ! ($extracted['global_search'] ?? false)) {
            $contextLocation = trim((string) ($context['location_label'] ?? ''));
            $isGenericLabel  = preg_match('/^\s*(near\s+you|near\s+me|nearby|your\s+location)\s*$/i', $contextLocation) === 1;
            if ($contextLocation !== '' && ! $isGenericLabel) {
                $location = $contextLocation;
            }
        }

        return [
            'budget_max'    => $extracted['budget_max'] ?? null,
            'budget_min'    => $extracted['budget_min'] ?? null,
            'location'      => $location,
            'near'          => '',
            'bedrooms'      => $extracted['bedrooms'] ?? null,
            'property_type' => $extracted['property_type'] ?? '',
            'item_type'     => $extracted['item_type'] ?? '',
            'condition'     => $extracted['condition'] ?? '',
            'global_search' => $extracted['global_search'] ?? false,
        ];
    }

    /**
     * Resolve item_type from free-text (keywords + optional category hint).
     */
    private function resolveItemType(string $keywords, string $category = ''): string
    {
        $text = strtolower($keywords.' '.$category);
        $candidates = [
            'iphone', 'phone', 'phones', 'mobile', 'smartphone', 'android',
            'laptop', 'laptops', 'computer', 'computers',
            'car', 'cars', 'vehicle', 'auto',
            'bike', 'bikes', 'scooter', 'scooters', 'motorcycle',
            'truck', 'trucks', 'tractor', 'tractors',
            'tv', 'television', 'furniture', 'sofa', 'fridge', 'refrigerator',
            'ac', 'washing machine',
        ];
        $item = $this->detectLatestCandidate($text, $candidates);
        if (in_array($item, ['iphone', 'mobile', 'phones', 'smartphone', 'android'], true)) { return 'phone'; }
        if (in_array($item, ['cars', 'vehicle', 'auto'], true)) { return 'car'; }
        if (in_array($item, ['bikes', 'scooters', 'motorcycle'], true)) { return 'bike'; }
        if (in_array($item, ['laptops', 'computer', 'computers'], true)) { return 'laptop'; }
        if ($item === 'television') { return 'tv'; }
        if ($item === 'refrigerator') { return 'fridge'; }
        return $item;
    }

    /**
     * Resolve property_type from free-text.
     */
    private function resolvePropertyType(string $keywords, string $category = ''): string
    {
        $text = strtolower($keywords.' '.$category);
        $candidates = ['flat', 'apartment', 'house', 'villa', 'plot', 'land', 'commercial', 'office', 'shop'];
        return $this->detectLatestCandidate($text, $candidates);
    }

    /**
     * Build a friendly sell-guidance response when user wants to post an ad.
     *
     * @param  array<string, mixed>  $extracted
     */
    private function buildSellGuidance(array $extracted, string $languageMode): string
    {
        $title    = trim((string) ($extracted['title'] ?? ''));
        $category = trim((string) ($extracted['category'] ?? ''));
        $price    = isset($extracted['price']) && $extracted['price'] !== null
            ? $this->formatAmount((float) $extracted['price'])
            : null;

        if ($languageMode === 'hi' || $languageMode === 'hinglish') {
            $parts = ['Aapka ad post karne ke liye ready hoon!'];
            if ($title !== '')    { $parts[] = "Title: {$title}"; }
            if ($category !== '') { $parts[] = "Category: {$category}"; }
            if ($price !== null)  { $parts[] = "Price: {$price}"; }
            $parts[] = 'App mein "Sell" button dabao aur details fill karo.';
            return implode("\n", $parts);
        }

        $parts = ['Ready to help you post your ad!'];
        if ($title !== '')    { $parts[] = "Title: {$title}"; }
        if ($category !== '') { $parts[] = "Category: {$category}"; }
        if ($price !== null)  { $parts[] = "Suggested price: {$price}"; }
        $parts[] = 'Tap the "Sell" button in the app to post it.';
        return implode("\n", $parts);
    }

    private function detectLanguageMode(string $query): string
    {
        if (preg_match('/\p{Devanagari}/u', $query) === 1) {
            return 'hi';
        }

        if (preg_match('/\b(kya|kaise|mujhe|chahiye|batao|dikhao|dhoond|dhund|sasta|mahenga|bechna|kharidna|kitna|mein|pas|acha|accha)\b/i', $query) === 1) {
            return 'hinglish';
        }

        return 'en';
    }

    private function normalizeMultilingualQuery(string $query): string
    {
        $normalized = mb_strtolower(trim($query));

        $patterns = [
            '/\b(gaadi|gadi|car)\b/u' => ' car ',
            '/(गाड़ी|गाडी|कार)/u' => ' car ',
            '/\b(scooty|scooter|bike|motorcycle)\b/u' => ' bike ',
            '/(स्कूटी|बाइक|मोटरसाइकिल)/u' => ' bike ',
            '/\b(mobile|phone|smartphone)\b/u' => ' phone ',
            '/(फोन|मोबाइल|स्मार्टफोन)/u' => ' phone ',
            '/\b(ghar|makaan|makan|house|flat|apartment)\b/u' => ' house ',
            '/(घर|मकान|फ्लैट|अपार्टमेंट)/u' => ' house ',
            '/\b(dhoond|dhund|dikhao|dikhaiye|find|search|show)\b/u' => ' find ',
            '/(ढूंढ|ढूँढ|दिखाओ|खोजो)/u' => ' find ',
            '/\b(kharidna|buy|purchase)\b/u' => ' buy ',
            '/(खरीद|खरीदना)/u' => ' buy ',
            '/\b(bechna|sell)\b/u' => ' sell ',
            '/(बेच|बेचना)/u' => ' sell ',
            '/\b(ke\s+pas|paas|pas|nearby|near)\b/u' => ' near ',
            '/(पास|नज़दीक|करीब)/u' => ' near ',
            '/\b(mein|main|in)\b/u' => ' in ',
            '/(में|मे)/u' => ' in ',
            '/\b(ke\s+andar|se\s+kam|under|below)\b/u' => ' under ',
            '/(कम|से\s+कम|अंदर)/u' => ' under ',
            '/\b(lac|lakh)\b/u' => ' lakh ',
            '/(लाख)/u' => ' lakh ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? trim($normalized);

        return $normalized;
    }

    private function localizedPrompt(string $key, string $languageMode): string
    {
        if ($languageMode === 'hi') {
            return match ($key) {
                'item' => 'Aap kya dhoond rahe hain? Example: car, phone, flat, bike.',
                'budget' => 'Aapka budget kya hai? Example: under 15 lakh.',
                'location' => 'Kaunsi city ya area mein search karu?',
                default => 'Thoda aur detail dein, main better help kar paunga.',
            };
        }

        if ($languageMode === 'hinglish') {
            return match ($key) {
                'item' => 'Aap kya dhoond rahe ho? Example: car, phone, flat, bike.',
                'budget' => 'Aapka budget kya hai? Example: under 15 lakh.',
                'location' => 'Kaunsi city ya area mein search karun?',
                default => 'Thoda aur detail do, better result dunga.',
            };
        }

        return match ($key) {
            'item' => 'What are you looking for? For example: car, phone, flat, bike.',
            'budget' => 'Do you have a budget in mind? For example, under 15 lakh.',
            'location' => 'Which city or area should I search in?',
            default => 'Could you share a little more detail?',
        };
    }

    // ── Intent Classification ─────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, string>>  $history
     */
    private function classifyIntent(string $query, array $history): string
    {
        $lower = strtolower(trim($query));

        if (preg_match('/\b(emi|equated monthly|monthly installment|monthly payment|loan installment|loan amount|home loan|car loan|vehicle finance|finance.*car|car.*finance)\b/i', $lower) === 1) {
            return 'emi';
        }

        if (preg_match('/^(hi|hello|hey|hii|helo|namaste|good morning|good afternoon|good evening|howdy|sup|what\'?s up|wassup)[\s!?.]*$/i', $lower) === 1) {
            return 'greeting';
        }

        if (preg_match('/^(bye|goodbye|see you|later|ciao|ok bye|thanks? bye)[\s!?.]*$/i', $lower) === 1) {
            return 'farewell';
        }

        if (preg_match('/^(thanks?|thank you|thx|ty|ok thanks?|okay thanks?|got it|okay|alright|sure|sounds good|perfect|great|awesome|nice|cool|noted|👍|🙏)[\s!?.]*$/i', $lower) === 1) {
            return 'thanks';
        }

        if (preg_match('/\b(what can you|what do you do|help me|how can you help|your capabilities|what are you|who are you|what is this)\b/i', $lower) === 1) {
            return 'capability';
        }

        if (preg_match('/\b(write|create|draft|improve|help.*write|how to write|write.*ad|write.*listing|ad description|listing description)\b/i', $lower) === 1) {
            return 'listing_help';
        }

        if (preg_match('/\b(how much|what.*price|fair price|good price|is.*price|is.*worth|overpriced|underpriced|negotiate|market rate|market price|price range|price estimate|cost of|worth buying)\b/i', $lower) === 1) {
            return 'price_advice';
        }

        if (preg_match('/\b(what to check|things to check|tips.*buying|how to buy|buying guide|what should i look|inspection|red flags|things to look|used car check|second hand checklist|checklist)\b/i', $lower) === 1) {
            return 'buying_advice';
        }

        if (preg_match('/\b(how to sell|selling tips?|tips.*selling|how to price.*sell|get more buyers|attract buyers|best time to sell|sell faster|sell quickly|sell my)\b/i', $lower) === 1) {
            return 'selling_advice';
        }

        if (preg_match('/\b(vs\.?|versus|compare|difference between|which is better|which one.*buy|which.*should i)\b/i', $lower) === 1) {
            return 'comparison';
        }

        return 'search';
    }

    // ── Conversational Response Handlers ─────────────────────────────────────

    /**
     * @param  array<int, array<string, string>>  $history
     */
    private function handleConversational(string $intentClass, string $query, array $history, string $languageMode = 'en'): string
    {
        $isReturningUser = count($history) > 2;

        if ($languageMode === 'hi' || $languageMode === 'hinglish') {
            return match ($intentClass) {
                'greeting' => 'Namaste! Main aapki buying/selling mein help kar sakta hoon. Aap Hindi, English ya Hinglish mein puch sakte ho.',
                'farewell' => 'Dhanyavaad! Jab bhi zarurat ho, wapas aaiye.',
                'thanks' => 'Khushi hui madad karke. Aur kuch chahiye ho to batayiye.',
                'capability' => 'Main listings dhoond sakta hoon, price advice de sakta hoon, buying tips aur ad writing mein help kar sakta hoon.',
                default => 'Main madad ke liye yahan hoon. Aap natural language mein puch sakte ho.',
            };
        }

        switch ($intentClass) {
            case 'greeting':
                return $isReturningUser
                    ? "Welcome back! What are you looking for today? I can find listings, give you price advice, or help you write a better ad."
                    : "Hi there! I'm your AI buying and selling assistant.\n\nTell me what you're looking for — like \"used Swift under 5 lakh in Pune\" — and I'll find the best matches.\n\nI can also:\n• Give price advice (\"Is 15 lakh fair for an i20?\")\n• Share buying tips (\"What to check when buying a used phone?\")\n• Help you write better ads\n\nWhat can I help you with?";

            case 'farewell':
                return "Goodbye! Come back anytime you need help finding deals or writing better ads. Good luck! 👋";

            case 'thanks':
                $options = [
                    "Happy to help! Let me know if you need anything else.",
                    "You're welcome! Feel free to ask if you need more information.",
                    "Glad I could help! Anything else on your mind?",
                ];

                return $options[array_rand($options)];

            case 'capability':
                return "Here's what I can help you with:\n\n🔍 Find listings — \"used Swift in Pune under 4 lakh\"\n💰 Price advice — \"is 8 lakh fair for a 2019 i20?\"\n🛒 Buying tips — \"what to check when buying a used phone\"\n📝 Write better ads — \"help me write a listing for my Honda Activa\"\n📊 Comparisons — \"Activa vs Jupiter, which is better?\"\n\nJust ask naturally — I'll understand!";

            default:
                return "I'm here to help! You can ask me to find listings, get price advice, buying/selling tips, or help write a better ad.";
        }
    }

    private function handleAdvice(string $intentClass, string $query, string $contextualQuery, string $languageMode = 'en'): string
    {
        $lower = strtolower($query);

        switch ($intentClass) {
            case 'price_advice':
                return $this->generatePriceAdvice($lower, $contextualQuery);
            case 'buying_advice':
                return $this->generateBuyingAdvice($lower);
            case 'selling_advice':
                return $this->generateSellingAdvice($lower);
            case 'comparison':
                return $this->generateComparison($lower);
            case 'listing_help':
                return $this->generateListingHelp($lower);
            case 'emi':
                return $this->calculateEmi($query);
            default:
                return $languageMode === 'en'
                    ? 'Could you give me a bit more detail so I can help better?'
                    : 'Thoda aur detail do, main better help karunga.';
        }
    }

    private function calculateEmi(string $query): string
    {
        $amount = null;
        if (preg_match('/([0-9,.]+)\s*(lakh|lac|crore|cr|k|thousand)?/i', $query, $m) === 1) {
            $amount = $this->amountToNumber((string) ($m[1] ?? ''), (string) ($m[2] ?? ''));
        }

        if ($amount === null || $amount <= 0) {
            return "Tell me the loan amount and I'll calculate the EMI.\nExample: \"What is EMI for 8 lakh car loan?\"";
        }

        // Extract optional interest rate and tenure from query
        $rate = 0.09; // default 9% per annum
        $years = 5;   // default 5 years

        if (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $query, $m) === 1) {
            $rate = (float) ($m[1] ?? 9) / 100;
        }
        if (preg_match('/(\d+)\s*(year|yr|years)/i', $query, $m) === 1) {
            $years = max(1, min(30, (int) ($m[1] ?? 5)));
        }

        $monthlyRate = $rate / 12;
        $months = $years * 12;
        $emi = $amount * $monthlyRate * (float) (pow(1 + $monthlyRate, $months)) / ((float) (pow(1 + $monthlyRate, $months)) - 1);

        $totalPayment = $emi * $months;
        $totalInterest = $totalPayment - $amount;

        $formatted      = $this->formatAmount($amount);
        $emiFormatted   = '\u20b9'.number_format((int) round($emi));
        $totalFormatted = $this->formatAmount($totalPayment);
        $interestFmt    = $this->formatAmount($totalInterest);
        $ratePct        = round($rate * 100, 1);

        return "EMI estimate for {$formatted} loan:\n\n"
            ."\u2022 Monthly EMI: **{$emiFormatted}**\n"
            ."\u2022 Tenure: {$years} year".($years > 1 ? 's' : '')." ({$months} months)\n"
            ."\u2022 Interest rate: {$ratePct}%\n"
            ."\u2022 Total payment: {$totalFormatted}\n"
            ."\u2022 Total interest: {$interestFmt}\n\n"
            ."This is an estimate. Actual EMI depends on your credit score and lender. Most banks offer 7.5\u201312% for vehicle loans and 8\u201310% for home loans.";
    }

    private function generatePriceAdvice(string $query, string $contextualQuery): string
    {
        foreach ($this->knownPriceRanges() as $keyword => $advice) {
            if (str_contains($query, $keyword)) {
                return $advice;
            }
        }

        if (preg_match('/([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)?/i', $query, $m) === 1) {
            $amount = $this->amountToNumber((string) ($m[1] ?? ''), (string) ($m[2] ?? ''));
            if ($amount !== null) {
                $formatted = $this->formatAmount($amount);

                return "To judge if {$formatted} is fair, I'd need the item, age, and condition.\n\nGeneral rules:\n• Compare 3–5 similar active listings before deciding\n• Vehicles depreciate ~15–20% per year\n• Electronics lose ~25–30% per year\n• Always negotiate — sellers usually price 5–10% above minimum\n\nTell me what item you're asking about and I'll give you a specific range!";
            }
        }

        return "To give you accurate price advice, tell me the item — for example: \"Is 8 lakh fair for a 2019 Swift?\"\n\nGeneral tips:\n• Compare 3–5 similar listings before deciding\n• Factor in age, condition, and original MRP\n• Vehicles: ~15–20% depreciation per year\n• Electronics: ~25–30% per year\n• Always try to negotiate — most sellers have room";
    }

    private function generateBuyingAdvice(string $query): string
    {
        if (preg_match('/\b(car|cars|vehicle|auto)\b/i', $query) === 1) {
            return "What to check when buying a used car:\n\n1. Documents — RC book, insurance, PUC, service history\n2. Ownership — verify no loan/hypothecation (check RC)\n3. Body & Paint — look for uneven gaps, repainted panels (accident signs)\n4. Engine — start cold, listen for knocking, check for smoke or oil leaks\n5. Test Drive — test brakes, gears, steering at different speeds\n6. Odometer — cross-check with wear on pedals, seat, and steering\n7. RTO Check — verify registration at your local RTO\n\nAlways get a trusted mechanic to inspect before you pay!";
        }

        if (preg_match('/\b(bike|scooter|motorcycle|activa|two.?wheeler)\b/i', $query) === 1) {
            return "What to check when buying a used bike or scooter:\n\n1. Documents — RC book, insurance, PUC certificate\n2. Engine — listen for knocking, check for oil leaks under the engine\n3. Frame — inspect for cracks or bends (signs of accident)\n4. Tyres — check tread depth and uneven wear (alignment issue)\n5. Chain & Sprocket — check for stretch or excessive wear\n6. Lights & Switches — test indicators, headlight, horn\n7. Odometer vs Condition — high mileage on a 2-year scooter is a red flag";
        }

        if (preg_match('/\b(phone|mobile|smartphone|iphone|android)\b/i', $query) === 1) {
            return "What to check when buying a used phone:\n\n1. IMEI Check — verify at imei.info (check if stolen/blacklisted)\n2. Battery Health — iPhone: Settings > Battery Health (aim for 80%+)\n3. Screen — check for dead pixels, burn-in, or yellowing (view in a bright room)\n4. Cameras — test all cameras including selfie and flash\n5. Speakers & Mic — make a test call\n6. Biometrics — Face ID or fingerprint must work reliably\n7. iCloud / Google Account — must be signed out before you buy\n8. Original Box — matching IMEI on box is a good sign";
        }

        if (preg_match('/\b(laptop|computer|mac|macbook)\b/i', $query) === 1) {
            return "What to check when buying a used laptop:\n\n1. Battery — check health (aim for 80%+ capacity remaining)\n2. Screen — look for dead pixels and backlight bleed (test on a dark background)\n3. Keyboard & Trackpad — press every key, check for sticking or rattling\n4. Ports — test USB, HDMI, audio jack\n5. Actual Specs — verify RAM and storage (don't just trust the seller)\n6. Performance — open a browser, play a video, check if it throttles or runs hot\n7. Warranty — check if manufacturer warranty is still active";
        }

        if (preg_match('/\b(flat|apartment|house|property|bhk|villa|rent)\b/i', $query) === 1) {
            return "What to check when buying or renting a property:\n\n1. Legal Documents — title deed, encumbrance certificate, approved plan\n2. Builder — research past projects and reviews\n3. RERA — check your state RERA portal for registered projects\n4. Multiple Visits — visit at different times (day/night) to check lighting and noise\n5. Society Charges — ask about maintenance, parking, water charges\n6. Neighbourhood — proximity to schools, hospitals, metro/bus\n7. Negotiate — property prices are almost always negotiable by 5–15%";
        }

        return "General buying tips for second-hand items:\n\n1. Meet in person at a safe, public place\n2. Inspect thoroughly before paying — never rush\n3. Ask for documents, receipts, or warranty cards\n4. Negotiate — sellers almost always expect it\n5. Avoid advance payments — pay only when you have the item\n6. If a deal seems too cheap, be cautious\n\nTell me what you're buying and I'll give specific advice!";
    }

    private function generateSellingAdvice(string $query): string
    {
        if (preg_match('/\b(car|vehicle|bike|scooter)\b/i', $query) === 1) {
            return "Tips to sell your vehicle faster at a better price:\n\n1. Clean it thoroughly — a clean vehicle fetches 5–10% more\n2. Fix small issues — broken lights or scratches lower perceived value significantly\n3. Gather all documents — RC, insurance, service history build buyer trust\n4. Price right — check 5 similar listings and price slightly below average for quick sale\n5. Great photos — shoot in daylight from all angles including interior and odometer\n6. Be honest — mention any accidents or major repairs, it builds trust and avoids disputes\n7. List on multiple platforms for maximum reach";
        }

        if (preg_match('/\b(phone|laptop|electronics|gadget|mobile)\b/i', $query) === 1) {
            return "Tips to sell electronics quickly:\n\n1. Factory reset — always wipe all data before showing to buyers\n2. Include accessories — original charger, box, and earphones can add 10–20% to value\n3. Be specific in your title — model, storage, colour, age, battery health\n4. Good photos — show screen quality clearly in good lighting\n5. Price at market rate — check recent sold prices, not just listed ones\n6. Reply fast — interested buyers move on quickly; aim to reply within 1 hour";
        }

        return "General tips to sell faster:\n\n1. Clear, specific title — brand, model, condition, and city\n2. Upload 4–6 quality photos in natural daylight from multiple angles\n3. Set a fair price — research 3–5 similar active listings first\n4. Write honestly — describe condition, what's included, and reason for selling\n5. Respond quickly — fast replies build trust\n6. Refresh or bump your listing regularly to stay visible\n\nWant help writing your listing description? Just tell me what you're selling!";
    }

    private function generateComparison(string $query): string
    {
        $hasActiva = stripos($query, 'activa') !== false;
        $hasJupiter = stripos($query, 'jupiter') !== false;
        $hasNtorq = stripos($query, 'ntorq') !== false;
        $hasDio = stripos($query, 'dio') !== false;
        $hasAccess = stripos($query, 'access') !== false;

        if ($hasActiva && ($hasJupiter || $hasNtorq || $hasDio || $hasAccess)) {
            return "Popular scooter comparison:\n\nHonda Activa — Best reliability, widest service network, top resale value. Best choice for daily commuting and long-term ownership.\n\nTVS Jupiter — Slightly better mileage, more storage, great value for money. Good alternative to Activa.\n\nTVS NTORQ — Best for younger buyers. Sportier look, Bluetooth features, fun to ride. Lower resale than Activa.\n\nSuzuki Access — Comfortable seat, good build quality, sporty styling.\n\nFor pure reliability and resale: Activa wins. For features and fun: NTORQ. For budget: Jupiter.";
        }

        $hasIphone = stripos($query, 'iphone') !== false;
        $hasAndroid = preg_match('/samsung|oneplus|pixel|xiaomi|realme|redmi|android/i', $query) === 1;

        if ($hasIphone && $hasAndroid) {
            return "iPhone vs Android — quick comparison:\n\niPhone (iOS)\n✅ Longer software updates (5–6 years)\n✅ Best resale value\n✅ Consistent, smooth performance\n❌ More expensive\n❌ Less customisation\n\nAndroid\n✅ More variety and price options\n✅ Greater flexibility\n✅ Better value in mid-range\n❌ Software updates vary by brand (usually 2–3 years)\n\nFor resale value and longevity: iPhone. For features per rupee: Android. What's your budget range?";
        }

        if (preg_match('/swift.*i10|i10.*swift|swift.*wagnor|wagnor.*swift|swift.*celerio/i', $query) === 1) {
            return "Maruti Swift vs competitors:\n\nMaruti Swift — Most popular hatchback in India. Excellent resale value, great service network, fun to drive. Best choice if resale matters.\n\nHyundai Grand i10/i20 — More features for the price, comfortable ride, slightly better build quality.\n\nMaruti WagonR — More practical space, better ground clearance, preferred for tall buyers. Less sporty.\n\nFor resale and running costs: Swift wins. For space and features: i20.";
        }

        return "I can compare specific items for you! Try asking:\n• \"Activa vs Jupiter — which is better?\"\n• \"iPhone 13 vs Samsung S21\"\n• \"Maruti Swift vs Hyundai i20\"\n\nWhat would you like to compare?";
    }

    private function generateListingHelp(string $query): string
    {
        if (preg_match('/\b(car|cars)\b/i', $query) === 1) {
            return "Here's a template for a great used car listing:\n\nTitle: [Year] [Brand] [Model] [Variant] — [Fuel] | [km] km | [City]\nExample: 2019 Maruti Swift VXi Petrol | 32,000 km | Pune\n\nDescription should include:\n• Year of purchase and registration\n• Fuel type and transmission (manual/automatic)\n• Total kilometres driven\n• Number of previous owners\n• Insurance validity date\n• Any accessories or modifications\n• Reason for selling\n• Recent service or repairs done\n\nPhotos to take: front, rear, both sides, dashboard, odometer, seats, engine bay, boot\n\nTell me your car's details and I'll write the full description for you!";
        }

        if (preg_match('/\b(bike|scooter|activa|motorcycle)\b/i', $query) === 1) {
            return "Great bike listing template:\n\nTitle: [Year] [Brand] [Model] — [km] km | [Condition] | [City]\nExample: 2021 Honda Activa 6G — 18,000 km | Excellent | Bangalore\n\nDescription should include:\n• Year of purchase\n• Total kilometres driven\n• Number of owners\n• Insurance validity\n• Any scratches or damages (be honest)\n• Accessories included (lock, cover, etc.)\n• Reason for selling\n\nTell me your bike's details and I'll draft the description!";
        }

        if (preg_match('/\b(phone|mobile|iphone|smartphone)\b/i', $query) === 1) {
            return "Great used phone listing template:\n\nTitle: [Brand] [Model] [Storage]GB [Colour] — [Condition] | [City]\nExample: iPhone 13 128GB Midnight — Excellent Condition | Mumbai\n\nDescription should include:\n• Purchase date or age\n• Battery health percentage\n• Any scratches or damage (be honest — it attracts serious buyers)\n• Accessories included (original box, charger, case)\n• Bill or warranty card availability\n• Reason for selling\n\nBeing upfront about condition gets you faster, more serious inquiries!";
        }

        return "For a great listing, always include:\n\n1. Clear title — Brand + Model + Condition + City\n   Example: Sony 43\" 4K Smart TV — Good Condition | Delhi\n2. Honest description — age, condition, what's included, why you're selling\n3. Key specs — whatever matters most for that item\n4. Your asking price — listings with prices get far more responses\n5. 4–6 photos in good natural light from multiple angles\n\nTell me what you're selling and I'll write a sample description for you!";
    }

    /**
     * @return array<string, string>
     */
    private function knownPriceRanges(): array
    {
        return [
            'maruti swift'    => "Used Maruti Swift price guide (India):\n\n• 2015–2017 (7–9 yrs): ₹2.5 – 4.5 lakh\n• 2018–2020 (4–6 yrs): ₹4.5 – 6.5 lakh\n• 2021–2023 (1–3 yrs): ₹6.5 – 9 lakh\n\nAutomatic variants cost 15–20% more. First-owner cars with full service history command a premium.",
            'honda activa'    => "Used Honda Activa price guide:\n\n• Activa 5G/older (pre-2019): ₹25,000 – 45,000\n• Activa 6G (2019–2021): ₹45,000 – 65,000\n• Activa 6G/125 (2022+): ₹65,000 – 80,000\n\nActiva holds value better than most scooters — great resale.",
            'iphone 13'       => "Used iPhone 13 price guide (India):\n\n• 128GB: ₹40,000 – 55,000\n• 256GB: ₹50,000 – 65,000\n\nBattery health below 80% → negotiate ₹3,000–5,000 down.",
            'iphone 12'       => "Used iPhone 12 price guide (India):\n\n• 64GB: ₹28,000 – 38,000\n• 128GB: ₹33,000 – 45,000\n\nCheck battery health — aim for 80%+.",
            'iphone 14'       => "Used iPhone 14 price guide (India):\n\n• 128GB: ₹55,000 – 70,000\n• 256GB: ₹65,000 – 80,000\n\nPro models cost ₹10,000–20,000 more.",
            'iphone 15'       => "Used iPhone 15 price guide (India):\n\n• 128GB: ₹65,000 – 80,000\n• 256GB: ₹75,000 – 95,000\n\nBeing newer, prices are still close to retail — negotiate based on battery health and accessories included.",
            'royal enfield'   => "Used Royal Enfield price guide:\n\n• Classic 350 (pre-2021): ₹1 – 1.3 lakh\n• Classic 350 (2021+): ₹1.5 – 2 lakh\n• Meteor 350: ₹1.5 – 2 lakh\n• Himalayan: ₹1.5 – 2.2 lakh\n• Hunter 350: ₹1.4 – 1.8 lakh\n\nRE bikes hold value well — better resale than most brands.",
            'honda city'      => "Used Honda City price guide:\n\n• 4th Gen (2014–2017): ₹5 – 8 lakh\n• 5th Gen (2017–2020): ₹8 – 12 lakh\n• 6th Gen (2020+): ₹11 – 16 lakh\n\nPetrol and diesel variants priced similarly; automatic adds ₹1–1.5 lakh.",
            'hyundai i20'     => "Used Hyundai i20 price guide:\n\n• 2016–2018: ₹4 – 6 lakh\n• 2019–2021: ₹6 – 9 lakh\n• 2022+: ₹9 – 13 lakh\n\nAutomatic variants fetch 10–15% premium.",
            'samsung'         => "Used Samsung price guide:\n\n• A-series (A32/A52): ₹8,000 – 18,000\n• A53/A54: ₹15,000 – 22,000\n• S21: ₹25,000 – 35,000\n• S22: ₹35,000 – 50,000\n• S23: ₹45,000 – 65,000\n\nSpecify the exact model for a tighter range.",
            'redmi'           => "Used Redmi/Xiaomi price guide:\n\n• Entry (Redmi 9/10/12): ₹5,000 – 9,000\n• Note 11/12: ₹9,000 – 14,000\n• Note 13 series: ₹13,000 – 20,000\n\nAlways check IMEI at imei.info before buying.",
            'oneplus'         => "Used OnePlus price guide:\n\n• OnePlus Nord CE2/CE3: ₹12,000 – 18,000\n• OnePlus 10T: ₹22,000 – 30,000\n• OnePlus 11: ₹30,000 – 40,000\n• OnePlus 12: ₹42,000 – 55,000\n\nOnePlus holds value reasonably well in the premium Android segment.",
        ];
    }

    /**
     * @param  string  $locationSource  Use current (non-merged) query for location to avoid history pollution
     * @return array<string, mixed>
     */
    private function parseIntent(string $query, array $context = [], string $locationSource = ''): array
    {
        $lower = strtolower($query);
        $locText = $locationSource !== '' ? $locationSource : $query;
        $focusLower = strtolower($locText);

        $budgetMax = null;
        if (preg_match('/under\s+([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)?/i', $query, $matches) === 1) {
            $budgetMax = $this->amountToNumber($matches[1] ?? '', $matches[2] ?? '');
        } elseif (preg_match('/([0-9,.]+)\s*(lakh|lac|k|thousand|crore|cr)\s*(?:max|budget)?/i', $query, $matches) === 1) {
            $budgetMax = $this->amountToNumber($matches[1] ?? '', $matches[2] ?? '');
        }

        $bedrooms = null;
        if (preg_match('/(\d+)\s*(bhk|bed|bedroom)/i', $query, $matches) === 1) {
            $bedrooms = (int) ($matches[1] ?? 0);
        }

        // Location: parsed from CURRENT query only ($locText) to avoid history pollution.
        // Regex limited to 1-2 words; stops at budget/verb keywords and digits.
        $location = '';
        if (preg_match('/\bin\s+([a-zA-Z]+(?:\s+[a-zA-Z]+)?)/i', $locText, $matches) === 1) {
            $location = trim((string) ($matches[1] ?? ''));
            // Strip at any budget/common-verb word
            $location = preg_replace('/\s+\b(under|over|max|near|with|and|for|find|show|buy|sell|get|the|a|an|is|are)\b.*$/i', '', $location) ?? $location;
            // Strip at first digit (e.g. "15 lac")
            $location = preg_replace('/\s+\d.*$/', '', $location) ?? $location;
            $location = trim($location);
            // Sanity: discard if it looks like a verb or too long
            $nonPlaceWords = ['find', 'show', 'search', 'buy', 'sell', 'the', 'get', 'need', 'want', 'help'];
            if (in_array(strtolower($location), $nonPlaceWords, true) || str_word_count($location) > 3) {
                $location = '';
            }
        }

        if ($location === '') {
            $location = $this->inferLocationFromQuery($locText);
        }

        $near = '';
        if (preg_match('/\bnear\s+([a-zA-Z\s]{2,40})/i', $query, $matches) === 1) {
            $near = trim((string) ($matches[1] ?? ''));
            $near = preg_replace('/\b(under|max|in|with|and)\b.*$/i', '', $near) ?? $near;
            $near = trim($near);
        }

        $nearIsMe = in_array(strtolower($near), ['me', 'my location', 'my place', 'here', 'nearby'], true);
        if ($nearIsMe) {
            $near = '';
        }

        // Detect explicit global-search intent — user wants results from anywhere.
        $globalSearch = preg_match(
            '/\b(all\s+india|pan\s+india|anywhere|globally|all\s+over|any\s+city|any\s+location|any\s+where|nationwide|sabhi\s+jagah|poore\s+india)\b/i',
            $query
        ) === 1;

        // If global, clear any location that was parsed above.
        if ($globalSearch) {
            $location = '';
            $near     = '';
        }

        $contextLocation = trim((string) ($context['location_label'] ?? ''));
        // Only use context location if it's a real place name (not a generic "Near you" label)
        $isGenericNearLabel = preg_match('/^\s*(near\s+you|near\s+me|nearby|your\s+location)\s*$/i', $contextLocation) === 1;
        $mentionsNearMe = preg_match('/\b(near me|around me|nearby)\b/i', $query) === 1;
        if (! $globalSearch && $location === '' && $contextLocation !== '' && ! $isGenericNearLabel && ($nearIsMe || $mentionsNearMe)) {
            $location = $contextLocation;
        }

        $propertyCandidates = ['flat', 'apartment', 'house', 'villa', 'plot', 'land', 'commercial', 'office', 'shop'];
        $propertyType = $this->detectLatestCandidate($focusLower, $propertyCandidates);
        if ($propertyType === '') {
            $propertyType = $this->detectLatestCandidate($lower, $propertyCandidates);
        }

        // Detect item type from current query first (prevents stale history from overriding follow-ups).
        $itemCandidates = [
            'iphone', 'phone', 'phones', 'mobile', 'smartphone', 'android',
            'laptop', 'laptops', 'computer', 'computers',
            'car', 'cars', 'vehicle', 'auto',
            'bike', 'bikes', 'scooter', 'scooters', 'motorcycle',
            'truck', 'trucks', 'tractor', 'tractors',
            'tv', 'television', 'furniture', 'sofa', 'fridge', 'refrigerator',
            'ac', 'air conditioner', 'washing machine',
        ];
        $itemType = $this->detectLatestCandidate($focusLower, $itemCandidates);
        if ($itemType === '') {
            $itemType = $this->detectLatestCandidate($lower, $itemCandidates);
        }

        // Canonicalize common aliases.
        if (in_array($itemType, ['iphone', 'mobile', 'phones', 'smartphone', 'android'], true)) {
            $itemType = 'phone';
        }
        if (in_array($itemType, ['cars', 'vehicle', 'auto'], true)) {
            $itemType = 'car';
        }
        if (in_array($itemType, ['bikes', 'scooters', 'motorcycle'], true)) {
            $itemType = 'bike';
        }
        if (in_array($itemType, ['laptops', 'computer', 'computers'], true)) {
            $itemType = 'laptop';
        }
        if (in_array($itemType, ['trucks'], true)) {
            $itemType = 'truck';
        }
        if (in_array($itemType, ['tractors'], true)) {
            $itemType = 'tractor';
        }
        if (in_array($itemType, ['television'], true)) {
            $itemType = 'tv';
        }
        if (in_array($itemType, ['refrigerator'], true)) {
            $itemType = 'fridge';
        }
        if ($itemType === 'air conditioner') {
            $itemType = 'ac';
        }

        return [
            'budget_max'    => $budgetMax,
            'bedrooms'      => $bedrooms,
            'location'      => $location,
            'near'          => $near,
            'property_type' => $propertyType,
            'item_type'     => $itemType,
            'global_search' => $globalSearch,
        ];
    }

    /**
     * Picks the latest matching whole-word candidate in text.
     * This helps follow-up queries override older context terms.
     *
     * @param  array<int, string>  $candidates
     */
    private function detectLatestCandidate(string $text, array $candidates): string
    {
        if ($text === '' || $candidates === []) {
            return '';
        }

        $latest = '';
        $latestOffset = -1;

        foreach ($candidates as $candidate) {
            $needle = trim((string) $candidate);
            if ($needle === '') {
                continue;
            }

            $pattern = '/\\b'.preg_quote($needle, '/').'\\b/i';
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $offset = (int) ($match[1] ?? -1);
                if ($offset > $latestOffset) {
                    $latestOffset = $offset;
                    $latest = $needle;
                }
            }
        }

        return $latest;
    }

    /**
     * Build contextual query from history — only include search-like turns,
     * not conversational ones (greetings, advice questions, thanks, etc.).
     *
     * @param  array<int, array<string, string>>  $history
     */
    private function buildContextualQuery(string $query, array $history): string
    {
        // Only include turns that look like product searches or have budget/location/item info.
        // This prevents "how to sell my car" from polluting "find car in Bengaluru".
        $searchSignals = '/\b(find|search|show|want|buy|under|budget|lakh|lac|crore|looking|cheap|best|deal|affordable|price|cheap|need\s+a?\s+\w)\b/i';

        $parts = [];

        foreach (array_slice($history, -8) as $turn) {
            $role = strtolower(trim((string) ($turn['role'] ?? '')));
            if ($role !== 'user') {
                continue;
            }

            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if (preg_match($searchSignals, $content) === 1) {
                $parts[] = $content;
            }
        }

        $parts[] = $query;

        $merged = trim(implode(' ', array_filter($parts, static fn ($value): bool => trim((string) $value) !== '')));

        if ($merged === '') {
            return $query;
        }

        return mb_substr($merged, 0, 600);
    }

    private function inferLocationFromQuery(string $query): string
    {
        $normalizedQuery = $this->normalizeTokenText($query);
        if ($normalizedQuery === '') {
            return '';
        }

        static $cityCandidates = null;
        static $stateCandidates = null;

        if ($cityCandidates === null) {
            $cityCandidates = Listing::query()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->pluck('city')
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => strlen($value) >= 3)
                ->unique()
                ->sortByDesc(static fn (string $value): int => strlen($value))
                ->values()
                ->all();
        }

        if ($stateCandidates === null) {
            $stateCandidates = Listing::query()
                ->whereNotNull('state')
                ->where('state', '!=', '')
                ->distinct()
                ->pluck('state')
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => strlen($value) >= 3)
                ->unique()
                ->sortByDesc(static fn (string $value): int => strlen($value))
                ->values()
                ->all();
        }

        foreach ($cityCandidates as $city) {
            if ($this->containsWholePhrase($normalizedQuery, $city)) {
                return $city;
            }
        }

        foreach ($stateCandidates as $state) {
            if ($this->containsWholePhrase($normalizedQuery, $state)) {
                return $state;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array<int, array<string, mixed>>
     */
    private function searchListings(array $intent, string $fallbackQuery): array
    {
        $categoryIds = $this->inferCategoryIds($fallbackQuery);
        $keywords    = $this->extractSearchKeywords($fallbackQuery, $intent);
        $outputLimit   = max(1, min(20, (int) setting('ai_compass_max_results', 5)));
        $candidateLimit = min(120, max(24, $outputLimit * 6));

        $location     = trim((string) ($intent['location'] ?? ''));
        $near         = trim((string) ($intent['near'] ?? ''));
        $bedrooms     = (int) ($intent['bedrooms'] ?? 0);
        $propertyType = trim((string) ($intent['property_type'] ?? ''));
        $itemType     = trim((string) ($intent['item_type'] ?? ''));
        $budgetMax    = isset($intent['budget_max']) ? (float) ($intent['budget_max'] ?? 0) : null;

        /**
         * Apply base filters (budget, location, near, bedrooms) shared by all levels.
         *
         * @param \Illuminate\Database\Eloquent\Builder<Listing> $b
         */
        $applyBase = function ($b) use ($budgetMax, $location, $near, $bedrooms): void {
            if ($budgetMax !== null && $budgetMax > 0) {
                $b->where('price', '<=', $budgetMax);
            }
            if ($location !== '') {
                $b->where(function ($n) use ($location): void {
                    $n->where('city', 'like', '%'.$location.'%')
                      ->orWhere('state', 'like', '%'.$location.'%')
                      ->orWhere('address', 'like', '%'.$location.'%');
                });
            }
            if ($near !== '') {
                $b->where(function ($n) use ($near): void {
                    $n->where('address', 'like', '%'.$near.'%')
                      ->orWhere('description', 'like', '%'.$near.'%');
                });
            }
            if ($bedrooms > 0) {
                $needle = $bedrooms.'bhk';
                $b->where(function ($n) use ($needle, $bedrooms): void {
                    $n->whereRaw('LOWER(title) like ?', ['%'.$needle.'%'])
                      ->orWhereRaw('LOWER(description) like ?', ['%'.$needle.'%'])
                      ->orWhereRaw('LOWER(title) like ?', ['%'.$bedrooms.' bedroom%'])
                      ->orWhereRaw('LOWER(description) like ?', ['%'.$bedrooms.' bedroom%']);
                });
            }
        };

        /**
         * Apply item-type / property-type content filter.
         *
         * @param \Illuminate\Database\Eloquent\Builder<Listing> $b
         */
        $applyTypeFilter = function ($b) use ($itemType, $propertyType): void {
            if ($propertyType !== '') {
                $b->where(function ($n) use ($propertyType): void {
                    $n->whereRaw('LOWER(title) like ?', ['%'.$propertyType.'%'])
                      ->orWhereRaw('LOWER(description) like ?', ['%'.$propertyType.'%']);
                });
            }
            if ($itemType !== '') {
                $b->where(function ($n) use ($itemType): void {
                    $n->whereRaw('LOWER(title) like ?', ['%'.strtolower($itemType).'%'])
                      ->orWhereRaw('LOWER(description) like ?', ['%'.strtolower($itemType).'%'])
                      ->orWhereHas('category', function (Builder $cq) use ($itemType): void {
                          $cq->whereRaw('LOWER(name) like ?', ['%'.strtolower($itemType).'%']);
                      });
                });
            }
        };

        // ── Level 1: Full structured search ─────────────────────────────────
        // category + type filters + base filters + keywords (all AND)
        $listings = Listing::query()->approved()->with(['category', 'images'])
            ->when($categoryIds !== [], fn ($b) => $b->whereIn('category_id', $categoryIds))
            ->tap($applyBase)
            ->tap($applyTypeFilter)
            ->when($keywords !== [], fn (Builder $b) => $this->applyKeywordMatching($b, $keywords))
            ->latest('published_at')->limit($candidateLimit)->get();

        // ── Level 2: Drop keywords, keep category + type + base ───────────
        if ($listings->isEmpty() && $keywords !== []) {
            $listings = Listing::query()->approved()->with(['category', 'images'])
                ->when($categoryIds !== [], fn ($b) => $b->whereIn('category_id', $categoryIds))
                ->tap($applyBase)
                ->tap($applyTypeFilter)
                ->latest('published_at')->limit($candidateLimit)->get();
        }

        // ── Level 3: Pure keyword search — ALL categories, no type filter ──
        // Useful when item_type / category detection missed the mark.
        if ($listings->isEmpty() && $keywords !== []) {
            $listings = Listing::query()->approved()->with(['category', 'images'])
                ->tap($applyBase)
                ->tap(fn (Builder $b) => $this->applyKeywordMatching($b, $keywords))
                ->latest('published_at')->limit($candidateLimit)->get();
        }

        // ── Level 4: Keyword search dropping location filter ─────────────
        // Widens the net when no listings exist in that city yet.
        if ($listings->isEmpty() && $keywords !== [] && $location !== '') {
            $listings = Listing::query()->approved()->with(['category', 'images'])
                ->when($budgetMax !== null && $budgetMax > 0, fn ($b) => $b->where('price', '<=', $budgetMax))
                ->tap(fn (Builder $b) => $this->applyKeywordMatching($b, $keywords))
                ->latest('published_at')->limit($candidateLimit)->get();
        }

        // ── Level 5: Category + base only (no keywords, no type text) ────
        if ($listings->isEmpty() && $categoryIds !== []) {
            $listings = Listing::query()->approved()->with(['category', 'images'])
                ->whereIn('category_id', $categoryIds)
                ->tap($applyBase)
                ->latest('published_at')->limit($candidateLimit)->get();
        }

        if ($listings->isEmpty()) {
            return [];
        }

        $rankedListings = $listings
            ->map(function (Listing $listing) use ($keywords): array {
                return [
                    'listing' => $listing,
                    'relevance' => $this->scoreListingRelevance($listing, $keywords),
                    'freshness' => $listing->published_at?->getTimestamp() ?? 0,
                ];
            })
            ->sort(function (array $a, array $b): int {
                if ($a['relevance'] !== $b['relevance']) {
                    return $b['relevance'] <=> $a['relevance'];
                }

                return $b['freshness'] <=> $a['freshness'];
            })
            ->pluck('listing')
            ->take($outputLimit)
            ->values();

        $medianPrice = $rankedListings->pluck('price')->map(static fn ($price): float => (float) $price)->sort()->values();
        $median = $this->median($medianPrice->all());

        $recommendations = [];
        foreach ($rankedListings as $listing) {
            $pros = [];
            $tradeoffs = [];

            $price = (float) $listing->price;
            if ($median > 0 && $price <= $median) {
                $pros[] = 'Below median price in this result set';
            }
            if ($listing->city !== '') {
                $pros[] = 'Located in '.$listing->city;
            }
            if ($listing->views > 400) {
                $tradeoffs[] = 'High demand listing may close quickly';
            }
            if ($listing->address === null || trim((string) $listing->address) === '') {
                $tradeoffs[] = 'Exact locality details are limited';
            }

            $recommendations[] = [
                'listing_id' => $listing->id,
                'slug' => $listing->slug,
                'title' => $listing->title,
                'price' => $price,
                'city' => $listing->city,
                'state' => $listing->state,
                'address' => $listing->address,
                'summary' => mb_substr(trim((string) $listing->description), 0, 180),
                'pros' => $pros,
                'tradeoffs' => $tradeoffs,
            ];
        }

        return $recommendations;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function scoreListingRelevance(Listing $listing, array $keywords): int
    {
        if ($keywords === []) {
            return 0;
        }

        $title = mb_strtolower((string) $listing->title);
        $description = mb_strtolower((string) ($listing->description ?? ''));
        $category = mb_strtolower((string) ($listing->category?->name ?? ''));
        $city = mb_strtolower((string) ($listing->city ?? ''));
        $state = mb_strtolower((string) ($listing->state ?? ''));
        $address = mb_strtolower((string) ($listing->address ?? ''));

        $score = 0;

        foreach ($keywords as $keyword) {
            $kw = mb_strtolower(trim($keyword));
            if ($kw === '') {
                continue;
            }

            // Strongest signal: exact whole-word keyword hit in title.
            if ($this->containsWholeWord($title, $kw)) {
                $score += 18;
                continue;
            }

            if (str_contains($title, $kw)) {
                $score += 12;
                continue;
            }

            $matchedFallbackField = false;

            if ($this->containsWholeWord($description, $kw) || str_contains($description, $kw)) {
                $score += 7;
                $matchedFallbackField = true;
            }

            if ($this->containsWholeWord($category, $kw) || str_contains($category, $kw)) {
                $score += 6;
                $matchedFallbackField = true;
            }

            if (str_contains($city, $kw) || str_contains($state, $kw) || str_contains($address, $kw)) {
                $score += 5;
                $matchedFallbackField = true;
            }

            if (! $matchedFallbackField) {
                $score -= 1;
            }
        }

        return $score;
    }

    private function containsWholeWord(string $text, string $keyword): bool
    {
        if ($text === '' || $keyword === '') {
            return false;
        }

        return preg_match('/\\b'.preg_quote($keyword, '/').'\\b/u', $text) === 1;
    }

    private function containsWholePhrase(string $query, string $candidate): bool
    {
        $normalizedCandidate = $this->normalizeTokenText($candidate);
        if ($query === '' || $normalizedCandidate === '') {
            return false;
        }

        return str_contains(' '.$query.' ', ' '.$normalizedCandidate.' ');
    }

    private function normalizeTokenText(string $value): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', ' ', mb_strtolower($value)) ?? '';
        $collapsed = preg_replace('/\s+/', ' ', trim($clean)) ?? '';

        return trim($collapsed);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<int, array<string, mixed>>  $recommendations
     * @param  array<int, array<string, string>>  $history
     */
    private function buildSearchSummary(string $query, array $intent, array $recommendations, array $history, string $contextualQuery, string $languageMode = 'en'): string
    {
        if ($recommendations === []) {
            return $this->buildNoResultsResponse($query, $intent, $history, $languageMode);
        }

        $budget = isset($intent['budget_max']) && is_numeric($intent['budget_max'])
            ? ' under '.$this->formatAmount((float) $intent['budget_max'])
            : '';

        $location     = trim((string) ($intent['location'] ?? ''));
        $isGlobal     = (bool) ($intent['global_search'] ?? false);
        $locationText = $location !== '' ? ' in '.$location : ($isGlobal ? ' across India' : '');
        $count  = count($recommendations);
        $plural = $count > 1 ? 's' : '';

        $options = [
            "Found {$count} listing{$plural}{$budget}{$locationText}. Here are the top matches.",
            "Here ".($count === 1 ? 'is 1 result' : "are {$count} results")."{$budget}{$locationText} — sorted by relevance.",
            "I found {$count} match{$plural}{$budget}{$locationText}. Check them out below.",
        ];

        if ($languageMode === 'hi' || $languageMode === 'hinglish') {
            $suffix = $isGlobal ? ' (poore India mein)' : ($location !== '' ? " ({$location} mein)" : '');
            $options = [
                "{$count} result mila{$plural}{$suffix}. Neeche best matches diye hain.",
                "Maine {$count} match{$plural} dhoonde{$plural}{$suffix}. Aap compare kar sakte ho.",
                "Yeh {$count} top listing{$plural} aapke query ke hisaab se best hain{$suffix}.",
            ];
        }

        return $options[array_rand($options)];
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<int, array<string, string>>  $history
     */
    private function buildNoResultsResponse(string $query, array $intent, array $history, string $languageMode = 'en'): string
    {
        $itemType = trim((string) ($intent['item_type'] ?? ''));
        $propertyType = trim((string) ($intent['property_type'] ?? ''));
        $location = trim((string) ($intent['location'] ?? ''));
        $budgetMax = $intent['budget_max'] ?? null;

        // Detect a bare follow-up: short message, no item keyword, looks like a filter refinement
        $hasItemKeyword = preg_match('/\b(car|bike|phone|mobile|laptop|flat|house|apartment|iphone|samsung|scooter|truck)\b/i', $query) === 1;
        $isFollowUp = count($history) > 3 && mb_strlen($query) < 20 && ! $hasItemKeyword;
        if ($isFollowUp) {
            if ($languageMode === 'hi' || $languageMode === 'hinglish') {
                return 'Abhi bhi match nahi mila. Location filter hata kar ya budget thoda badha kar try karein.';
            }

            return "Still no matches with those filters. Try widening the search — remove the city filter or raise the budget a little.";
        }

        $what = $itemType !== '' ? $itemType : ($propertyType !== '' ? $propertyType : 'what you\'re looking for');

        $filterParts = [];
        if ($location !== '') {
            $filterParts[] = $location;
        }
        if ($budgetMax !== null) {
            $filterParts[] = 'budget '.$this->formatAmount((float) $budgetMax);
        }
        $filterText = $filterParts !== [] ? ' ('.implode(', ', $filterParts).')' : '';

        $tips = [];
        if ($location !== '') {
            $tips[] = 'Try a nearby city or search without a location filter';
        }
        if ($budgetMax !== null) {
            $tips[] = 'A slightly higher budget may unlock more listings';
        }
        if ($itemType !== '') {
            $tips[] = 'Try a brand or model name (e.g. "Swift" instead of "car")';
        }
        $tips[] = 'New listings are added daily — check back soon';

        $bulletList = implode("\n", array_map(static fn (string $tip): string => '• '.$tip, $tips));

        if ($languageMode === 'hi' || $languageMode === 'hinglish') {
            return "{$what} ke listing abhi nahi mile{$filterText}.\n\n{$bulletList}";
        }

        return "No {$what} listings found{$filterText} right now.\n\n{$bulletList}";
    }

    private function formatAmount(float $amount): string
    {
        if ($amount >= 10000000) {
            return round($amount / 10000000, 1).' crore';
        }
        if ($amount >= 100000) {
            return round($amount / 100000, 1).' lakh';
        }
        if ($amount >= 1000) {
            return round($amount / 1000, 1).'k';
        }

        return '₹'.number_format((int) $amount);
    }

    /**
     * Dynamically match ALL categories from the DB against the query keywords.
     * Falls back to an empty array (no category filter) only when nothing matches.
     *
     * @return array<int, int>
     */
    private function inferCategoryIds(string $query): array
    {
        // Aliases: map common user terms → canonical search tokens used in category names.
        static $aliasMap = [
            // vehicles
            'cars'         => 'car',
            'vehicle'      => 'car',
            'vehicles'     => 'car',
            'auto'         => 'car',
            'bikes'        => 'bike',
            'motorcycle'   => 'bike',
            'motorcycles'  => 'bike',
            'scooters'     => 'scooter',
            'trucks'       => 'truck',
            'tractors'     => 'tractor',
            // electronics
            'phones'       => 'phone',
            'iphone'       => 'phone',
            'android'      => 'phone',
            'smartphone'   => 'phone',
            'smartphones'  => 'phone',
            'mobile'       => 'phone',
            'mobiles'      => 'phone',
            'laptops'      => 'laptop',
            'computers'    => 'computer',
            'television'   => 'tv',
            'televisions'  => 'tv',
            'refrigerator' => 'fridge',
            'refrigerators'=> 'fridge',
            'ac'           => 'air conditioner',
            'acs'          => 'air conditioner',
            // property
            'flats'        => 'flat',
            'apartments'   => 'apartment',
            'houses'       => 'house',
            'villas'       => 'villa',
            'plots'        => 'plot',
            'lands'        => 'land',
            'real estate'  => 'property',
            // furniture / home
            'sofas'        => 'sofa',
            'chairs'       => 'chair',
            'tables'       => 'table',
            'beds'         => 'bed',
            // services / jobs / other
            'jobs'         => 'job',
            'services'     => 'service',
            'pets'         => 'pet',
            'dogs'         => 'pet',
            'cats'         => 'pet',
            'books'        => 'book',
            'clothes'      => 'clothing',
            'clothing'     => 'clothing',
            'shoes'        => 'footwear',
            'footwear'     => 'footwear',
            'sports'       => 'sports',
            'cycles'       => 'cycle',
            'bicycle'      => 'cycle',
            'bicycles'     => 'cycle',
        ];

        // Words that should never be used to look up a category.
        static $catStopwords = [
            'find', 'show', 'search', 'looking', 'need', 'want', 'buy', 'sell', 'get',
            'in', 'near', 'under', 'over', 'between', 'for', 'with', 'and', 'or',
            'a', 'an', 'the', 'my', 'me', 'to', 'of', 'on', 'at', 'is', 'are',
            'please', 'i', 'we', 'you', 'he', 'she', 'it', 'they',
            'can', 'do', 'did', 'will', 'would', 'could', 'should',
            'good', 'best', 'cheap', 'affordable', 'used', 'old', 'new', 'second', 'hand',
            'lac', 'lakh', 'crore', 'rs', 'rupees', 'price', 'budget', 'max',
            'koi', 'kya', 'hai', 'ko', 'ka', 'ke', 'ki', 'se', 'mein', 'main',
        ];

        // Build a deduplicated list of search tokens from the query.
        $parts = preg_split('/[^a-zA-Z0-9]+/', strtolower($query)) ?: [];
        $searchTokens = [];
        foreach ($parts as $part) {
            $token = trim($part);
            if ($token === '' || strlen($token) < 2 || in_array($token, $catStopwords, true)) {
                continue;
            }
            $resolved = $aliasMap[$token] ?? $token;
            $searchTokens[$resolved] = true;
        }
        // Also check two-word phrases (e.g. "real estate", "air conditioner", "washing machine").
        $lowerQuery = strtolower($query);
        foreach ($aliasMap as $phrase => $resolved) {
            if (str_word_count($phrase) > 1 && str_contains($lowerQuery, $phrase)) {
                $searchTokens[$resolved] = true;
            }
        }

        if ($searchTokens === []) {
            return [];
        }

        $tokens = array_keys($searchTokens);

        // Load all categories once (tiny table, safe to load fully).
        /** @var array<int, array{id: int, name: string, slug: string}> $allCategories */
        static $allCategories = null;
        if ($allCategories === null) {
            $allCategories = Category::query()
                ->select(['id', 'name', 'slug'])
                ->get()
                ->map(static fn ($c): array => [
                    'id'   => (int) $c->id,
                    'name' => mb_strtolower(trim((string) $c->name)),
                    'slug' => mb_strtolower(trim((string) ($c->slug ?? ''))),
                ])
                ->all();
        }

        $matchedIds = [];
        foreach ($allCategories as $cat) {
            foreach ($tokens as $token) {
                if (str_contains($cat['name'], $token) || str_contains($cat['slug'], $token)) {
                    $matchedIds[$cat['id']] = true;
                    break; // one match per category is enough
                }
            }
        }

        return array_keys($matchedIds);
    }

    /**
     * Extract meaningful search keywords from the query.
     * Strips stopwords, location words already captured in intent, and numeric tokens.
     *
     * @param  array<string, mixed>  $intent
     * @return array<int, string>
     */
    private function extractSearchKeywords(string $query, array $intent = []): array
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', strtolower($query)) ?: [];

        // Command / filler words that carry no search value.
        $stopwords = [
            'find', 'show', 'search', 'looking', 'need', 'want',
            'in', 'near', 'under', 'over', 'between', 'for', 'with', 'and', 'or',
            'a', 'an', 'the', 'my', 'me', 'to', 'of', 'on', 'at', 'is', 'are',
            'please', 'i', 'we', 'you', 'he', 'she', 'it', 'they',
            'can', 'do', 'did', 'will', 'would', 'could', 'should', 'get', 'got',
            'lac', 'lakh', 'crore', 'rs', 'rupees',
            // Hinglish filler
            'koi', 'kya', 'hai', 'hain', 'ko', 'ka', 'ke', 'ki', 'se', 'mein', 'main',
        ];

        // Also strip location/place tokens already parsed — they are used as DB filters,
        // including them as content keywords adds noise and slows scoring.
        $locationTokens = [];
        foreach (['location', 'near'] as $field) {
            $val = strtolower(trim((string) ($intent[$field] ?? '')));
            if ($val !== '') {
                foreach (preg_split('/\s+/', $val) ?: [] as $t) {
                    if ($t !== '') {
                        $locationTokens[$t] = true;
                    }
                }
            }
        }

        $keywords = [];
        foreach ($parts as $part) {
            $token = trim($part);
            if ($token === ''
                || strlen($token) < 2
                || in_array($token, $stopwords, true)
                || isset($locationTokens[$token])
                || is_numeric($token)
            ) {
                continue;
            }
            $keywords[] = $token;
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function applyKeywordMatching(Builder $query, array $keywords): void
    {
        $query->where(function (Builder $outer) use ($keywords): void {
            foreach ($keywords as $keyword) {
                $like = '%'.$keyword.'%';
                $outer->orWhere(function (Builder $inner) use ($like): void {
                    $inner
                        ->whereRaw('LOWER(title) like ?', [$like])
                        ->orWhereRaw('LOWER(description) like ?', [$like])
                        ->orWhereRaw('LOWER(city) like ?', [$like])
                        ->orWhereRaw('LOWER(state) like ?', [$like])
                        ->orWhereRaw('LOWER(address) like ?', [$like])
                        ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                            $categoryQuery->whereRaw('LOWER(name) like ?', [$like]);
                        });
                });
            }
        });
    }

    private function amountToNumber(string $amount, string $unit): ?float
    {
        $raw = str_replace(',', '', trim($amount));
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        $normalizedUnit = strtolower(trim($unit));

        return match ($normalizedUnit) {
            'lakh', 'lac' => $value * 100000,
            'crore', 'cr' => $value * 10000000,
            'k', 'thousand' => $value * 1000,
            default => $value,
        };
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}

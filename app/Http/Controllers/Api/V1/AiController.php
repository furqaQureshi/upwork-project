<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SubscriptionPackagePurchase;
use App\Services\AI\AiGatewayService;
use App\Services\SubscriptionPackages\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends \App\Http\Controllers\AiController
{
	public function moderationPrecheck(
		Request $request,
		AiGatewayService $gateway,
		SubscriptionEntitlementService $entitlementService
	): JsonResponse {
		if (! ((bool) setting('ai_listing_assistant_enabled', true))) {
			return response()->json([
				'ok' => false,
				'message' => 'AI moderation pre-check is disabled.',
			], 403);
		}

		$validated = $request->validate([
			'title' => ['required', 'string', 'max:140'],
			'description' => ['required', 'string', 'max:5000'],
			'category_id' => ['nullable', 'integer', 'exists:categories,id'],
			'price' => ['nullable', 'numeric', 'min:0'],
			'condition' => ['nullable', 'string', 'in:new,used,refurbished'],
			'city' => ['nullable', 'string', 'max:120'],
		]);

		$riskScore = 0;
		$reasons = [];
		$text = strtolower(trim((string) $validated['title'].' '.(string) $validated['description']));

		$blockedKeywords = [
			'weapon', 'gun', 'pistol', 'rifle', 'explosive', 'narcotic', 'drugs',
			'counterfeit', 'fake id', 'forged', 'stolen', 'hacked account',
		];

		foreach ($blockedKeywords as $keyword) {
			if (str_contains($text, $keyword)) {
				$riskScore += 30;
				$reasons[] = 'Potential policy keyword: '.$keyword;
			}
		}

		$scamKeywords = ['advance payment', 'upi pin', 'otp', 'pay first', 'crypto only'];
		foreach ($scamKeywords as $keyword) {
			if (str_contains($text, $keyword)) {
				$riskScore += 18;
				$reasons[] = 'Potential scam pattern: '.$keyword;
			}
		}

		if ($gateway->shouldUseGemini()) {
			$prompt = json_encode([
				'task' => 'Classify marketplace listing policy risk and return strict JSON only.',
				'listing' => [
					'title' => (string) $validated['title'],
					'description' => (string) $validated['description'],
					'category_id' => $validated['category_id'] ?? null,
					'price' => $validated['price'] ?? null,
					'condition' => $validated['condition'] ?? null,
					'city' => $validated['city'] ?? null,
				],
				'response_schema' => [
					'risk_score' => 'integer 0-100',
					'risk_level' => 'one of low,medium,high',
					'blocked' => 'boolean',
					'reasons' => ['string'],
					'rewrite_suggestion' => 'short safer rewrite suggestion',
				],
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			$response = $gateway->completeText(
				'You are a strict marketplace content-moderation assistant. Return JSON only.',
				(string) $prompt,
				['max_tokens' => 260, 'temperature' => 0.0]
			);

			$json = $gateway->extractJsonObject((string) ($response['content'] ?? ''));
			if (is_array($json)) {
				$llmRisk = (int) ($json['risk_score'] ?? 0);
				$riskScore = (int) round(($riskScore * 0.45) + ($llmRisk * 0.55));
				$reasons = array_values(array_unique(array_merge($reasons, array_map('strval', (array) ($json['reasons'] ?? [])))));
				$rewriteSuggestion = trim((string) ($json['rewrite_suggestion'] ?? ''));
				$blockedByModel = (bool) ($json['blocked'] ?? false);
			}
		}

		$riskScore = max(0, min(100, $riskScore));
		$threshold = (int) setting('ai_confidence_threshold', 70);
		$blocked = ($blockedByModel ?? false) || $riskScore >= $threshold;
		$riskLevel = $riskScore >= 70 ? 'high' : ($riskScore >= 40 ? 'medium' : 'low');

		$consumedPurchase = $this->consumeAiCredit(
			$request,
			$entitlementService,
			'ai_moderation_precheck',
			[
				'risk_score' => $riskScore,
				'risk_level' => $riskLevel,
			]
		);

		if (! $consumedPurchase) {
			return $this->aiQuotaErrorResponse();
		}

		return response()->json([
			'ok' => true,
			'data' => [
				'blocked' => $blocked,
				'risk_score' => $riskScore,
				'risk_level' => $riskLevel,
				'reasons' => array_values(array_unique($reasons)),
				'rewrite_suggestion' => $rewriteSuggestion ?? '',
			],
			'usage' => $this->aiUsagePayload($consumedPurchase),
		]);
	}

	public function translateListingContent(
		Request $request,
		AiGatewayService $gateway,
		SubscriptionEntitlementService $entitlementService
	): JsonResponse {
		if (! ((bool) setting('ai_listing_assistant_enabled', true))) {
			return response()->json([
				'ok' => false,
				'message' => 'AI translation is disabled.',
			], 403);
		}

		$validated = $request->validate([
			'title' => ['required', 'string', 'max:140'],
			'description' => ['required', 'string', 'max:5000'],
			'target_language' => ['required', 'string', 'max:40'],
			'source_language' => ['nullable', 'string', 'max:40'],
		]);

		$translatedTitle = '';
		$translatedDescription = '';

		if ($gateway->shouldUseGemini()) {
			$prompt = json_encode([
				'task' => 'Translate marketplace listing text preserving meaning and sales tone. Return strict JSON only.',
				'source_language' => (string) ($validated['source_language'] ?? 'auto'),
				'target_language' => (string) $validated['target_language'],
				'title' => (string) $validated['title'],
				'description' => (string) $validated['description'],
				'response_schema' => [
					'title' => 'translated short title',
					'description' => 'translated description',
				],
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			$response = $gateway->completeText(
				'You are a marketplace localization assistant. Keep translations natural and concise. Return JSON only.',
				(string) $prompt,
				['max_tokens' => 450, 'temperature' => 0.2]
			);

			$json = $gateway->extractJsonObject((string) ($response['content'] ?? ''));
			if (is_array($json)) {
				$translatedTitle = trim((string) ($json['title'] ?? ''));
				$translatedDescription = trim((string) ($json['description'] ?? ''));
			}
		}

		if ($translatedTitle === '' || $translatedDescription === '') {
			return response()->json([
				'ok' => false,
				'message' => 'Translation failed. Please try again.',
			], 422);
		}

		$consumedPurchase = $this->consumeAiCredit(
			$request,
			$entitlementService,
			'ai_translation',
			[
				'target_language' => (string) $validated['target_language'],
			]
		);

		if (! $consumedPurchase) {
			return $this->aiQuotaErrorResponse();
		}

		return response()->json([
			'ok' => true,
			'data' => [
				'title' => $translatedTitle,
				'description' => $translatedDescription,
				'target_language' => (string) $validated['target_language'],
			],
			'usage' => $this->aiUsagePayload($consumedPurchase),
		]);
	}

	private function consumeAiCredit(
		Request $request,
		SubscriptionEntitlementService $entitlementService,
		string $usageType,
		array $meta = []
	): ?SubscriptionPackagePurchase {
		$user = $request->user();

		if (! $user) {
			return null;
		}

		$purchase = $entitlementService->findUsableAiPurchase($user);
		if (! $purchase) {
			return null;
		}

		return $entitlementService->consumeAiPurchase($purchase, $usageType, null, $meta);
	}

	private function aiQuotaErrorResponse(): JsonResponse
	{
		return response()->json([
			'ok' => false,
			'message' => 'AI credits are exhausted. Buy or renew a package with AI access to continue.',
		], 403);
	}

	private function aiUsagePayload(?SubscriptionPackagePurchase $purchase): array
	{
		if (! $purchase) {
			return [];
		}

		return [
			'purchase_id' => $purchase->id,
			'remaining_ai_items' => $purchase->remaining_ai_items,
			'is_unlimited' => $purchase->subscriptionPackage?->ai_usage_limit_type === 'unlimited',
		];
	}
}

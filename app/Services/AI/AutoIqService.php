<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;

class AutoIqService
{
    public function __construct(private readonly AiListingAssistantService $listingAssistant)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardForDealer(User $dealer): array
    {
        $inventory = Listing::query()
            ->with('category')
            ->where('user_id', $dealer->id)
            ->latest()
            ->get();

        $total = $inventory->count();
        $live = $inventory->where('status', 'approved')->count();
        $pending = $inventory->where('status', 'pending')->count();
        $sold = $inventory->where('status', 'sold')->count();

        $avgPrice = $inventory
            ->where('price', '>', 0)
            ->avg('price');

        $inventoryAgeing = [
            '0_7' => 0,
            '8_30' => 0,
            '31_90' => 0,
            '90_plus' => 0,
        ];

        foreach ($inventory as $listing) {
            $days = (int) $listing->created_at->diffInDays(now());

            if ($days <= 7) {
                $inventoryAgeing['0_7']++;
            } elseif ($days <= 30) {
                $inventoryAgeing['8_30']++;
            } elseif ($days <= 90) {
                $inventoryAgeing['31_90']++;
            } else {
                $inventoryAgeing['90_plus']++;
            }
        }

        $leadConversations = Conversation::query()->where('seller_id', $dealer->id);
        $totalLeads = (clone $leadConversations)->count();

        $unreadLeadMessages = Message::query()
            ->whereHas('conversation', function ($builder) use ($dealer): void {
                $builder->where('seller_id', $dealer->id);
            })
            ->whereNull('read_at')
            ->where('sender_id', '!=', $dealer->id)
            ->count();

        $incomingMessages = Message::query()
            ->whereHas('conversation', function ($builder) use ($dealer): void {
                $builder->where('seller_id', $dealer->id);
            })
            ->where('sender_id', '!=', $dealer->id)
            ->count();

        $outgoingMessages = Message::query()
            ->whereHas('conversation', function ($builder) use ($dealer): void {
                $builder->where('seller_id', $dealer->id);
            })
            ->where('sender_id', $dealer->id)
            ->count();

        $responseRate = $incomingMessages > 0
            ? (int) round(min(100, ($outgoingMessages / $incomingMessages) * 100))
            : 100;

        $vehicleListings = $inventory
            ->filter(function (Listing $listing): bool {
                $name = strtolower((string) ($listing->category?->name ?? ''));

                return str_contains($name, 'car')
                    || str_contains($name, 'bike')
                    || str_contains($name, 'vehicle')
                    || str_contains($name, 'auto');
            })
            ->take(6)
            ->values();

        $pricingRecommendations = [];
        foreach ($vehicleListings as $vehicleListing) {
            $priceHint = $this->listingAssistant->recommendPrice([
                'category_id' => $vehicleListing->category_id,
                'city' => $vehicleListing->city,
                'condition' => $vehicleListing->condition,
            ]);

            $pricingRecommendations[] = [
                'listing_id' => $vehicleListing->id,
                'slug' => $vehicleListing->slug,
                'title' => $vehicleListing->title,
                'current_price' => (float) $vehicleListing->price,
                'suggested_price' => $priceHint['suggested_price'],
                'min_price' => $priceHint['min_price'],
                'max_price' => $priceHint['max_price'],
            ];
        }

        $inventoryHighlights = $inventory
            ->sortByDesc('views')
            ->take(5)
            ->values()
            ->map(static function (Listing $listing): array {
                return [
                    'listing_id' => $listing->id,
                    'slug' => $listing->slug,
                    'title' => $listing->title,
                    'views' => $listing->views,
                    'status' => $listing->status,
                    'city' => $listing->city,
                ];
            })
            ->all();

        $videoIdeas = array_values(array_map(static fn (Listing $listing): array => [
            'listing_id' => $listing->id,
            'title' => $listing->title,
            'script' => 'Show exterior, close-ups, condition details, and one clear pricing CTA for '.$listing->title.'.',
        ], $vehicleListings->all()));

        return [
            'inventory' => [
                'total' => $total,
                'live' => $live,
                'pending' => $pending,
                'sold' => $sold,
                'average_price' => $avgPrice !== null ? (float) $avgPrice : null,
                'ageing' => $inventoryAgeing,
            ],
            'leads' => [
                'total_conversations' => $totalLeads,
                'unread_messages' => $unreadLeadMessages,
                'incoming_messages' => $incomingMessages,
                'outgoing_messages' => $outgoingMessages,
                'response_rate_percent' => $responseRate,
            ],
            'pricing_recommendations' => $pricingRecommendations,
            'inventory_highlights' => $inventoryHighlights,
            'video_ideas' => $videoIdeas,
        ];
    }
}

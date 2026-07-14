<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationMessagingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_polls_incremental_messages_and_marks_incoming_as_read(): void
    {
        $setup = $this->createConversationSetup();
        $buyer = $setup['buyer'];
        $conversation = $setup['conversation'];

        $firstMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'body' => 'Hi, is this still available?',
        ]);

        $incomingMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $setup['seller']->id,
            'body' => 'Yes, it is available.',
        ]);

        $response = $this->actingAs($buyer)->getJson(route('chat.messages', [
            'conversation' => $conversation,
            'after' => $firstMessage->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $incomingMessage->id)
            ->assertJsonPath('messages.0.is_own', false)
            ->assertJsonPath('messages.0.body', 'Yes, it is available.');

        $this->assertNotNull($incomingMessage->fresh()->read_at);
    }

    public function test_it_sends_image_attachment_without_text(): void
    {
        Storage::fake('public');

        $setup = $this->createConversationSetup();
        $buyer = $setup['buyer'];
        $conversation = $setup['conversation'];

        $response = $this->actingAs($buyer)->post(
            route('chat.message', $conversation),
            [
                'body' => '',
                'attachment' => UploadedFile::fake()->image('listing-photo.jpg'),
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message.is_own', true)
            ->assertJsonPath('message.attachment_kind', 'image')
            ->assertJsonCount(1, 'message.attachments');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'attachment_kind' => 'image',
            'body' => '',
        ]);

        $savedMessage = Message::query()->latest('id')->firstOrFail();
        Storage::disk('public')->assertExists((string) $savedMessage->attachment_path);

        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $savedMessage->id,
            'kind' => 'image',
        ]);
    }

    public function test_it_sends_multiple_attachments_in_single_message(): void
    {
        Storage::fake('public');

        $setup = $this->createConversationSetup();
        $buyer = $setup['buyer'];
        $conversation = $setup['conversation'];

        $response = $this->actingAs($buyer)->post(
            route('chat.message', $conversation),
            [
                'body' => 'Sharing images and invoice.',
                'attachments' => [
                    UploadedFile::fake()->image('front-view.jpg'),
                    UploadedFile::fake()->image('back-view.jpg'),
                    UploadedFile::fake()->create('invoice.pdf', 120),
                ],
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(3, 'message.attachments');

        $savedMessage = Message::query()->latest('id')->firstOrFail();

        $this->assertDatabaseCount('message_attachments', 3);
        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $savedMessage->id,
            'kind' => 'image',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $savedMessage->id,
            'kind' => 'file',
        ]);
    }

    /**
     * @return array{seller: User, buyer: User, conversation: Conversation}
     */
    private function createConversationSetup(): array
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $category = Category::create([
            'name' => 'Mobiles '.Str::upper(Str::random(4)),
            'slug' => 'mobiles-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Pixel 7 Pro 128GB',
            'slug' => Str::slug('pixel-7-pro-'.Str::random(8)),
            'description' => 'Well maintained phone with charger and box.',
            'price' => 32000,
            'currency' => 'INR',
            'condition' => 'used',
            'city' => 'Patna',
            'state' => 'Bihar',
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $conversation = Conversation::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        return [
            'seller' => $seller,
            'buyer' => $buyer,
            'conversation' => $conversation,
        ];
    }
}

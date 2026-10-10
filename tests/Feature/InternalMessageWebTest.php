<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InternalMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalMessageWebTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private Branch $branchC;

    private User $master;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create(['code' => 'A', 'name' => 'Cabang A']);
        $this->branchB = Branch::create(['code' => 'B', 'name' => 'Cabang B']);
        $this->branchC = Branch::create(['code' => 'C', 'name' => 'Cabang C']);
        $this->master = User::factory()->create(['branch_id' => $this->branchA->id, 'role' => 'master']);
        $this->adminA = User::factory()->create(['branch_id' => $this->branchA->id, 'role' => 'branch_admin']);
        $this->adminB = User::factory()->create(['branch_id' => $this->branchB->id, 'role' => 'branch_admin']);
    }

    public function test_cashier_and_branchless_admin_are_forbidden(): void
    {
        $cashier = User::factory()->create(['branch_id' => $this->branchA->id, 'role' => 'cashier']);
        $branchlessAdmin = User::factory()->create(['branch_id' => null, 'role' => 'branch_admin']);

        foreach ([$cashier, $branchlessAdmin] as $user) {
            $this->actingAs($user)->get('/backoffice/messages')->assertForbidden();
            $this->actingAs($user)->getJson('/backoffice/messages/fetch?branch_id=')->assertForbidden();
            $this->actingAs($user)->postJson('/backoffice/messages/send', ['branch_id' => null, 'message' => 'x'])->assertForbidden();
        }
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/backoffice/messages')->assertRedirect('/login');
    }

    public function test_branch_admin_sends_to_pusat_and_master_reads_it(): void
    {
        $this->actingAs($this->adminA)
            ->postJson('/backoffice/messages/send', ['branch_id' => null, 'message' => 'Stok habis'])
            ->assertCreated()
            ->assertJsonPath('message.is_mine', true);

        $this->assertDatabaseHas('internal_messages', [
            'sender_id' => $this->adminA->id,
            'sender_branch_id' => $this->branchA->id,
            'receiver_branch_id' => null,
            'is_read' => false,
        ]);

        $this->actingAs($this->master)
            ->getJson('/backoffice/messages/fetch?branch_id='.$this->branchA->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Stok habis')
            ->assertJsonPath('messages.0.is_mine', false);

        $this->assertDatabaseHas('internal_messages', ['message' => 'Stok habis', 'is_read' => true]);
    }

    public function test_master_messages_are_sent_as_pusat_even_with_branch_id(): void
    {
        $this->actingAs($this->master)
            ->postJson('/backoffice/messages/send', ['branch_id' => $this->branchB->id, 'message' => 'Dari Pusat'])
            ->assertCreated();

        $this->assertDatabaseHas('internal_messages', [
            'sender_branch_id' => null,
            'receiver_branch_id' => $this->branchB->id,
        ]);

        $this->actingAs($this->adminB)
            ->getJson('/backoffice/messages/fetch?branch_id=')
            ->assertOk()
            ->assertJsonPath('messages.0.message', 'Dari Pusat');

        // Cabang A tidak boleh melihat percakapan Pusat <-> Cabang B.
        $this->actingAs($this->adminA)
            ->getJson('/backoffice/messages/fetch?branch_id=')
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_third_branch_cannot_read_other_branches_conversation(): void
    {
        $this->actingAs($this->adminA)
            ->postJson('/backoffice/messages/send', ['branch_id' => $this->branchB->id, 'message' => 'Rahasia A ke B'])
            ->assertCreated();

        $adminC = User::factory()->create(['branch_id' => $this->branchC->id, 'role' => 'branch_admin']);

        foreach ([$this->branchA->id, $this->branchB->id] as $branchId) {
            $this->actingAs($adminC)
                ->getJson('/backoffice/messages/fetch?branch_id='.$branchId)
                ->assertOk()
                ->assertJsonCount(0, 'messages');
        }

        $this->actingAs($this->master)
            ->getJson('/backoffice/messages/fetch?branch_id='.$this->branchA->id)
            ->assertJsonCount(0, 'messages');

        $this->actingAs($this->adminB)
            ->getJson('/backoffice/messages/fetch?branch_id='.$this->branchA->id)
            ->assertJsonPath('messages.0.message', 'Rahasia A ke B');
    }

    public function test_cannot_message_own_channel_or_inactive_branch(): void
    {
        $this->actingAs($this->adminA)
            ->postJson('/backoffice/messages/send', ['branch_id' => $this->branchA->id, 'message' => 'Halo'])
            ->assertUnprocessable();

        $this->actingAs($this->master)
            ->postJson('/backoffice/messages/send', ['branch_id' => null, 'message' => 'Halo'])
            ->assertUnprocessable();

        $this->branchC->update(['is_active' => false]);
        $this->actingAs($this->adminA)
            ->postJson('/backoffice/messages/send', ['branch_id' => $this->branchC->id, 'message' => 'Halo'])
            ->assertUnprocessable();

        $this->actingAs($this->adminA)
            ->postJson('/backoffice/messages/send', ['branch_id' => null, 'message' => '   '])
            ->assertUnprocessable();

        $this->assertSame(0, InternalMessage::count());
    }

    public function test_after_id_returns_only_newer_messages(): void
    {
        $first = InternalMessage::create([
            'sender_id' => $this->adminA->id,
            'sender_branch_id' => $this->branchA->id,
            'receiver_branch_id' => null,
            'message' => 'Lama',
        ]);
        InternalMessage::create([
            'sender_id' => $this->master->id,
            'sender_branch_id' => null,
            'receiver_branch_id' => $this->branchA->id,
            'message' => 'Baru',
        ]);

        $this->actingAs($this->adminA)
            ->getJson('/backoffice/messages/fetch?branch_id=&after_id='.$first->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Baru');
    }

    public function test_index_renders_contacts_for_branch_admin(): void
    {
        $this->actingAs($this->adminA)
            ->get('/backoffice/messages')
            ->assertOk()
            ->assertSee('Pesan Internal')
            ->assertSee('Cabang B');
    }
}

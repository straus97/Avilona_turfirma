<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PersonalDocumentDeletionOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_document_deleting_exception_preserves_row_and_file(): void
    {
        Storage::fake('local');

        $manager = $this->createUserWithRole(Role::MANAGER);

        $documentPath = 'documents/personal/manager-ordering-failure.pdf';
        Storage::disk('local')->put($documentPath, 'private-personal-document');

        $document = UserDocument::query()->create([
            'user_id' => $manager->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $documentPath,
            'file_type' => 'pdf',
            'file_size' => 26,
        ]);
        $documentId = $document->id;

        $this->assertDatabaseHas('user_documents', [
            'id' => $documentId,
            'user_id' => $manager->id,
        ]);
        Storage::disk('local')->assertExists($documentPath);

        UserDocument::deleting(function (UserDocument $target) use ($documentId, $documentPath): void {
            if ($target->id !== $documentId || $target->file_path !== $documentPath) {
                return;
            }

            throw new RuntimeException('forced manager personal document deletion failure');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($manager)
                ->delete(route('cabinet.manager.documents.delete', $document));

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced manager personal document deletion failure', $e->getMessage());
        }

        $this->assertDatabaseHas('user_documents', [
            'id' => $documentId,
            'user_id' => $manager->id,
        ]);

        Storage::disk('local')->assertExists($documentPath);
    }

    public function test_tourist_document_deleting_exception_preserves_row_and_file(): void
    {
        Storage::fake('local');

        $tourist = $this->createUserWithRole(Role::TOURIST);

        $documentPath = 'documents/personal/tourist-ordering-failure.pdf';
        Storage::disk('local')->put($documentPath, 'private-personal-document');

        $document = UserDocument::query()->create([
            'user_id' => $tourist->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $documentPath,
            'file_type' => 'pdf',
            'file_size' => 26,
        ]);
        $documentId = $document->id;

        $this->assertDatabaseHas('user_documents', [
            'id' => $documentId,
            'user_id' => $tourist->id,
        ]);
        Storage::disk('local')->assertExists($documentPath);

        UserDocument::deleting(function (UserDocument $target) use ($documentId, $documentPath): void {
            if ($target->id !== $documentId || $target->file_path !== $documentPath) {
                return;
            }

            throw new RuntimeException('forced tourist personal document deletion failure');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($tourist)
                ->delete(route('cabinet.documents.personal.delete', $document));

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced tourist personal document deletion failure', $e->getMessage());
        }

        $this->assertDatabaseHas('user_documents', [
            'id' => $documentId,
            'user_id' => $tourist->id,
        ]);

        Storage::disk('local')->assertExists($documentPath);
    }

    public function test_manager_successful_document_deletion_removes_row_and_file(): void
    {
        Storage::fake('local');

        $manager = $this->createUserWithRole(Role::MANAGER);

        $documentPath = 'documents/personal/manager-ordering-success.pdf';
        Storage::disk('local')->put($documentPath, 'private-personal-document');

        $document = UserDocument::query()->create([
            'user_id' => $manager->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $documentPath,
            'file_type' => 'pdf',
            'file_size' => 26,
        ]);

        $this->actingAs($manager)
            ->delete(route('cabinet.manager.documents.delete', $document))
            ->assertRedirect(route('cabinet.manager.documents'))
            ->assertSessionHas('status', 'Документ удален.');

        $this->assertDatabaseMissing('user_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($documentPath);
    }

    public function test_tourist_successful_document_deletion_removes_row_and_file(): void
    {
        Storage::fake('local');

        $tourist = $this->createUserWithRole(Role::TOURIST);

        $documentPath = 'documents/personal/tourist-ordering-success.pdf';
        Storage::disk('local')->put($documentPath, 'private-personal-document');

        $document = UserDocument::query()->create([
            'user_id' => $tourist->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $documentPath,
            'file_type' => 'pdf',
            'file_size' => 26,
        ]);

        $this->actingAs($tourist)
            ->delete(route('cabinet.documents.personal.delete', $document))
            ->assertRedirect(route('cabinet.documents.personal'))
            ->assertSessionHas('status', 'Документ удален!');

        $this->assertDatabaseMissing('user_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($documentPath);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();

        $user->roles()->attach($role->id);

        return $user;
    }
}

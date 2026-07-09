<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonalDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tourist_upload_stores_personal_document_on_private_disk_and_downloads_through_route(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->createUserWithRole(Role::TOURIST);

        $response = $this
            ->actingAs($user)
            ->post(route('cabinet.documents.personal.upload'), [
                'name' => 'Passport Copy',
                'document_type' => 'passport',
                'file' => UploadedFile::fake()->create('passport.pdf', 12, 'application/pdf'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cabinet.documents.personal'));

        $document = UserDocument::query()->firstOrFail();

        $this->assertSame($user->id, $document->user_id);
        $this->assertStringStartsWith('documents/personal/', $document->file_path);

        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);

        $this
            ->actingAs($user)
            ->get(route('cabinet.documents.personal.download', $document))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_tourist_cannot_download_or_delete_another_users_personal_document(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $intruder = $this->createUserWithRole(Role::TOURIST);

        Storage::disk('local')->put('documents/personal/private.pdf', 'private');

        $document = UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private Document',
            'document_type' => 'passport',
            'file_path' => 'documents/personal/private.pdf',
            'file_type' => 'pdf',
            'file_size' => 7,
        ]);

        $this
            ->actingAs($intruder)
            ->get(route('cabinet.documents.personal.download', $document))
            ->assertForbidden();

        $this
            ->actingAs($intruder)
            ->delete(route('cabinet.documents.personal.delete', $document))
            ->assertForbidden();

        Storage::disk('local')->assertExists($document->file_path);

        $this->assertDatabaseHas('user_documents', [
            'id' => $document->id,
        ]);
    }

    public function test_tourist_delete_removes_private_file_and_database_record(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithRole(Role::TOURIST);

        Storage::disk('local')->put('documents/personal/delete-me.pdf', 'delete-me');

        $document = UserDocument::query()->create([
            'user_id' => $user->id,
            'name' => 'Delete Me',
            'document_type' => 'other',
            'file_path' => 'documents/personal/delete-me.pdf',
            'file_type' => 'pdf',
            'file_size' => 9,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('cabinet.documents.personal.delete', $document))
            ->assertRedirect(route('cabinet.documents.personal'));

        Storage::disk('local')->assertMissing('documents/personal/delete-me.pdf');

        $this->assertDatabaseMissing('user_documents', [
            'id' => $document->id,
        ]);
    }

    public function test_manager_upload_uses_private_disk_and_protected_download_route(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $manager = $this->createUserWithRole(Role::MANAGER);

        $response = $this
            ->actingAs($manager)
            ->post(route('cabinet.manager.documents.upload'), [
                'name' => 'Manager Document',
                'document_type' => 'other',
                'file' => UploadedFile::fake()->create('manager.pdf', 8, 'application/pdf'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cabinet.manager.documents'));

        $document = UserDocument::query()->firstOrFail();

        $this->assertSame($manager->id, $document->user_id);
        $this->assertStringStartsWith('documents/personal/', $document->file_path);

        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);

        $this
            ->actingAs($manager)
            ->get(route('cabinet.manager.documents.download', $document))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_admin_can_download_a_users_private_personal_document(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole(Role::ADMIN);
        $owner = $this->createUserWithRole(Role::TOURIST);

        Storage::disk('local')->put(
            'documents/personal/admin-download.pdf',
            'private-document'
        );

        $document = UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Admin Download',
            'document_type' => 'passport',
            'file_path' => 'documents/personal/admin-download.pdf',
            'file_type' => 'pdf',
            'file_size' => 16,
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'cabinet.admin.user-document.download',
                    [$owner, $document]
                )
            )
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_admin_document_download_rejects_mismatched_user_and_document(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole(Role::ADMIN);
        $owner = $this->createUserWithRole(Role::TOURIST);
        $otherUser = $this->createUserWithRole(Role::TOURIST);

        Storage::disk('local')->put(
            'documents/personal/mismatched.pdf',
            'private-document'
        );

        $document = UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Mismatched Document',
            'document_type' => 'other',
            'file_path' => 'documents/personal/mismatched.pdf',
            'file_type' => 'pdf',
            'file_size' => 16,
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route(
                    'cabinet.admin.user-document.download',
                    [$otherUser, $document]
                )
            )
            ->assertNotFound();
    }

    public function test_non_admin_cannot_use_admin_personal_document_download_route(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $otherTourist = $this->createUserWithRole(Role::TOURIST);

        Storage::disk('local')->put(
            'documents/personal/admin-only.pdf',
            'private-document'
        );

        $document = UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Admin Only',
            'document_type' => 'other',
            'file_path' => 'documents/personal/admin-only.pdf',
            'file_type' => 'pdf',
            'file_size' => 16,
        ]);

        $this
            ->actingAs($otherTourist)
            ->get(
                route(
                    'cabinet.admin.user-document.download',
                    [$owner, $document]
                )
            )
            ->assertForbidden();
    }
    public function test_personal_document_upload_rejects_unsupported_file_types(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithRole(Role::TOURIST);

        $this
            ->actingAs($user)
            ->post(route('cabinet.documents.personal.upload'), [
                'name' => 'Executable',
                'document_type' => 'other',
                'file' => UploadedFile::fake()->create('payload.exe', 1, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('user_documents', 0);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            [
                'description' =>
                    Role::availableRoles()[$roleName] ?? $roleName,
            ]
        );

        $user = User::factory()->create();

        $user->roles()->attach($role->id);

        return $user;
    }
}

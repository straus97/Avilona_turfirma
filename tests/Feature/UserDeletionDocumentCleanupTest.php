<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Очистка файлов при удалении пользователя (AOA-05-I3): файлы личных документов удаляются
 * только ПОСЛЕ фиксации транзакции; откат не трогает ни записи, ни файлы. Заявки и их
 * документы теперь защищены от удаления (см. UserDeletionGuardTest).
 */
class UserDeletionDocumentCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_deletion_removes_personal_document_files(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $records = $this->createPersonalDocument($owner, 'model-delete');

        $owner->delete();

        Storage::disk('local')->assertMissing($records['path']);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('user_documents', ['id' => $records['document_id']]);
    }

    public function test_tourist_account_deletion_removes_personal_document_files(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $records = $this->createPersonalDocument($owner, 'tourist-account');

        $this
            ->actingAs($owner)
            ->delete(route('cabinet.settings.destroy-account'), ['password' => 'password'])
            ->assertRedirect(route('home.index'));

        Storage::disk('local')->assertMissing($records['path']);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('user_documents', ['id' => $records['document_id']]);
    }

    public function test_admin_user_deletion_removes_personal_document_files(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole(Role::ADMIN);
        $target = $this->createUserWithRole(Role::TOURIST);
        $records = $this->createPersonalDocument($target, 'admin-delete');

        $this
            ->actingAs($admin)
            ->delete(route('cabinet.admin.delete-user', $target))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($records['path']);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('user_documents', ['id' => $records['document_id']]);
    }

    public function test_files_are_removed_only_after_the_outer_transaction_commits(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $records = $this->createPersonalDocument($owner, 'after-commit');

        $pathExistedInsideTransaction = null;

        DB::transaction(function () use ($owner, $records, &$pathExistedInsideTransaction): void {
            $owner->delete();

            $pathExistedInsideTransaction = Storage::disk('local')->exists($records['path']);
        });

        $this->assertTrue($pathExistedInsideTransaction, 'File must still exist before commit.');
        Storage::disk('local')->assertMissing($records['path']);
    }

    public function test_rollback_after_delete_keeps_user_document_row_and_file(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $records = $this->createPersonalDocument($owner, 'rollback');

        try {
            DB::transaction(function () use ($owner): void {
                $owner->delete();

                throw new RuntimeException('forced rollback');
            });
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            // ожидаемый откат
        }

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('user_documents', ['id' => $records['document_id']]);
        Storage::disk('local')->assertExists($records['path']);
    }

    public function test_cleanup_failure_after_commit_keeps_the_deletion_and_logs_recoverable_paths(): void
    {
        $owner = $this->createUserWithRole(Role::TOURIST);

        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('delete')->once()->with('documents/personal/stuck.pdf')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => 'documents/personal/stuck.pdf',
            'file_type' => 'pdf',
            'file_size' => 1,
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('User document file cleanup failed after commit', \Mockery::on(
                fn ($context): bool => $context['user_id'] === $owner->id
                    && $context['total_files'] === 1
                    && $context['failed_files'] === 1
                    && $context['failed_paths'] === ['documents/personal/stuck.pdf']
                    && ! array_key_exists('email', $context)
            ));

        $owner->delete();

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    }

    /**
     * @return array{path: string, document_id: int}
     */
    private function createPersonalDocument(User $owner, string $suffix): array
    {
        $path = 'documents/personal/' . $suffix . '.pdf';

        Storage::disk('local')->put($path, 'private-personal-document');

        $document = UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $path,
            'file_type' => 'pdf',
            'file_size' => 25,
        ]);

        return ['path' => $path, 'document_id' => $document->id];
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

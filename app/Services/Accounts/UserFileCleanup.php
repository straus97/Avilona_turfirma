<?php

namespace App\Services\Accounts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Удаление физических файлов удалённого пользователя строго ПОСЛЕ фиксации транзакции
 * (AOA-05-I3). При откате обратный вызов отбрасывается, и ни один файл не затрагивается.
 *
 * Сбой после фиксации откатить нельзя: он пишется в технический журнал (идентификатор,
 * число файлов и относительные пути хранилища — имена генерирует Laravel, персональных
 * данных в них нет). Этот список — путь ручного восстановления; фоновой очистки сирот нет.
 */
class UserFileCleanup
{
    /** @param list<string> $paths относительные пути диска local */
    public static function schedule(int $userId, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        DB::afterCommit(static fn () => self::run($userId, $paths));
    }

    /** @param list<string> $paths */
    public static function run(int $userId, array $paths): void
    {
        $failed = [];

        foreach ($paths as $path) {
            try {
                if (Storage::disk('local')->delete($path) === false) {
                    $failed[] = $path;
                }
            } catch (Throwable) {
                $failed[] = $path;
            }
        }

        if ($failed !== []) {
            Log::error('User document file cleanup failed after commit', [
                'user_id' => $userId,
                'total_files' => count($paths),
                'failed_files' => count($failed),
                'failed_paths' => $failed,
            ]);
        }
    }
}

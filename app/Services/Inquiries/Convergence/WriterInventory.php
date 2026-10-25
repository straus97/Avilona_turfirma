<?php

namespace App\Services\Inquiries\Convergence;

/**
 * Перечень реальных писателей incoming_inquiries и того, ЧЕМ каждый из них останавливается
 * (AOA-03-I5). Это документ в виде кода: тест сверяет его с исходниками, а заморозка считается
 * заявленной только если заявлены все механизмы, нужные хотя бы одному писателю.
 *
 * Режим обслуживания Laravel НЕ входит в механизмы: он отвечает 503 на HTTP, не останавливает
 * транзакцию, уже идущую в БД, а webhook в этом режиме отвечает 503 без записи (см. docs).
 */
final class WriterInventory
{
    /**
     * @var list<array{id: string, path: string, stopped_by: string, note: string}>
     */
    public const WRITERS = [
        ['id' => 'P1', 'path' => 'Webhook intake and repeat delivery (TourvisorInquiryIntake)', 'stopped_by' => FreezeAttestation::INGRESS_STOPPED, 'note' => 'stopping ingress rejects the provider request; acceptance is NOT buffered'],
        ['id' => 'P2-P4', 'path' => 'Import claim / result / failure (TourvisorInquiryImporter)', 'stopped_by' => FreezeAttestation::QUEUE_WORKERS_STOPPED, 'note' => 'also runs inside the webhook request when QUEUE_CONNECTION=sync'],
        ['id' => 'P5-P11', 'path' => 'Staff workflow: assign, reassign, client link, create client, convert, close (IncomingInquiryWorkflow)', 'stopped_by' => FreezeAttestation::INGRESS_STOPPED, 'note' => 'reached over authenticated HTTP only'],
        ['id' => 'P13a', 'path' => 'User hard-delete: Admin deleteUser and own-account deletion by Admin, Manager, Tourist and Profile (ON DELETE SET NULL)', 'stopped_by' => FreezeAttestation::INGRESS_STOPPED, 'note' => 'five controller call sites; changes assigned_to/client_*/converted_by/closed_by without touching updated_at'],
        ['id' => 'P13b', 'path' => 'Booking hard-delete (ON DELETE SET NULL on booking_id)', 'stopped_by' => FreezeAttestation::NO_MANUAL_SQL, 'note' => 'the application soft-deletes; only SQL can hard-delete'],
        ['id' => 'P14', 'path' => 'Direct SQL, tinker, maintenance scripts, migrations', 'stopped_by' => FreezeAttestation::NO_MANUAL_SQL, 'note' => 'not visible to the application'],
        ['id' => 'Q', 'path' => 'Queued ImportTourvisorInquiry jobs and delayed releases (retry back-off)', 'stopped_by' => FreezeAttestation::QUEUE_WORKERS_STOPPED, 'note' => 'a queue worker pauses in maintenance mode, but a job already reserved finishes'],
        ['id' => 'S', 'path' => 'Scheduler (bookings:send-trip-reminders)', 'stopped_by' => FreezeAttestation::SCHEDULER_PAUSED, 'note' => 'does not write incoming_inquiries today; listed so a future scheduled writer cannot be forgotten'],
    ];

    /** @return list<string> */
    public static function requiredAttestations(): array
    {
        return array_values(array_unique(array_column(self::WRITERS, 'stopped_by')));
    }
}

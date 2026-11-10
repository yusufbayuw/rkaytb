<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Answer, EvidenceRecord};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvidenceService
{
    private const MIME = [
        'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png', 'image/jpeg',
    ];

    public function __construct(private TenantAccess $access, private AuditTrail $audit) {}

    public function upload(Answer $answer, int $actorId, UploadedFile $file): EvidenceRecord
    {
        $instance = $answer->instance;
        if ($answer->author_id !== $actorId || $answer->organization_id !== $instance->organization_id) {
            throw new AuthorizationException('Hanya penulis jawaban yang boleh mengunggah buktinya.');
        }
        $this->access->requireAnswerInstance($instance, $actorId);
        if (!$file->isValid() || $file->getSize() > 10 * 1024 * 1024 ||
            !in_array($file->getMimeType(), self::MIME, true)) {
            throw ValidationException::withMessages(['evidence' => 'Dokumen harus PDF, DOCX, XLSX, JPG, atau PNG (maksimal 10 MB).']);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store('private/rka-evidence/'.$instance->organization_id.'/'.$instance->id, 'local');
        if (!$path) throw ValidationException::withMessages(['evidence' => 'Berkas gagal disimpan.']);
        try {
            return DB::transaction(function () use ($answer, $instance, $actorId, $file, $hash, $path) {
                $record = EvidenceRecord::create([
                    'organization_id' => $instance->organization_id,
                    'instance_id' => $instance->id, 'answer_id' => $answer->id,
                    'uploaded_by' => $actorId, 'original_name' => substr(basename($file->getClientOriginalName()), 0, 255),
                    'storage_path' => $path, 'mime_type' => $file->getMimeType(),
                    'sha256' => $hash, 'status' => 'pending',
                ]);
                $this->audit->record($instance->organization_id, $actorId,
                    'evidence.uploaded', 'evidence', $record->id, ['sha256' => $hash]);
                return $record;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function verify(EvidenceRecord $record, int $actorId): void
    {
        $instance = $record->instance;
        $this->access->requireReviewInstance($instance, $actorId);
        if ($record->uploaded_by === $actorId) throw new AuthorizationException('Pemeriksa tidak boleh memverifikasi buktinya sendiri.');
        DB::transaction(function () use ($record, $instance, $actorId) {
            $record = EvidenceRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->organization_id !== $instance->organization_id || $record->status !== 'pending') {
                throw ValidationException::withMessages(['evidence' => 'Bukti tidak tersedia untuk diverifikasi.']);
            }
            if (!Storage::disk('local')->exists($record->storage_path)) {
                throw ValidationException::withMessages(['evidence' => 'Berkas bukti tidak ditemukan.']);
            }
            $record->update(['status' => 'verified', 'verified_by' => $actorId, 'verified_at' => now()]);
            $this->audit->record($instance->organization_id, $actorId, 'evidence.verified', 'evidence', $record->id);
        });
    }

    public function download(EvidenceRecord $record, int $actorId)
    {
        $instance = $record->instance;
        $this->access->requireReadInstance($instance, $actorId);
        if ($record->uploaded_by !== $actorId && !$this->access->canViewAnswers($instance, $actorId)) {
            throw new AuthorizationException('Bukti milik kontributor lain tidak boleh diakses.');
        }
        if (!Storage::disk('local')->exists($record->storage_path)) abort(404);
        return Storage::disk('local')->download($record->storage_path, $record->original_name);
    }
}

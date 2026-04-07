<?php

namespace App\Application\Services;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Models\Attachment;
use App\Domain\Policies\AttachmentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $audit,
    ) {}

    /**
     * Validate and store an attachment record.
     * The file should already be stored; this validates metadata and records the attachment.
     */
    public function create(
        string $attachableType,
        int $attachableId,
        string $originalFilename,
        string $storedPath,
        string $mimeType,
        int $sizeBytes,
        string $sha256Fingerprint,
        ?int $uploadedBy = null,
    ): Attachment {
        $errors = AttachmentPolicy::validate($sizeBytes, $mimeType);
        if ($errors) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        $attachment = Attachment::create([
            'attachable_type'    => $attachableType,
            'attachable_id'      => $attachableId,
            'original_filename'  => $originalFilename,
            'stored_path'        => $storedPath,
            'mime_type'          => $mimeType,
            'size_bytes'         => $sizeBytes,
            'sha256_fingerprint' => $sha256Fingerprint,
            'uploaded_by'        => $uploadedBy,
        ]);

        $this->audit->log('attachment_created', $attachableType, $attachableId, null, [
            'filename'    => $originalFilename,
            'mime_type'   => $mimeType,
            'size_bytes'  => $sizeBytes,
            'sha256'      => $sha256Fingerprint,
        ]);

        return $attachment;
    }
}

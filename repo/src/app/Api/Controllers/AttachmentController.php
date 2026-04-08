<?php

namespace App\Api\Controllers;

use App\Application\Services\AttachmentService;
use App\Domain\Models\Attachment;
use App\Domain\Policies\AttachmentPolicy;
use App\Infrastructure\Export\ImageCompressor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $service) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'attachable_type' => 'required|string|max:100',
            'attachable_id' => 'required|integer|min:1',
        ]);

        $user = $request->attributes->get('auth_user');

        // 1) Whitelist attachable_type — reject unknown classes outright.
        $fqcn = AttachmentPolicy::canonicalAttachableType($request->input('attachable_type'));
        if ($fqcn === null) {
            Log::channel('security')->warning('attachment.upload.rejected_type', [
                'user_id' => $user?->id,
                'attempted_type' => $request->input('attachable_type'),
            ]);
            return response()->json(['message' => 'Invalid attachable type.'], 422);
        }

        // 2) Existence check — the attachable entity must exist.
        $entity = $fqcn::find((int) $request->input('attachable_id'));
        if ($entity === null) {
            return response()->json(['message' => 'Attachable entity not found.'], 404);
        }

        // 3) Object-level authorization — must be allowed to attach to THIS entity.
        if (!AttachmentPolicy::canAttachTo($user, $entity)) {
            Log::channel('security')->warning('attachment.upload.denied', [
                'user_id' => $user->id,
                'attachable_type' => $fqcn,
                'attachable_id' => $entity->id,
            ]);
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // 4) Persist file only after the above checks pass.
        $file = $request->file('file');
        $path = $file->store('attachments', 'local');
        $fullPath = Storage::disk('local')->path($path);

        $mime = $file->getMimeType();
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
            ImageCompressor::compress($fullPath);
        }

        $sha256 = hash_file('sha256', $fullPath);
        $sizeBytes = filesize($fullPath);

        try {
            $attachment = $this->service->create(
                attachableType: $fqcn,
                attachableId: (int) $entity->id,
                originalFilename: $file->getClientOriginalName(),
                storedPath: $path,
                mimeType: $mime,
                sizeBytes: $sizeBytes,
                sha256Fingerprint: $sha256,
                uploadedBy: $user->id,
            );
        } catch (\Throwable $e) {
            // Roll back the stored file if metadata persistence/validation fails.
            Storage::disk('local')->delete($path);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $attachment, 'message' => 'File uploaded.'], 201);
    }

    public function download(Request $request, int $id)
    {
        $attachment = Attachment::findOrFail($id);
        $user = $request->attributes->get('auth_user');

        // Object-level authorization: admin OR uploader OR allowed-to-attach to the parent entity.
        if (!$user->isAdmin() && $attachment->uploaded_by !== $user->id) {
            $fqcn = AttachmentPolicy::canonicalAttachableType((string) $attachment->attachable_type);
            if ($fqcn === null) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            $entity = $fqcn::find($attachment->attachable_id);
            if (!$entity || !AttachmentPolicy::canAttachTo($user, $entity)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        if (!Storage::disk('local')->exists($attachment->stored_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::disk('local')->download(
            $attachment->stored_path,
            $attachment->original_filename,
        );
    }
}

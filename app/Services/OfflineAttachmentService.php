<?php

namespace App\Services;

use App\Concerns\HasAttachments;
use App\Models\Attachment;
use App\Models\User;
use App\Support\OfflineSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OfflineAttachmentService
{
    /**
     * @return array{attachment: Attachment, created: bool}
     */
    public function storeForSync(
        User $user,
        string $resource,
        string $parentClientUuid,
        string $clientUuid,
        UploadedFile $file,
    ): array {
        if (! OfflineSettings::attachmentsEnabled()) {
            throw ValidationException::withMessages([
                'file' => ['Offline attachments are disabled.'],
            ]);
        }

        $this->validateFile($file);

        $existing = Attachment::query()->where('client_uuid', $clientUuid)->first();

        if ($existing) {
            return ['attachment' => $existing, 'created' => false];
        }

        $parent = $this->resolveParent($user, $resource, $parentClientUuid);

        if (! in_array(HasAttachments::class, class_uses_recursive($parent), true)) {
            throw new InvalidArgumentException('Parent model does not support attachments.');
        }

        /** @var Model&object{storeAttachment: callable} $parent */
        $attachment = $parent->storeAttachment($file, null, $user->id, $clientUuid);

        return ['attachment' => $attachment, 'created' => true];
    }

    public function validateFile(UploadedFile $file): void
    {
        $maxKb = (int) ceil(OfflineSettings::attachmentMaxBytes() / 1024);
        $mimes = OfflineSettings::allowedMimes();

        $validator = Validator::make(
            ['file' => $file],
            [
                'file' => [
                    'required',
                    'file',
                    'max:'.$maxKb,
                    'mimetypes:'.implode(',', $mimes),
                ],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Resolve (or create) the attachable parent from config('offline.attachment_parents').
     */
    protected function resolveParent(User $user, string $resource, string $parentClientUuid): Model
    {
        /** @var array<string, mixed>|null $config */
        $config = config("offline.attachment_parents.{$resource}");

        if (! is_array($config) || ! isset($config['model']) || ! is_string($config['model'])) {
            throw ValidationException::withMessages([
                'resource' => ["Unsupported attachment resource [{$resource}]. Register it under offline.attachment_parents."],
            ]);
        }

        $modelClass = $config['model'];

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw ValidationException::withMessages([
                'resource' => ["Invalid attachment parent model for [{$resource}]."],
            ]);
        }

        $userScoped = (bool) ($config['user_scoped'] ?? false);
        /** @var array<string, mixed> $defaults */
        $defaults = is_array($config['defaults'] ?? null) ? $config['defaults'] : [];

        $attributes = [
            'client_uuid' => $parentClientUuid,
        ];

        if ($userScoped) {
            $attributes['user_id'] = $user->id;
        }

        $values = [
            ...$defaults,
            'client_updated_at' => now(),
        ];

        if ($userScoped) {
            $values['user_id'] = $user->id;
        }

        return $modelClass::query()->firstOrCreate($attributes, $values);
    }
}

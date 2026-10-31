<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless($request->user(), 403);
        abort_unless($attachment->ownedBy($request->user()), 403);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_filename,
        );
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        abort_unless($request->user(), 403);
        abort_unless($attachment->ownedBy($request->user()), 403);

        $attachment->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Attachment moved to trash.'),
            ]);
        }

        return back()->with('success', __('Attachment moved to trash.'));
    }
}

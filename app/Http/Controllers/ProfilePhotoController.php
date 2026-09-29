<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in person's own profile photo.
 *
 * The page crops and shrinks the picture to 256px before sending it, so what
 * arrives is a small JPEG, PNG or WebP as a data URL. It is kept in the users
 * row rather than on disk: the deployment's disk is wiped on every deploy.
 * Removing it brings back the Google picture, or the letter for an account
 * that does not sign in with Google.
 */
class ProfilePhotoController extends Controller
{
    /** About 300 KB of picture; a 256px JPEG is a tenth of that. */
    private const MAX_BYTES = 300 * 1024;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'string', 'max:' . (int) ceil(self::MAX_BYTES * 4 / 3) + 64],
        ])['photo'];

        if (! preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $data, $m)) {
            return response()->json(['success' => false, 'message' => __('That is not a picture this page can use.')], 422);
        }

        $bytes = base64_decode($m[2], true);
        $info  = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if (! $info || strlen($bytes) > self::MAX_BYTES || $info[0] > 1024 || $info[1] > 1024) {
            return response()->json(['success' => false, 'message' => __('That picture could not be read, or is too large.')], 422);
        }

        $request->user()->forceFill(['photo' => $data])->save();

        return response()->json(['success' => true, 'message' => __('Profile photo updated.'), 'avatar' => $data]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['photo' => null])->save();

        return response()->json([
            'success' => true,
            'message' => $user->google_avatar ? __('Your Google photo is back.') : __('Profile photo removed.'),
            'avatar'  => $user->avatarUrl(),
        ]);
    }
}

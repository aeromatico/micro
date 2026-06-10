<?php namespace Aero\Sites\Http\Controllers\Api;

use Aero\Sites\Jobs\DispatchContactNotification;
use Aero\Sites\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Check contact form is enabled
        $contactConfig = $tenant->contactConfig;
        if (!$contactConfig || !$contactConfig->form_enabled) {
            return response()->json(['error' => 'disabled', 'message' => 'Contact form is disabled.'], 403);
        }

        // Rate limiting: 3 per IP per hour
        $key = 'contact:' . $request->ip() . ':' . $tenant->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'error'   => 'rate_limited',
                'message' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ], 429);
        }
        RateLimiter::hit($key, 3600);

        // Validate
        $validator = Validator::make($request->all(), [
            'name'    => 'required|min:2|max:100',
            'email'   => 'required|email|max:200',
            'phone'   => 'nullable|max:30',
            'message' => 'required|min:5|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => 'validation',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Create submission
        $submission = ContactSubmission::create([
            'tenant_id' => $tenant->id,
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'phone'     => $request->input('phone'),
            'message'   => $request->input('message'),
            'metadata'  => [
                'ip'       => $request->ip(),
                'ua'       => $request->userAgent(),
                'referer'  => $request->header('referer'),
            ],
            'status'    => 'pending',
        ]);

        // Dispatch notification job
        DispatchContactNotification::dispatch($submission);

        $successMessage = $contactConfig->success_message ?: '¡Mensaje recibido!';

        return response()->json([
            'success' => true,
            'message' => $successMessage,
        ]);
    }
}

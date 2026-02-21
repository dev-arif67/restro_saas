<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactEnquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends BaseApiController
{
    /**
     * Store a new contact/enquiry (public, no auth).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'restaurant_name' => ['nullable', 'string', 'max:150'],
            'message'         => ['required', 'string', 'max:2000'],
        ]);

        $enquiry = ContactEnquiry::create($validated);

        return $this->created($enquiry, 'Thank you for your enquiry! We will get back to you soon.');
    }

    /**
     * List all enquiries (super_admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactEnquiry::latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query, $request->get('per_page', 20));
    }

    /**
     * Show a single enquiry (super_admin only).
     */
    public function show(int $id): JsonResponse
    {
        $enquiry = ContactEnquiry::find($id);

        if (!$enquiry) {
            return $this->notFound('Enquiry not found');
        }

        // Auto-mark as read
        if ($enquiry->status === 'new') {
            $enquiry->update(['status' => 'read']);
        }

        return $this->success($enquiry);
    }

    /**
     * Update enquiry status (super_admin only).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $enquiry = ContactEnquiry::find($id);

        if (!$enquiry) {
            return $this->notFound('Enquiry not found');
        }

        $request->validate([
            'status' => ['required', 'in:new,read,replied'],
        ]);

        $enquiry->update(['status' => $request->status]);

        return $this->success($enquiry, 'Status updated');
    }

    /**
     * Delete an enquiry (super_admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $enquiry = ContactEnquiry::find($id);

        if (!$enquiry) {
            return $this->notFound('Enquiry not found');
        }

        $enquiry->delete();

        return $this->success(null, 'Enquiry deleted');
    }
}

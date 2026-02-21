<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EnquiryController extends Controller
{
    /**
     * List all enquiries (super admin)
     */
    public function index(Request $request)
    {
        $query = ContactEnquiry::with('tenant')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        $enquiries = $query->paginate($request->get('per_page', 20));

        return response()->json($enquiries);
    }

    /**
     * Get single enquiry
     */
    public function show(ContactEnquiry $enquiry)
    {
        $enquiry->load('tenant');
        return response()->json(['data' => $enquiry]);
    }

    /**
     * Mark enquiry as read
     */
    public function markRead(ContactEnquiry $enquiry)
    {
        $enquiry->update(['status' => 'read']);

        AuditLogger::logAction('enquiry_marked_read', $enquiry);

        return response()->json([
            'message' => 'Enquiry marked as read',
            'data' => $enquiry
        ]);
    }

    /**
     * Reply to enquiry
     */
    public function reply(Request $request, ContactEnquiry $enquiry)
    {
        $request->validate([
            'message' => 'required|string|min:10',
        ]);

        // Send email reply
        try {
            Mail::raw($request->message, function ($mail) use ($enquiry) {
                $mail->to($enquiry->email)
                    ->subject('Re: ' . ($enquiry->subject ?: 'Your Enquiry'));
            });

            $enquiry->update([
                'status' => 'replied',
                'replied_at' => now(),
                'reply_message' => $request->message,
            ]);

            AuditLogger::logAction('enquiry_replied', $enquiry, null, [
                'reply_message' => $request->message
            ]);

            return response()->json([
                'message' => 'Reply sent successfully',
                'data' => $enquiry
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send reply: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update enquiry status
     */
    public function updateStatus(Request $request, ContactEnquiry $enquiry)
    {
        $request->validate([
            'status' => 'required|in:new,read,replied,archived',
        ]);

        $oldStatus = $enquiry->status;
        $enquiry->update(['status' => $request->status]);

        AuditLogger::logAction('enquiry_status_updated', $enquiry, ['status' => $oldStatus], ['status' => $request->status]);

        return response()->json([
            'message' => 'Status updated',
            'data' => $enquiry
        ]);
    }

    /**
     * Delete enquiry
     */
    public function destroy(ContactEnquiry $enquiry)
    {
        AuditLogger::logDeleted($enquiry);

        $enquiry->delete();

        return response()->json([
            'message' => 'Enquiry deleted'
        ]);
    }
}

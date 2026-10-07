<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Controllers;

use App\Base\Controller;
use App\Modules\FinancialAccounting\Application\Services\MoodianSubmissionService;
use App\Modules\FinancialAccounting\Infrastructure\Models\MoodianSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodianController extends Controller
{
    public function __construct(
        private readonly MoodianSubmissionService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $q = MoodianSubmission::query()->orderByDesc('created_at');
        if ($request->filled('company_id')) {
            $q->where('company_id', $request->string('company_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json(['status' => 'success', 'data' => $q->limit(200)->get()]);
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'           => 'required|uuid',
            'source_document_type' => 'required|string|max:100',
            'source_document_id'   => 'required|uuid',
            'tax_transaction_id'   => 'nullable|uuid',
            'payload'              => 'required|array',
        ]);

        $row = $this->service->submit([
            'company_id'           => $data['company_id'],
            'source_document_type' => $data['source_document_type'],
            'source_document_id'   => $data['source_document_id'],
            'tax_transaction_id'   => $data['tax_transaction_id'] ?? null,
        ], $data['payload']);

        return response()->json([
            'status'  => 'success',
            'message' => 'ارسال مودیان ثبت شد.',
            'data'    => $row,
        ], 201);
    }

    public function poll(string $submission): JsonResponse
    {
        $row = $this->service->poll($submission);

        return response()->json([
            'status'  => 'success',
            'message' => 'وضعیت به‌روز شد.',
            'data'    => $row,
        ]);
    }
}

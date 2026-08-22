<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\DTOs\CreateHrDocumentDTO;
use App\Modules\HrManagement\Models\HrDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HrDocumentService
{
    public function createDocument(CreateHrDocumentDTO $dto): HrDocument
    {
        return DB::transaction(function () use ($dto) {
            $document = HrDocument::create([
                // tenant_id به صورت خودکار توسط TenantScoped/Observer (یا در لایه Repository) مقداردهی می‌شود، 
                // اما اگر دستی هندل می‌کنید می‌توانید app('current_tenant_id') را اینجا پاس دهید.
                'tenant_id' => app('current_tenant_id'), 
                'employee_id' => $dto->employee_id,
                'document_type_code' => $dto->document_type_code,
                'document_title' => $dto->document_title,
                'issue_date' => $dto->issue_date,
                'expiry_date' => $dto->expiry_date,
                'attachment_id' => $dto->attachment_id,
                'status' => $dto->status,
            ]);

            Log::info("HR Document created successfully.", [
                'document_id' => $document->id,
                'employee_id' => $document->employee_id,
                'tenant_id' => $document->tenant_id
            ]);

            // در صورت نیاز به Event-Outbox، رویداد در اینجا منتشر می‌شود.

            return $document;
        });
    }
}
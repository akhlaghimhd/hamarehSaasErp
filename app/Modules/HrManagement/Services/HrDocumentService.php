<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\Models\Employee;
use App\Modules\HrManagement\Models\HrDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HrDocumentService
{
    public const STATUS_VALID = 1;
    public const STATUS_EXPIRED = 2;
    public const STATUS_TERMINATED = 3;

    public function listForEmployee(string $employeeId): Collection
    {
        return HrDocument::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array{employee_id:string,document_type_code:string,document_title:string,issue_date?:?string,expiry_date?:?string,attachment_id?:?string}  $data
     */
    public function create(array $data): HrDocument
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $employee = Employee::query()->find($data['employee_id']);
                if (!$employee) {
                    throw new NotFoundHttpException('Employee not found.');
                }

                return HrDocument::create([
                    'tenant_id'          => $tenantId,
                    'employee_id'        => $data['employee_id'],
                    'document_type_code' => $data['document_type_code'],
                    'document_title'     => $data['document_title'],
                    'issue_date'         => $data['issue_date'] ?? null,
                    'expiry_date'        => $data['expiry_date'] ?? null,
                    'attachment_id'      => $data['attachment_id'] ?? null,
                    'status'             => self::STATUS_VALID,
                    'created_by'         => $userId,
                    'row_version'        => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create HR document: ' . $e->getMessage());
            throw $e;
        }
    }
}

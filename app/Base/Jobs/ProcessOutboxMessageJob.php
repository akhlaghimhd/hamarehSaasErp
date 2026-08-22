<?php

namespace App\Base\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Base\Context\TenantContext;
use Exception;
use Throwable;

class ProcessOutboxMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * تعداد دفعات تلاش مجدد در صورت شکست (مطابق معماری DLQ)
     */
    public int $tries = 3;

    // تغییر: تبدیل به Nullable برای جلوگیری از خطای Type Error در PHP
    public ?string $eventId;

    /**
     * فواصل زمانی پلکانی برای تلاش مجدد (Exponential Backoff) بر حسب ثانیه
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Create a new job instance.
     */
    // تغییر: پارامتر ورودی اختیاری شد تا عامل فراخوانی در کنسول خطا ندهد
    public function __construct(?string $eventId = null)
    {
        $this->eventId = $eventId;
    }

    /**
     * Execute the job.
     */
    public function handle(TenantContext $tenantContext): void
    {
        // گارد امنیتی: اگر جاب بدون شناسه ایونت فراخوانی شد، پردازش را متوقف کن
        if (empty($this->eventId)) {
            return;
        }

        // دریافت تازه‌ترین وضعیت رکورد از دیتابیس
        $eventRecord = DB::table('event_outbox')->where('event_id', $this->eventId)->first();

        if (!$eventRecord) {
            return;
        }

        // بررسی Idempotency: اگر پیام قبلاً پردازش شده است، رد شو (حفاظت در برابر اجرای تکراری)
        if ($eventRecord->status == 2) {
            return;
        }

        try {
            DB::beginTransaction();

            // ۱. بازسازی Tenant Context در ثانیه اول پردازش (Red Line Rule)
            if (!empty($eventRecord->tenant_id)) {
                $tenantContext->setTenantId($eventRecord->tenant_id);
            }

            // ۲. استخراج Payload
            $payload = json_decode($eventRecord->payload, true) ?? [];
            $eventName = $eventRecord->event_type;

            // ۳. شلیک رویداد در اکوسیستم Pub/Sub داخلی لاراول
            // لیسنرهای سایر ماژول‌ها این رویداد را دریافت و با کانتکست مستأجر فعلی پردازش می‌کنند
            event($eventName, [$payload]);

            // ۴. تغییر وضعیت به پردازش شده (وضعیت 2 = Processed)
            DB::table('event_outbox')
                ->where('event_id', $this->eventId)
                ->update([
                    'status' => 2,
                    'processed_at' => now(),
                    'error_log' => null, // پاکسازی خطاهای احتمالی قبلی
                ]);

            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            $this->handleFailure($e);
        }
    }

    /**
     * مدیریت منطق خطا و Dead Letter Queue
     */
    protected function handleFailure(Throwable $e): void
    {
        $isLastAttempt = $this->attempts() >= $this->tries;
        
        // اگر آخرین تلاش بود وضعیت 3 (Failed) وگرنه در حالت 4 باقی بماند تا تلاش بعدی
        $newStatus = $isLastAttempt ? 3 : 4;

        DB::table('event_outbox')
            ->where('event_id', $this->eventId)
            ->update([
                'status' => $newStatus,
                'error_log' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'retry_count' => DB::raw('retry_count + 1'),
            ]);

        Log::error("Outbox Event Processing Failed", [
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenant_id ?? 'N/A', // نکته: ممکن است اینجا tenant_id ست نشده باشد ولی لاگ می‌شود
            'attempt' => $this->attempts(),
            'error' => $e->getMessage()
        ]);

        // پرتاب مجدد خطا تا مکانیزم Tries و Backoff لاراول وارد عمل شود
        throw $e;
    }
}
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\RkaPlatform\{Cycle, Membership, Organization};
use App\Services\RkaPlatform\TaskInboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** Optional once-daily due-date digest; never changes tasks or approval status. */
class RemindRkaTasks extends Command
{
    protected $signature = 'rka:remind-tasks {--dry-run : Evaluate without sending email}';
    protected $description = 'Send optional due-date reminder digest to active RKA members';

    public function handle(TaskInboxService $inbox): int
    {
        $dryRun = (bool)$this->option('dry-run');
        if (!$dryRun && !config('rka_platform.task_reminder_mail_enabled', false)) {
            $this->info('Pengingat email RKA nonaktif.');
            return self::SUCCESS;
        }
        $days = max(0, min(14, (int)config('rka_platform.task_reminder_days_ahead', 3)));
        $count = 0;
        Cycle::query()->where('status', 'draft')->chunkById(100,
            function ($cycles) use ($inbox, $dryRun, $days, &$count) {
                foreach ($cycles as $cycle) {
                    $organization = Organization::whereKey($cycle->organization_id)->first();
                    if (!$organization) continue;
                    $day = Carbon::now(config('rka_platform.timezone', 'Asia/Jakarta'))->toDateString();
                    $members = Membership::where('organization_id', $organization->id)
                        ->where('active', true)->get();
                    foreach ($members as $member) {
                        $user = User::find($member->user_id);
                        if (!$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) continue;
                        $due = array_values(array_filter(
                            $inbox->forCycle((int)$organization->id, (int)$cycle->id, (int)$user->id),
                            static fn ($t) => $t['due'] !== null &&
                                Carbon::parse($t['due'])->lessThanOrEqualTo(now()->addDays($days))
                        ));
                        if (!$due) continue;
                        if ($dryRun) { $count++; continue; }
                        $key = 'rka:taskmail:'.hash('sha256',
                            $organization->id.':'.$cycle->id.':'.$user->id.':'.$day);
                        if (!Cache::add($key, true, now()->addHours(26))) continue;
                        try {
                            $titles = array_map(static fn ($t) => '- '.$t['title'].' ('.$t['due'].')',
                                array_slice($due, 0, 25));
                            $body = "Ada pekerjaan RKA mendekati atau melewati tenggat.\n"
                                ."Organisasi: ".($organization->brand_name ?: $organization->name)."\n"
                                ."Siklus: ".$cycle->name."\n\n".implode("\n", $titles)
                                ."\n\nBuka aplikasi RKA untuk menindaklanjuti.";
                            Mail::raw($body, static function ($message) use ($user) {
                                $message->to($user->email)->subject('Pengingat tugas RKA');
                            });
                            $count++;
                        } catch (\Throwable $error) {
                            Cache::forget($key);
                            report($error);
                            $this->warn('Gagal mengirim ke user #'.$user->id);
                        }
                    }
                }
            });
        $this->info(($dryRun ? 'Eligible reminder digests: ' : 'Reminder digests sent: ').$count);
        return self::SUCCESS;
    }
}

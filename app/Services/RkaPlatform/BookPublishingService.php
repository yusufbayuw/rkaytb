<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Book, BookSection, BudgetLine, SharedPool, Synthesis, NarrativeConflict, JournalLine, Cycle};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookPublishingService
{
    public function __construct(private TenantAccess $access, private AuditTrail $audit) {}

    public function saveSection(BookSection $section, int $actor, string $content): void
    {
        $book = $section->book;
        $this->access->requireRole($book->organization_id, $actor, ['owner','admin','editor']);
        if ($book->status !== 'draft' || $section->status === 'approved') {
            throw ValidationException::withMessages(['section' => 'Bagian telah dikunci.']);
        }
        $section->update([
            'content' => $content, 'status' => 'review', 'last_edited_by' => $actor,
        ]);
        $this->audit->record($book->organization_id, $actor, 'book.section.updated', 'book_section', $section->id);
    }

    public function approveSection(BookSection $section, int $actor): void
    {
        $book = $section->book;
        $this->access->requireRole($book->organization_id, $actor, ['owner','admin','reviewer']);
        if ($book->status !== 'draft' || $section->status !== 'review' || $section->last_edited_by === $actor) {
            throw new AuthorizationException('Review independen diperlukan.');
        }
        $section->update(['status' => 'approved']);
        $this->audit->record($book->organization_id, $actor, 'book.section.approved', 'book_section', $section->id);
    }

    public function publish(Book $book, int $actor): Book
    {
        $this->access->requireRole($book->organization_id, $actor, ['owner', 'publisher']);
        return DB::transaction(function () use ($book, $actor) {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
            if ($book->status !== 'draft') throw ValidationException::withMessages(['book' => 'Buku tidak dalam status draft.']);
            $sections = $book->sections()->get();
            if (!$sections->count() || $sections->contains(fn ($s) => $s->required && ($s->status !== 'approved' || !trim((string)$s->content))) ||
                $sections->contains(fn ($s) => $s->status !== 'approved')) {
                throw ValidationException::withMessages(['book' => 'Seluruh bagian harus disetujui dan bagian wajib tidak boleh kosong.']);
            }
            if ($sections->contains(fn ($s) => $s->last_edited_by === $actor)) {
                throw new AuthorizationException('Penerbit tidak boleh menyetujui isi yang terakhir ditulisnya sendiri.');
            }
            if (NarrativeConflict::query()->where('organization_id', $book->organization_id)->where('status', 'open')
                ->whereHas('instance', fn ($q) => $q->where('cycle_id', $book->cycle_id))->exists()) {
                throw ValidationException::withMessages(['book' => 'Konflik narasi pada siklus belum diselesaikan.']);
            }
            $cycle = Cycle::query()->where('organization_id', $book->organization_id)->findOrFail($book->cycle_id);
            if (BudgetLine::query()->where('organization_id', $book->organization_id)
                ->where('cycle_id', $book->cycle_id)->where('status', '!=', 'approved')->exists() ||
                SharedPool::query()->where('organization_id', $book->organization_id)
                ->where('cycle_id', $book->cycle_id)->where('status', '!=', 'approved')->exists()) {
                throw ValidationException::withMessages(['book' => 'Seluruh anggaran dan alokasi wajib disahkan sebelum buku diterbitkan.']);
            }
            if (JournalLine::query()->where('organization_id', $book->organization_id)
                ->whereBetween('posted_on', [$cycle->starts_on, $cycle->ends_on])
                ->where('entry_kind', 'unmapped')->where('posting_status', 'posted')->exists()) {
                throw ValidationException::withMessages(['book' => 'Jurnal posted belum terpetakan.']);
            }
            $snapshot = [
                'organization_id' => $book->organization_id,
                'cycle_id' => $book->cycle_id,
                'template_id' => $book->template_id,
                'title' => $book->title,
                'currency' => $book->organization->currency,
                'sections' => $sections->map(fn ($s) => ['code' => $s->code, 'title' => $s->title,
                    'content' => $s->content, 'required' => $s->required])->all(),
                'budgets' => BudgetLine::query()->where('organization_id', $book->organization_id)
                    ->where('cycle_id', $book->cycle_id)->where('status', 'approved')
                    ->orderBy('id')->get(['unit_id','account_code','category','name','amount','period_amounts'])->toArray(),
                'shared_pools' => SharedPool::query()->where('organization_id', $book->organization_id)
                    ->where('cycle_id', $book->cycle_id)->where('status', 'approved')
                    ->orderBy('id')->get(['code','name','amount','allocations','method','version'])->toArray(),
                'syntheses' => Synthesis::query()->where('organization_id', $book->organization_id)->where('status', 'verified')
                    ->whereHas('instance', fn ($q) => $q->where('cycle_id', $book->cycle_id))
                    ->orderBy('id')->get(['instance_id','content','version','verified_at'])->toArray(),
            ];
            $snapshot['sha256'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $book->update(['snapshot' => $snapshot, 'status' => 'published',
                'published_at' => now(), 'approved_by' => $actor]);
            $this->audit->record($book->organization_id, $actor, 'book.published', 'book', $book->id,
                ['sha256' => $snapshot['sha256']]);
            return $book;
        });
    }
}

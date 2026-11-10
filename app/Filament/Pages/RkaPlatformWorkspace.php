<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\RkaPlatform\{
    Organization, Membership, Unit, Cycle, BudgetLine, SharedPool, Question,
    QuestionInstance, Invitation, Answer, Synthesis, BookTemplate, Book, BookSection,
    JournalBatch, NarrativeConflict, ApprovalPolicy, ApprovalRequest
};
use App\Services\RkaPlatform\{
    TenantAccess, AuditTrail, ContributionService, QuestionRules,
    SharedCostAllocator, JournalSpreadsheetImporter, BookPublishingService, FinancialApprovalService,
    EvidenceService, FinancialReconciliationService, FinancialClosingService
};
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use App\Models\RkaPlatform\EvidenceRecord;

class RkaPlatformWorkspace extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Workspace RKA';
    protected static ?string $navigationGroup = 'Rencana Kerja & Anggaran';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.rka-platform-workspace';

    public ?int $organizationId = null;
    public ?int $cycleId = null;
    public ?int $instanceId = null;
    public ?int $bookId = null;
    public ?int $sectionId = null;

    public string $orgName = '';
    public string $orgBrand = '';
    public string $orgCurrency = 'IDR';
    public string $orgLocale = 'id';
    public string $orgTimezone = 'Asia/Jakarta';
    public string $orgColor = '#1e3a5f';
    public string $unitCode = '';
    public string $unitName = '';
    public string $memberEmail = '';
    public string $memberRole = 'contributor';
    public string $cycleName = '';
    public string $cycleYear = '';
    public string $cycleStart = '';
    public string $cycleEnd = '';
    public string $questionCode = '';
    public string $questionText = '';
    public string $questionDomain = 'general';
    public string $questionOwnerRole = 'reviewer';
    public string $questionRulesJson = '{}';
    public string $questionEvidenceJson = '{}';
    public ?int $newQuestionId = null;
    public ?int $newOwnerUserId = null;
    public ?int $newReviewerUserId = null;
    public ?int $newUnitId = null;
    public string $questionContextJson = '{}';
    public ?int $inviteUserId = null;
    public int $inviteDays = 7;
    public string $answerText = '';
    public int $answerRevision = 0;
    public string $synthesisText = '';
    public array $synthesisAnswerIds = [];
    public string $lineName = '';
    public string $lineCategory = 'operational';
    public string $lineAmount = '';
    public ?int $lineUnitId = null;
    public string $poolName = '';
    public string $poolCode = '';
    public string $poolAmount = '';
    public string $poolMethod = 'equal';
    public array $poolDrivers = [];
    public string $templateName = '';
    public string $templateSections = '';
    public ?int $newTemplateId = null;
    public string $bookTitle = '';
    public string $sectionContent = '';
    public string $journalSource = 'primary';
    public string $journalMappingJson = '{}';
    public $journalFile = null;
    public $answerEvidenceFile = null;
    public string $legacySource = 'investment';
    public ?int $legacyUnitId = null;
    public array $legacyPreview = [];
    public string $kpiProfileJson = '{"income_categories":[],"expense_categories":[]}';
    public ?int $memberUnitId = null;
    public string $mappingSource = 'primary';
    public string $mappingAccount = '';
    public string $mappingClass = 'expense';
    public ?int $mappingUnitId = null;
    public string $policyAction = 'cycle.close';
    public string $policyRisk = 'normal';
    public string $policyStepsJson = '[]';
    public string $cycleApprovalReason = '';
    public string $approvalDecisionReason = '';
    public ?int $disputePoolId = null;
    public ?int $disputeUnitId = null;
    public string $disputeReason = '';
    public string $disputeResolution = '';

    public function mount(): void
    {
        $this->organizationId = Membership::query()->where('user_id', auth()->id())
            ->where('active', true)->orderBy('organization_id')->value('organization_id');
        if ($this->organizationId) {
            $this->cycleId = Cycle::query()->where('organization_id', $this->organizationId)
                ->latest('id')->value('id');
            $this->loadBranding();
            $this->loadKpiProfile();
        }
    }

    private function access(): TenantAccess { return app(TenantAccess::class); }
    private function audit(): AuditTrail { return app(AuditTrail::class); }

    private function orgId(): int
    {
        if (!$this->organizationId) abort(403, 'Belum mempunyai organisasi.');
        $this->access()->membership($this->organizationId, (int)auth()->id());
        return $this->organizationId;
    }

    private function managers(): void
    {
        $this->access()->requireRole($this->orgId(), (int)auth()->id(), ['owner', 'admin']);
    }

    private function cycle(): Cycle
    {
        return Cycle::query()->where('organization_id', $this->orgId())
            ->findOrFail($this->cycleId);
    }

    private function instance(): QuestionInstance
    {
        $instance = QuestionInstance::query()->where('organization_id', $this->orgId())
            ->where('cycle_id', $this->cycleId)->findOrFail($this->instanceId);
        $this->access()->requireReadInstance($instance, (int)auth()->id());
        return $instance;
    }

    private function book(): Book
    {
        $book = Book::query()->where('organization_id', $this->orgId())
            ->where('cycle_id', $this->cycleId)->findOrFail($this->bookId);
        $this->access()->requireRole($book->organization_id, (int)auth()->id(),
            ['owner', 'admin', 'editor', 'reviewer', 'publisher']);
        return $book;
    }

    public function createOrganization(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['superadmin', 'admin-keuangan']), 403);
        $this->validate(['orgName' => 'required|string|min:3|max:150']);
        DB::transaction(function () {
            $org = Organization::create([
                'name' => trim($this->orgName),
                'brand_name' => trim($this->orgName),
                'slug' => Str::slug($this->orgName).'-'.Str::lower(Str::random(7)),
                'currency' => config('rka_platform.currency', 'IDR'),
                'locale' => config('rka_platform.locale', 'id'),
                'timezone' => config('rka_platform.timezone', 'Asia/Jakarta'),
            ]);
            Membership::create([
                'organization_id' => $org->id, 'user_id' => auth()->id(), 'roles' => ['owner'],
            ]);
            $this->audit()->record($org->id, (int)auth()->id(), 'organization.created', 'organization', $org->id);
            $this->organizationId = $org->id;
        });
        $this->orgName = '';
        $this->loadBranding();
        $this->notify('success', 'Organisasi dibuat.');
    }

    public function selectOrganization(int $id): void
    {
        $this->access()->membership($id, (int)auth()->id());
        $this->organizationId = $id;
        $this->cycleId = Cycle::query()->where('organization_id', $id)->latest('id')->value('id');
        $this->instanceId = $this->bookId = $this->sectionId = null;
        $this->loadBranding();
        $this->loadKpiProfile();
    }

    private function loadBranding(): void
    {
        if (!$this->organizationId) return;
        $org = Organization::findOrFail($this->orgId());
        $this->orgBrand = $org->brand_name ?: $org->name;
        $this->orgCurrency = $org->currency;
        $this->orgLocale = $org->locale;
        $this->orgTimezone = $org->timezone;
        $this->orgColor = $org->primary_color;
    }

    public function updateBranding(): void
    {
        $this->managers();
        $this->validate([
            'orgBrand' => 'required|string|max:150',
            'orgCurrency' => 'required|regex:/^[A-Z]{3}$/',
            'orgLocale' => 'required|regex:/^[a-z]{2}(?:_[A-Z]{2})?$/',
            'orgTimezone' => 'required|timezone',
            'orgColor' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
        ]);
        $org = Organization::findOrFail($this->orgId());
        if ($org->currency !== $this->orgCurrency) {
            $approvedBudgetExists = BudgetLine::query()->where('organization_id', $org->id)
                ->where('status', 'approved')->exists();
            $approvedPoolExists = SharedPool::query()->where('organization_id', $org->id)
                ->where('status', 'approved')->exists();
            $publishedBookExists = Book::query()->where('organization_id', $org->id)
                ->where('status', 'published')->exists();
            if ($approvedBudgetExists || $approvedPoolExists || $publishedBookExists) {
                throw ValidationException::withMessages([
                    'orgCurrency' => 'Mata uang tidak boleh diganti setelah ada anggaran/alokasi yang disahkan atau buku diterbitkan.',
                ]);
            }
        }
        $org->update([
            'brand_name' => $this->orgBrand, 'currency' => $this->orgCurrency,
            'locale' => $this->orgLocale, 'timezone' => $this->orgTimezone,
            'primary_color' => $this->orgColor,
        ]);
        $this->audit()->record($org->id, (int)auth()->id(), 'organization.branding_updated', 'organization', $org->id);
        $this->notify('success', 'Pengaturan organisasi diperbarui.');
    }

    public function addMember(): void
    {
        $this->managers();
        $this->validate([
            'memberEmail' => 'required|email|max:255',
            'memberRole' => 'required|in:admin,finance,reviewer,editor,publisher,contributor,unit_manager',
            'memberUnitId' => 'nullable|integer',
        ]);
        $user = User::query()->where('email', $this->memberEmail)->first();
        if (!$user) throw ValidationException::withMessages(['memberEmail' => 'Pengguna harus memiliki akun existing.']);
        $existing = Membership::query()->where('organization_id', $this->orgId())
            ->where('user_id', $user->id)->first();
        if ($existing && in_array('owner', $existing->roles ?? [], true)) {
            throw ValidationException::withMessages(['memberEmail' => 'Kewenangan owner tidak boleh ditimpa.']);
        }
        if ($this->memberUnitId !== null) {
            Unit::where('organization_id', $this->orgId())->findOrFail($this->memberUnitId);
        }
        if ($this->memberRole === 'unit_manager' && $this->memberUnitId === null) {
            throw ValidationException::withMessages(['memberUnitId' => 'Penanggung jawab unit wajib memiliki unit.']);
        }
        $membership = Membership::updateOrCreate(
            ['organization_id' => $this->orgId(), 'user_id' => $user->id],
            ['roles' => [$this->memberRole], 'unit_id' => $this->memberUnitId, 'active' => true]
        );
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'membership.assigned', 'membership', $membership->id, ['role' => $this->memberRole]);
        $this->memberEmail = '';
        $this->memberUnitId = null;
        $this->notify('success', 'Anggota ditambahkan.');
    }

    public function createUnit(): void
    {
        $this->managers();
        $this->validate(['unitCode' => 'required|max:50|regex:/^[a-zA-Z0-9_.-]+$/',
            'unitName' => 'required|max:160']);
        $unit = Unit::create([
            'organization_id' => $this->orgId(), 'code' => $this->unitCode, 'name' => $this->unitName,
        ]);
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'unit.created', 'unit', $unit->id);
        $this->unitCode = $this->unitName = '';
        $this->notify('success', 'Unit dibuat.');
    }

    private function loadKpiProfile(): void
    {
        if (!$this->organizationId || !$this->cycleId) return;
        $cycle = Cycle::where('organization_id', $this->orgId())->find($this->cycleId);
        $this->kpiProfileJson = json_encode(($cycle?->settings ?? [])['kpi_profile'] ??
            ['income_categories' => [], 'expense_categories' => []], JSON_UNESCAPED_UNICODE);
    }

    public function saveKpiProfile(): void
    {
        $this->access()->requireRole($this->orgId(), (int)auth()->id(), ['owner','admin','finance']);
        $cycle = $this->cycle();
        try {
            $profile = json_decode($this->kpiProfileJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['kpiProfileJson' => 'Format profil harus JSON yang valid.']);
        }
        if (!is_array($profile)) {
            throw ValidationException::withMessages(['kpiProfileJson' => 'Profil KPI harus berupa objek JSON.']);
        }
        app(\App\Services\RkaPlatform\KpiBudgetPreviewService::class)->validateProfile($profile);
        if ($cycle->status !== 'draft' || BudgetLine::where('organization_id', $cycle->organization_id)
            ->where('cycle_id', $cycle->id)->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['kpiProfileJson' => 'Profil dikunci setelah anggaran disetujui.']);
        }
        $cycle->update(['settings' => array_merge($cycle->settings ?? [], ['kpi_profile' => $profile])]);
        $this->audit()->record($cycle->organization_id, (int)auth()->id(), 'kpi_profile.updated', 'cycle', $cycle->id);
        $this->notify('success', 'Profil KPI disimpan untuk siklus ini.');
    }

    public function createCycle(): void
    {
        $this->managers();
        $this->validate([
            'cycleName' => 'required|max:160', 'cycleYear' => 'required|integer|between:2000,2100',
            'cycleStart' => 'required|date', 'cycleEnd' => 'required|date|after:cycleStart',
        ]);
        $cycle = Cycle::create([
            'organization_id' => $this->orgId(), 'name' => $this->cycleName, 'year' => $this->cycleYear,
            'starts_on' => $this->cycleStart, 'ends_on' => $this->cycleEnd,
        ]);
        $this->cycleId = $cycle->id;
        $this->loadKpiProfile();
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'cycle.created', 'cycle', $cycle->id);
        $this->notify('success', 'Siklus RKA dibuat.');
    }

    public function selectCycle(int $id): void
    {
        Cycle::query()->where('organization_id', $this->orgId())->findOrFail($id);
        $this->cycleId = $id;
        $this->instanceId = $this->bookId = $this->sectionId = null;
        $this->loadKpiProfile();
    }

    public function previewLegacy(): void
    {
        abort_unless(auth()->user()->hasRole('superadmin'), 403);
        $this->validate(['legacySource' => 'required|string', 'legacyUnitId' => 'required|integer']);
        $this->legacyPreview = app(\App\Services\RkaPlatform\LegacyBudgetPreviewService::class)
            ->preview((int)auth()->id(), $this->legacySource, $this->legacyUnitId);
    }

    public function createQuestion(): void
    {
        $this->managers();
        $this->validate([
            'questionCode' => 'required|max:70|regex:/^[a-zA-Z0-9_.-]+$/',
            'questionText' => 'required|string|max:15000',
            'questionDomain' => 'required|string|max:80',
            'questionOwnerRole' => 'required|string|max:80',
        ]);
        try {
            $rules = json_decode($this->questionRulesJson, true, 512, JSON_THROW_ON_ERROR);
            $evidence = json_decode($this->questionEvidenceJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['questionRulesJson' => 'Format JSON pertanyaan tidak valid.']);
        }
        if (!is_array($rules) || !is_array($evidence)) {
            throw ValidationException::withMessages(['questionRulesJson' => 'Aturan harus berupa objek JSON.']);
        }
        app(QuestionRules::class)->applies($rules, []);
        $question = Question::create([
            'organization_id' => $this->orgId(), 'code' => $this->questionCode,
            'prompt' => $this->questionText, 'domain' => $this->questionDomain,
            'owner_role' => $this->questionOwnerRole, 'rules' => $rules, 'evidence_policy' => $evidence,
        ]);
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'question.created', 'question', $question->id);
        $this->newQuestionId = $question->id;
        $this->questionCode = $this->questionText = '';
        $this->notify('success', 'Pertanyaan ditambahkan.');
    }

    public function createInstance(): void
    {
        $this->managers(); $cycle = $this->cycle();
        $this->validate([
            'newQuestionId' => 'required|integer', 'newOwnerUserId' => 'required|integer',
            'newReviewerUserId' => 'required|integer|different:newOwnerUserId',
            'newUnitId' => 'nullable|integer',
        ]);
        $question = Question::query()->where('organization_id', $this->orgId())
            ->where('active', true)->findOrFail($this->newQuestionId);
        $this->access()->membership($this->orgId(), $this->newOwnerUserId);
        $this->access()->requireRole($this->orgId(), $this->newReviewerUserId, ['owner','admin','reviewer']);
        if ($this->newUnitId !== null) {
            Unit::query()->where('organization_id', $this->orgId())->findOrFail($this->newUnitId);
        }
        try {
            $context = json_decode($this->questionContextJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['questionContextJson' => 'Format konteks JSON tidak valid.']);
        }
        if (!is_array($context) || !app(QuestionRules::class)->applies($question->rules ?? [], $context)) {
            throw ValidationException::withMessages(['questionContextJson' => 'Kondisi pertanyaan tidak cocok.']);
        }
        $instance = QuestionInstance::create([
            'organization_id' => $this->orgId(), 'cycle_id' => $cycle->id,
            'question_id' => $question->id, 'owner_user_id' => $this->newOwnerUserId,
            'reviewer_user_id' => $this->newReviewerUserId, 'unit_id' => $this->newUnitId,
            'prompt_snapshot' => $question->prompt, 'rules_snapshot' => $question->rules,
        ]);
        $this->instanceId = $instance->id;
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'question.assigned', 'question_instance', $instance->id);
        $this->notify('success', 'Pertanyaan ditugaskan.');
    }

    public function selectInstance(int $id): void
    {
        $instance = QuestionInstance::query()->where('organization_id', $this->orgId())
            ->where('cycle_id', $this->cycle()->id)->findOrFail($id);
        $this->access()->requireReadInstance($instance, (int)auth()->id());
        $this->instanceId = $id;
        $this->answerRevision = (int)(Answer::query()->where('instance_id', $id)
            ->where('author_id', auth()->id())->max('revision') ?? 0);
        $this->answerText = (string)(Answer::query()->where('instance_id', $id)
            ->where('author_id', auth()->id())->orderByDesc('revision')->value('content') ?? '');
        $this->synthesisAnswerIds = [];
        $this->synthesisText = '';
    }

    public function invite(): void
    {
        $this->validate(['inviteUserId' => 'required|integer', 'inviteDays' => 'required|integer|between:1,30']);
        app(ContributionService::class)->invite($this->instance(), (int)auth()->id(), $this->inviteUserId, $this->inviteDays);
        $this->notify('success', 'Undangan dibuat.');
    }

    public function acceptInvite(int $id): void
    {
        $invite = Invitation::query()->where('organization_id', $this->orgId())->findOrFail($id);
        app(ContributionService::class)->accept($invite, (int)auth()->id());
        $this->notify('success', 'Undangan diterima.');
    }

    public function revokeInvite(int $id): void
    {
        $invite = $this->instance()->invitations()->findOrFail($id);
        app(ContributionService::class)->revoke($invite, (int)auth()->id());
        $this->notify('success', 'Undangan dicabut.');
    }

    public function uploadAnswerEvidence(): void
    {
        $instance = $this->instance();
        $this->validate(['answerEvidenceFile' => 'required|file|mimes:pdf,docx,xlsx,jpg,jpeg,png|max:10240']);
        $this->access()->requireAnswerInstance($instance, (int)auth()->id());
        $answer = Answer::query()->where('instance_id', $instance->id)
            ->where('author_id', auth()->id())->orderByDesc('revision')->firstOrFail();
        app(EvidenceService::class)->upload($answer, (int)auth()->id(), $this->answerEvidenceFile);
        $this->answerEvidenceFile = null;
        $this->notify('success', 'Bukti disimpan secara privat untuk verifikasi.');
    }

    public function verifyEvidence(int $id): void
    {
        $evidence = EvidenceRecord::query()->where('organization_id', $this->orgId())
            ->where('instance_id', $this->instance()->id)->findOrFail($id);
        app(EvidenceService::class)->verify($evidence, (int)auth()->id());
        $this->notify('success', 'Bukti diverifikasi.');
    }

    public function submitAnswer(): void
    {
        $this->validate(['answerText' => 'required|string|max:100000']);
        $answer = app(ContributionService::class)->submitAnswer(
            $this->instance(), (int)auth()->id(), $this->answerText, $this->answerRevision
        );
        $this->answerRevision = $answer->revision;
        $this->notify('success', 'Jawaban disimpan dengan revisi baru.');
    }

    public function synthesize(): void
    {
        $this->validate(['synthesisText' => 'required|string|max:100000', 'synthesisAnswerIds' => 'required|array|min:1']);
        app(ContributionService::class)->synthesize(
            $this->instance(), (int)auth()->id(), $this->synthesisText, $this->synthesisAnswerIds
        );
        $this->notify('success', 'Sintesis diajukan untuk review.');
    }

    public function verifySynthesis(int $id): void
    {
        $synthesis = Synthesis::query()->where('organization_id', $this->orgId())->findOrFail($id);
        app(ContributionService::class)->verify($synthesis, (int)auth()->id());
        $this->notify('success', 'Sintesis diverifikasi.');
    }

    public function addBudget(): void
    {
        $this->access()->requireRole($this->orgId(), (int)auth()->id(), ['owner','admin','finance']);
        $cycle = $this->cycle();
        if ($cycle->status !== 'draft') throw ValidationException::withMessages(['cycle' => 'Siklus sudah ditutup.']);
        $this->validate(['lineName' => 'required|string|max:180', 'lineCategory' => 'required|max:80',
            'lineAmount' => 'required|numeric|min:0']);
        if ($this->lineUnitId) Unit::query()->where('organization_id', $this->orgId())->findOrFail($this->lineUnitId);
        $line = BudgetLine::create([
            'organization_id' => $this->orgId(), 'cycle_id' => $cycle->id,
            'unit_id' => $this->lineUnitId, 'name' => $this->lineName,
            'category' => $this->lineCategory, 'amount' => $this->lineAmount,
            'created_by' => auth()->id(),
        ]);
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'budget.created', 'budget_line', $line->id);
        $this->lineName = $this->lineAmount = '';
        $this->notify('success', 'Baris anggaran draft dibuat.');
    }

    public function createPool(): void
    {
        $this->access()->requireRole($this->orgId(), (int)auth()->id(), ['owner','admin','finance']);
        $cycle = $this->cycle();
        if ($cycle->status !== 'draft') throw ValidationException::withMessages(['cycle' => 'Siklus sudah ditutup.']);
        $this->validate(['poolName' => 'required|max:160', 'poolCode' => 'required|max:70',
            'poolAmount' => 'required|numeric|min:0', 'poolMethod' => 'required|in:equal,weighted']);
        $drivers = array_filter($this->poolDrivers, fn ($value) => is_numeric($value) && (float)$value > 0);
        if (!$drivers) throw ValidationException::withMessages(['poolDrivers' => 'Pilih minimal satu unit penerima.']);
        $pool = SharedPool::create([
            'organization_id' => $this->orgId(), 'cycle_id' => $cycle->id,
            'name' => $this->poolName, 'code' => $this->poolCode,
            'amount' => $this->poolAmount, 'method' => $this->poolMethod, 'drivers' => $drivers,
            'created_by' => auth()->id(),
        ]);
        app(SharedCostAllocator::class)->saveDraft($pool, (int)auth()->id(), $this->access(), $this->audit());
        $this->poolName = $this->poolCode = $this->poolAmount = '';
        $this->poolDrivers = [];
        $this->notify('success', 'Alokasi draft dihitung.');
    }

    public function approveBudget(int $id): void
    {
        $line = BudgetLine::query()->where('organization_id', $this->orgId())
            ->where('cycle_id', $this->cycle()->id)->findOrFail($id);
        app(FinancialApprovalService::class)->approveBudget($line, (int)auth()->id());
        $this->notify('success', 'Anggaran disetujui.');
    }

    public function approvePool(int $id): void
    {
        $pool = SharedPool::query()->where('organization_id', $this->orgId())
            ->where('cycle_id', $this->cycle()->id)->findOrFail($id);
        app(FinancialApprovalService::class)->approvePool($pool, (int)auth()->id());
        $this->notify('success', 'Alokasi disetujui.');
    }

    public function importJournal(): void
    {
        $this->validate(['journalFile' => 'required|file|mimes:xlsx|max:10240', 'journalSource' => 'required|max:60']);
        try {
            $mapping = json_decode($this->journalMappingJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['journalMappingJson' => 'Format mapping JSON tidak valid.']);
        }
        if (!is_array($mapping)) throw ValidationException::withMessages(['journalMappingJson' => 'Mapping harus objek JSON.']);
        $batch = app(JournalSpreadsheetImporter::class)->import(
            $this->orgId(), (int)auth()->id(), $this->journalFile, $this->journalSource, $mapping
        );
        $this->journalFile = null;
        $this->notify('success', 'Batch XLSX diterima: '.$batch->id.' (status staged).');
    }

    public function draftJournalMapping(): void
    {
        $this->validate([
            'mappingSource' => 'required|regex:/^[a-zA-Z0-9_.-]{2,60}$/',
            'mappingAccount' => 'required|string|max:255',
            'mappingClass' => 'required|in:income,expense,internal_income,internal_expense,clearing,non_pnl',
            'mappingUnitId' => 'nullable|integer',
        ]);
        app(FinancialReconciliationService::class)->createMapping(
            $this->cycle(), (int)auth()->id(), $this->mappingSource, $this->mappingAccount,
            $this->mappingClass, $this->mappingUnitId
        );
        $this->mappingAccount = '';
        $this->notify('success', 'Mapping tersimpan sebagai draft dan menunggu reviewer independen.');
    }

    public function approveJournalMapping(int $id): void
    {
        $row = DB::table('rka_journal_account_mappings')->where('organization_id',$this->orgId())
            ->where('cycle_id', $this->cycle()->id)->where('id',$id)->first();
        if (!$row) abort(404);
        app(FinancialReconciliationService::class)->approveMapping($this->orgId(), $id, (int)auth()->id());
        $this->notify('success', 'Mapping diaktifkan.');
    }

    public function verifyJournalBatch(int $id): void
    {
        $batch = JournalBatch::where('organization_id', $this->orgId())->findOrFail($id);
        app(FinancialReconciliationService::class)->verifyBatch($batch, (int)auth()->id());
        $this->notify('success', 'Integritas sumber batch diverifikasi. Rekonsiliasi tetap diperlukan.');
    }

    public function prepareReconciliation(): void
    {
        $id = app(FinancialReconciliationService::class)->prepare($this->cycle(), (int)auth()->id());
        $this->notify('success', 'Laporan rekonsiliasi #'.$id.' tersimpan. Periksa blocker sebelum mengesahkan.');
    }

    public function verifyReconciliation(int $id): void
    {
        app(FinancialReconciliationService::class)->verify($this->cycle(), $id, (int)auth()->id());
        $this->notify('success', 'Snapshot rekonsiliasi diverifikasi secara independen.');
    }

    public function proposeClosingPolicy(): void
    {
        $this->managers();
        $this->validate([
            'policyAction' => 'required|in:cycle.close,cycle.reopen',
            'policyRisk' => 'required|in:normal,high',
        ]);
        try { $steps = json_decode($this->policyStepsJson, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) {
            throw ValidationException::withMessages(['policyStepsJson' => 'Tahapan approval harus JSON yang valid.']);
        }
        if (!is_array($steps)) throw ValidationException::withMessages(['policyStepsJson' => 'Gunakan array tahapan approval.']);
        app(FinancialClosingService::class)->proposePolicy(
            $this->orgId(), (int)auth()->id(), $this->policyAction, $steps, $this->policyRisk
        );
        $this->notify('success', 'Kebijakan draft dibuat dan memerlukan aktivasi terpisah.');
    }

    public function activateClosingPolicy(int $id): void
    {
        $policy = ApprovalPolicy::where('organization_id',$this->orgId())->findOrFail($id);
        app(FinancialClosingService::class)->activatePolicy($policy, (int)auth()->id());
        $this->notify('success', 'Kebijakan closing diaktifkan.');
    }

    public function submitCycleApproval(string $action): void
    {
        if (!in_array($action, ['cycle.close','cycle.reopen'], true)) abort(404);
        $this->validate(['cycleApprovalReason' => 'required|string|min:10|max:3000']);
        app(FinancialClosingService::class)->request(
            $this->cycle(), (int)auth()->id(), $action, $this->cycleApprovalReason
        );
        $this->cycleApprovalReason = '';
        $this->notify('success', 'Permintaan closing/reopen diajukan ke tahap persetujuan pertama.');
    }

    public function decideCycleApproval(int $id, bool $approve): void
    {
        $req = ApprovalRequest::where('organization_id',$this->orgId())
            ->where('subject_type','cycle')->where('subject_id',$this->cycle()->id)->findOrFail($id);
        if (!$approve) $this->validate(['approvalDecisionReason' => 'required|string|min:10|max:2000']);
        app(FinancialClosingService::class)->decide(
            $req, (int)auth()->id(), $approve, $approve ? '' : $this->approvalDecisionReason
        );
        $this->approvalDecisionReason = '';
        $this->notify('success', 'Keputusan persetujuan dicatat.');
    }

    public function raiseSharingDispute(): void
    {
        $this->validate([
            'disputePoolId' => 'required|integer', 'disputeUnitId' => 'required|integer',
            'disputeReason' => 'required|string|min:10|max:2000',
        ]);
        app(FinancialClosingService::class)->raiseDispute(
            $this->cycle(), (int)auth()->id(), $this->disputePoolId, $this->disputeUnitId,
            $this->disputeReason
        );
        $this->disputeReason = '';
        $this->notify('success', 'Sengketa tercatat; closing tertahan sampai diselesaikan.');
    }

    public function resolveSharingDispute(int $id): void
    {
        $this->validate(['disputeResolution' => 'required|string|min:10|max:2000']);
        app(FinancialClosingService::class)->resolveDispute(
            $this->cycle(), $id, (int)auth()->id(), $this->disputeResolution
        );
        $this->disputeResolution = '';
        $this->notify('success', 'Resolusi sengketa tercatat untuk audit.');
    }

    public function createTemplate(): void
    {
        $this->managers();
        $this->validate(['templateName' => 'required|max:160', 'templateSections' => 'required|string|max:20000']);
        $titles = array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->templateSections))));
        if (!$titles || count($titles) > 100) throw ValidationException::withMessages(['templateSections' => '1–100 judul bagian diperlukan.']);
        $sections = [];
        foreach ($titles as $i => $title) $sections[] = [
            'code' => 's'.($i+1), 'title' => $title, 'required' => true,
        ];
        $template = BookTemplate::create([
            'organization_id' => $this->orgId(), 'name' => $this->templateName, 'sections' => $sections,
        ]);
        $this->newTemplateId = $template->id;
        $this->audit()->record($this->orgId(), (int)auth()->id(), 'template.created', 'book_template', $template->id);
        $this->notify('success', 'Template dibuat tanpa isi contoh organisasi.');
    }

    public function createBook(): void
    {
        $this->access()->requireRole($this->orgId(), (int)auth()->id(), ['owner','admin','editor']);
        $cycle = $this->cycle();
        $this->validate(['newTemplateId' => 'required|integer', 'bookTitle' => 'required|max:160']);
        $template = BookTemplate::query()->where('organization_id', $this->orgId())
            ->where('active', true)->findOrFail($this->newTemplateId);
        $book = DB::transaction(function () use ($cycle, $template) {
            $book = Book::create([
                'organization_id' => $this->orgId(), 'cycle_id' => $cycle->id,
                'template_id' => $template->id, 'title' => $this->bookTitle,
            ]);
            foreach ($template->sections as $i => $section) {
                BookSection::create([
                    'book_id' => $book->id, 'code' => $section['code'],
                    'title' => $section['title'], 'position' => $i,
                    'required' => (bool)($section['required'] ?? false),
                ]);
            }
            $this->audit()->record($this->orgId(), (int)auth()->id(), 'book.created', 'book', $book->id);
            return $book;
        });
        $this->bookId = $book->id;
        $this->bookTitle = '';
        $this->notify('success', 'Draft buku dibuat.');
    }

    public function selectBook(int $id): void
    {
        $book = Book::query()->where('organization_id', $this->orgId())
            ->where('cycle_id', $this->cycle()->id)->findOrFail($id);
        $this->access()->requireRole($book->organization_id, (int)auth()->id(),
            ['owner','admin','editor','reviewer','publisher']);
        $this->bookId = $id; $this->sectionId = null;
    }

    public function selectSection(int $id): void
    {
        $section = $this->book()->sections()->findOrFail($id);
        $this->sectionId = $section->id;
        $this->sectionContent = $section->content ?? '';
    }

    public function saveSection(): void
    {
        $section = $this->book()->sections()->findOrFail($this->sectionId);
        $this->validate(['sectionContent' => 'required|string|max:1000000']);
        app(BookPublishingService::class)->saveSection($section, (int)auth()->id(), $this->sectionContent);
        $this->notify('success', 'Bagian dikirim ke review.');
    }

    public function approveSection(int $id): void
    {
        $section = $this->book()->sections()->findOrFail($id);
        app(BookPublishingService::class)->approveSection($section, (int)auth()->id());
        $this->notify('success', 'Bagian disetujui.');
    }

    public function publishBook(): void
    {
        app(BookPublishingService::class)->publish($this->book(), (int)auth()->id());
        $this->notify('success', 'Snapshot Buku RKA telah diterbitkan.');
    }

    protected function getViewData(): array
    {
        $memberships = Membership::query()->where('user_id', auth()->id())->where('active', true)->get();
        $organizations = Organization::query()->whereIn('id', $memberships->pluck('organization_id'))->orderBy('name')->get();
        $data = [
            'organizations' => $organizations,
            'canBootstrap' => auth()->user()->hasAnyRole(['superadmin','admin-keuangan']),
            'org' => null, 'isManager' => false, 'isFinance' => false,
            'isInstanceOwner' => false, 'canAnswer' => false, 'canReview' => false,
            'canEditBook' => false, 'canApproveBookSection' => false, 'evidence' => collect(),
            'canPreviewLegacy' => false, 'legacyUnits' => collect(),
            'kpiPreview' => null,
            'accountMappings' => collect(), 'reconRuns' => collect(),
            'closingPolicies' => collect(), 'cycleApprovals' => collect(),
            'cycleClosings' => collect(), 'sharingDisputes' => collect(), 'disputePools' => collect(),
            'canControlClose' => false, 'canRaiseDispute' => false, 'cycleCurrent' => null,
            'units' => collect(), 'users' => collect(), 'cycles' => collect(), 'questions' => collect(),
            'instances' => collect(), 'invitations' => collect(), 'answers' => collect(), 'syntheses' => collect(),
            'books' => collect(), 'bookSections' => collect(), 'templates' => collect(),
            'budgets' => collect(), 'pools' => collect(), 'batches' => collect(), 'conflicts' => collect(),
        ];
        if (!$this->organizationId) return $data;
        $orgId = $this->orgId();
        $data['org'] = Organization::findOrFail($orgId);
        $data['canPreviewLegacy'] = auth()->user()->hasRole('superadmin');
        if ($data['canPreviewLegacy']) {
            $data['legacyUnits'] = \App\Models\Unit::query()->orderBy('nama')->get(['id','nama']);
        }
        $data['isManager'] = $this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin']);
        $data['isFinance'] = $this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin','finance']);
        $data['canControlClose'] = $this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin','finance','publisher']);
        $data['canRaiseDispute'] = $this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin','finance','unit_manager','contributor']);
        $data['canEditBook'] = $this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin','editor']);
        $data['canApproveBookSection'] = $this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin','reviewer']);
        $data['units'] = Unit::where('organization_id', $orgId)->orderBy('name')->get();
        $memberIds = Membership::where('organization_id', $orgId)->where('active', true)->pluck('user_id');
        $data['users'] = User::whereIn('id', $memberIds)->orderBy('name')->get(['id','name','email']);
        $data['cycles'] = Cycle::where('organization_id', $orgId)->orderByDesc('year')->get();
        $data['questions'] = Question::where('organization_id', $orgId)->where('active', true)->orderBy('code')->get();
        $data['templates'] = BookTemplate::where('organization_id', $orgId)->orderBy('name')->get();
        $data['batches'] = $data['isFinance'] ? JournalBatch::where('organization_id', $orgId)->latest()->limit(12)->get() : collect();
        if ($this->cycleId) {
            $cycle = $this->cycle();
            $data['cycleCurrent'] = $cycle;
            if ($data['isFinance']) {
                $data['accountMappings'] = DB::table('rka_journal_account_mappings')
                    ->where('organization_id', $orgId)->where('cycle_id',$cycle->id)
                    ->orderByDesc('id')->limit(30)->get();
                $data['reconRuns'] = DB::table('rka_reconciliation_runs')
                    ->where('organization_id', $orgId)->where('cycle_id',$cycle->id)
                    ->orderByDesc('version')->limit(8)->get()->map(function ($run) {
                        $run->report_data = json_decode($run->report, true) ?: [];
                        return $run;
                    });
            }
            if ($data['isManager']) {
                $data['closingPolicies'] = ApprovalPolicy::where('organization_id', $orgId)
                    ->whereIn('action', ['cycle.close','cycle.reopen'])->latest()->limit(12)->get();
            }
            if ($data['canControlClose']) {
                $data['cycleApprovals'] = ApprovalRequest::where('organization_id', $orgId)
                    ->where('subject_type','cycle')->where('subject_id',$cycle->id)
                    ->latest()->limit(15)->get();
                $data['cycleClosings'] = DB::table('rka_cycle_closings')
                    ->where('organization_id',$orgId)->where('cycle_id',$cycle->id)
                    ->orderByDesc('version')->limit(5)->get();
            }
            if ($data['canRaiseDispute']) {
                $data['disputePools'] = SharedPool::where('organization_id',$orgId)
                    ->where('cycle_id',$cycle->id)->orderBy('name')->get(['id','name']);
                $disputeQuery = DB::table('rka_sharing_disputes')
                    ->where('organization_id',$orgId)->where('cycle_id',$cycle->id);
                if (!$data['isFinance']) {
                    $ownUnit = $this->access()->membership($orgId, (int)auth()->id())->unit_id;
                    $disputeQuery->where('unit_id', $ownUnit ?: 0);
                }
                $data['sharingDisputes'] = $disputeQuery->orderByDesc('id')->limit(30)->get();
            }
            $data['instances'] = QuestionInstance::where('organization_id', $orgId)->where('cycle_id', $cycle->id)
                ->where(function ($query) use ($orgId) {
                    if ($this->access()->hasRole($orgId, (int)auth()->id(), ['owner','admin'])) return;
                    $query->where('owner_user_id', auth()->id())
                        ->orWhere('reviewer_user_id', auth()->id())
                        ->orWhereHas('invitations', fn ($q) => $q->where('invited_user_id', auth()->id())
                            ->whereIn('status', ['invited','accepted','completed'])->where('expires_at', '>', now()));
                })->orderByDesc('id')->get();
            $data['books'] = $this->access()->hasRole($orgId, (int)auth()->id(),
                ['owner','admin','editor','reviewer','publisher'])
                ? Book::where('organization_id', $orgId)->where('cycle_id', $cycle->id)->latest()->get()
                : collect();
            $data['budgets'] = $data['isFinance'] ? BudgetLine::where('organization_id', $orgId)
                ->where('cycle_id', $cycle->id)->orderByDesc('id')->limit(30)->get() : collect();
            $data['pools'] = $data['isFinance'] ? SharedPool::where('organization_id', $orgId)
                ->where('cycle_id', $cycle->id)->orderByDesc('id')->get() : collect();
            $kpiProfile = ($cycle->settings ?? [])['kpi_profile'] ?? null;
            if ($data['isFinance'] && is_array($kpiProfile)) {
                $data['kpiPreview'] = app(\App\Services\RkaPlatform\KpiBudgetPreviewService::class)->preview($cycle, $kpiProfile);
            }
            $data['conflicts'] = $data['isManager'] ? NarrativeConflict::where('organization_id', $orgId)
                ->whereHas('instance', fn ($q) => $q->where('cycle_id', $cycle->id))
                ->where('status','open')->count() : 0;
        }
        if ($this->instanceId) {
            $instance = $this->instance();
            $data['invitations'] = $instance->invitations()->where(function ($q) {
                if ($this->access()->hasRole($this->orgId(), (int)auth()->id(), ['owner','admin']) ||
                    $this->instance()->owner_user_id === (int)auth()->id()) return;
                $q->where('invited_user_id', auth()->id());
            })->get();
            $data['isInstanceOwner'] = $instance->owner_user_id === (int)auth()->id();
            $data['canAnswer'] = $this->access()->canAnswerInstance($instance, (int)auth()->id());
            $data['canReview'] = $this->access()->canReviewInstance($instance, (int)auth()->id());
            $privileged = $this->access()->canViewAnswers($instance, (int)auth()->id());
            $answerQuery = $instance->answers();
            if (!$privileged) $answerQuery->where('author_id', auth()->id());
            $data['answers'] = $answerQuery->orderBy('author_id')->orderByDesc('revision')->get()
                ->unique('author_id')->values();
            $synthesisQuery = $instance->syntheses();
            if (!$privileged) $synthesisQuery->where('status', 'verified');
            $data['syntheses'] = $synthesisQuery->orderByDesc('version')->get();
            $evidenceQuery = EvidenceRecord::query()->where('organization_id', $orgId)
                ->where('instance_id', $instance->id);
            if (!$privileged) $evidenceQuery->where('uploaded_by', auth()->id());
            $data['evidence'] = $evidenceQuery->orderByDesc('id')->get();
        }
        if ($this->bookId) {
            $data['bookSections'] = $this->book()->sections()->get();
        }
        return $data;
    }
}

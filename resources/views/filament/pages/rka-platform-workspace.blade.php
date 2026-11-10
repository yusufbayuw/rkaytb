<x-filament::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
            <h2 class="text-xl font-semibold">Workspace RKA</h2>
            <p class="text-sm text-gray-500">Perencanaan dan Buku RKA lintas organisasi. Nama, unit, jabatan, pertanyaan dan template dikelola oleh pengguna, tanpa organisasi contoh.</p>
            @if($organizations->count())
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach($organizations as $organization)
                        <button type="button" wire:click="selectOrganization({{ $organization->id }})"
                            class="px-3 py-2 rounded-lg border {{ $organizationId === $organization->id ? 'bg-primary-50 border-primary-500' : 'dark:border-gray-600' }}">
                            {{ $organization->brand_name ?: $organization->name }}
                        </button>
                    @endforeach
                </div>
            @endif
            @if($canBootstrap)
                <form wire:submit.prevent="createOrganization" class="mt-4 flex flex-wrap gap-2 items-end">
                    <div><label class="block text-sm font-medium mb-1">Buat organisasi baru</label>
                        <input wire:model.defer="orgName" class="border rounded-lg p-2 dark:bg-gray-900" placeholder="Nama organisasi" /></div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white">Buat</button>
                </form>
            @endif
        </div>
        @if(!$org)
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">Belum ada organisasi yang dapat diakses. Hubungi administrator untuk mendapatkan penugasan, atau buat organisasi jika Anda berwenang.</div>
        @else
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5 space-y-2">
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $org->brand_name ?: $org->name }}</h2>
                        <p class="text-sm text-gray-500">RKA white-label · {{ $org->currency }} · {{ $org->timezone }}</p>
                    </div>
                    <div class="text-sm">Siklus aktif:
                        @if($cycles->count())
                            <select class="border rounded-lg p-2 dark:bg-gray-900" wire:change="selectCycle($event.target.value)">
                                @foreach($cycles as $cycle)
                                    <option value="{{ $cycle->id }}" @selected($cycleId === $cycle->id)>{{ $cycle->name }} — {{ $cycle->year }}</option>
                                @endforeach
                            </select>
                        @else
                            Belum ada siklus
                        @endif
                    </div>
                </div>
            </div>

            @if($isManager)
                <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                    <summary class="font-semibold cursor-pointer">Identitas Organisasi / White Label</summary>
                    <form wire:submit.prevent="updateBranding" class="grid md:grid-cols-2 gap-3 mt-4">
                        <div><label class="block text-xs mb-1">Nama tampilan</label><input wire:model.defer="orgBrand" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                        <div><label class="block text-xs mb-1">Warna utama (HEX)</label><input wire:model.defer="orgColor" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                        <div><label class="block text-xs mb-1">Mata uang (kode ISO)</label><input wire:model.defer="orgCurrency" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                        <div><label class="block text-xs mb-1">Locale</label><input wire:model.defer="orgLocale" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                        <div><label class="block text-xs mb-1">Zona waktu IANA</label><input wire:model.defer="orgTimezone" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                        <div class="flex items-end"><button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white">Simpan Pengaturan</button></div>
                    </form>
                    <p class="text-xs text-gray-500 mt-3">Logo dokumen dan pengaturan tipografi lanjutan belum tersedia pada tahap ini.</p>
                </details>
                <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                    <summary class="font-semibold cursor-pointer">Pengaturan Organisasi, Unit dan Pengguna</summary>
                    <div class="grid lg:grid-cols-3 gap-5 mt-4">
                        <form wire:submit.prevent="createUnit" class="space-y-2">
                            <h3 class="font-medium">Unit</h3>
                            <input wire:model.defer="unitCode" placeholder="Kode unik unit" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <input wire:model.defer="unitName" placeholder="Nama unit" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Tambah Unit</button>
                        </form>
                        <form wire:submit.prevent="addMember" class="space-y-2">
                            <h3 class="font-medium">Keanggotaan</h3>
                            <input wire:model.defer="memberEmail" type="email" placeholder="Email akun yang sudah terdaftar" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <select wire:model.defer="memberRole" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                @foreach(['contributor','editor','reviewer','finance','publisher','admin'] as $role)
                                    <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Tambahkan Anggota</button>
                        </form>
                        <form wire:submit.prevent="createCycle" class="space-y-2">
                            <h3 class="font-medium">Siklus Anggaran</h3>
                            <input wire:model.defer="cycleName" placeholder="Nama siklus" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <input wire:model.defer="cycleYear" type="number" placeholder="Tahun anggaran" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <label class="text-sm">Awal periode</label>
                            <input wire:model.defer="cycleStart" type="date" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <label class="text-sm">Akhir periode</label>
                            <input wire:model.defer="cycleEnd" type="date" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Buat Siklus</button>
                        </form>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">Unit aktif: {{ $units->count() }} · Anggota aktif: {{ $users->count() }} · Siklus: {{ $cycles->count() }}</p>
                </details>
            @endif

            @if($cycleId)
                @if($isManager)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Bank Pertanyaan dan Penugasan</summary>
                        <div class="grid lg:grid-cols-2 gap-5 mt-4">
                            <form wire:submit.prevent="createQuestion" class="space-y-2">
                                <h3 class="font-medium">Buat Pertanyaan</h3>
                                <input wire:model.defer="questionCode" placeholder="Kode pertanyaan" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <textarea wire:model.defer="questionText" rows="3" placeholder="Pertanyaan" class="w-full border rounded-lg p-2 dark:bg-gray-900"></textarea>
                                <input wire:model.defer="questionDomain" placeholder="Domain substansi" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <input wire:model.defer="questionOwnerRole" placeholder="Jabatan pemilik substansi" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <label class="text-xs">Rules JSON (opsional)</label>
                                <textarea wire:model.defer="questionRulesJson" rows="2" class="w-full border rounded-lg p-2 font-mono text-xs dark:bg-gray-900"></textarea>
                                <label class="text-xs">Kebijakan bukti JSON (opsional)</label>
                                <textarea wire:model.defer="questionEvidenceJson" rows="2" class="w-full border rounded-lg p-2 font-mono text-xs dark:bg-gray-900"></textarea>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Simpan Pertanyaan</button>
                            </form>
                            <form wire:submit.prevent="createInstance" class="space-y-2">
                                <h3 class="font-medium">Tugaskan Pertanyaan</h3>
                                <select wire:model.defer="newQuestionId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Pilih pertanyaan</option>
                                    @foreach($questions as $question)
                                        <option value="{{ $question->id }}">{{ $question->code }} — {{ \Illuminate\Support\Str::limit($question->prompt, 65) }}</option>
                                    @endforeach
                                </select>
                                <select wire:model.defer="newOwnerUserId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Pilih penanggung jawab</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                <label class="text-xs">Unit pemilik (opsional)</label>
                                <select wire:model.defer="newUnitId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Tidak terikat unit</option>
                                    @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                                </select>
                                <label class="text-xs">Reviewer independen</label>
                                <select wire:model.defer="newReviewerUserId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Pilih reviewer</option>
                                    @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                                </select>
                                <label class="text-xs">Konteks aturan JSON (opsional)</label>
                                <textarea wire:model.defer="questionContextJson" rows="2" class="w-full border rounded-lg p-2 font-mono text-xs dark:bg-gray-900"></textarea>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Buat Penugasan</button>
                            </form>
                        </div>
                    </details>
                @endif

                <div class="grid lg:grid-cols-3 gap-5">
                    <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <h3 class="font-semibold">Pertanyaan Saya dan Unit</h3>
                        <div class="mt-3 space-y-2 max-h-96 overflow-auto">
                            @forelse($instances as $instance)
                                <button type="button" wire:click="selectInstance({{ $instance->id }})"
                                    class="text-left block w-full border rounded-lg p-3 {{ $instanceId === $instance->id ? 'border-primary-500' : 'dark:border-gray-600' }}">
                                    <span class="block text-sm">{{ \Illuminate\Support\Str::limit($instance->prompt_snapshot, 95) }}</span>
                                    <span class="block text-xs text-gray-500 mt-1">{{ $instance->status }} · #{{ $instance->id }}</span>
                                </button>
                            @empty
                                <p class="text-sm text-gray-500 mt-3">Belum ada penugasan.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="lg:col-span-2 rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        @if($instanceId)
                            <h3 class="font-semibold">Jawaban dan Sintesis</h3>
                            <p class="text-xs text-gray-500 mt-1">Jawaban tiap kontributor disimpan sebagai versi tersendiri.</p>
                            @if($canAnswer)
                            <form wire:submit.prevent="submitAnswer" class="space-y-2 mt-4">
                                <label class="text-sm font-medium">Jawaban saya (revisi {{ $answerRevision }})</label>
                                <textarea wire:model.defer="answerText" rows="4" class="w-full border rounded-lg p-2 dark:bg-gray-900" placeholder="Berikan jawaban berdasarkan fakta dan sumber."></textarea>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Kirim Jawaban</button>
                            </form>
                            @if($answerRevision > 0)
                                <form wire:submit.prevent="uploadAnswerEvidence" class="flex flex-wrap gap-2 items-end mt-3">
                                    <div><label class="block text-xs mb-1">Bukti pendukung (privat)</label>
                                        <input wire:model="answerEvidenceFile" type="file" accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png" /></div>
                                    <button type="submit" class="px-3 py-2 rounded-lg border" wire:loading.attr="disabled">Unggah Bukti</button>
                                </form>
                            @endif
                            @endif
                            <div class="space-y-2 mt-5">
                                <h4 class="font-medium text-sm">Jawaban terkumpul</h4>
                                @forelse($answers as $answer)
                                    <label class="block border dark:border-gray-600 rounded-lg p-3 text-sm">
                                        @if($isManager || $isInstanceOwner)
                                            <input type="checkbox" value="{{ $answer->id }}" wire:model="synthesisAnswerIds" class="mr-2" />
                                        @endif
                                        <span class="text-xs text-gray-500">Pengguna #{{ $answer->author_id }} · Rev {{ $answer->revision }}</span>
                                        <p class="mt-2 whitespace-pre-wrap">{{ $answer->content }}</p>
                                    </label>
                                @empty
                                    <p class="text-sm text-gray-500">Belum ada jawaban.</p>
                                @endforelse
                            </div>
                            @if($isManager || $isInstanceOwner)
                            <form wire:submit.prevent="synthesize" class="mt-4 space-y-2">
                                <label class="text-sm font-medium">Draf sintesis berdasarkan jawaban terpilih</label>
                                <textarea wire:model.defer="synthesisText" rows="4" class="w-full border rounded-lg p-2 dark:bg-gray-900"></textarea>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Ajukan Sintesis</button>
                            </form>
                            @endif
                            <div class="mt-4 space-y-2">
                                <h4 class="font-medium text-sm">Bukti jawaban</h4>
                                @forelse($evidence as $document)
                                    <div class="border dark:border-gray-600 rounded-lg p-2 flex flex-wrap gap-3 justify-between text-sm">
                                        <span>{{ $document->original_name }} · {{ $document->status }}</span>
                                        <div class="flex gap-2">
                                            <a class="underline" href="{{ route('rka-platform.evidence.download', ['evidence' => $document->id]) }}">Unduh</a>
                                            @if($canReview && $document->status === 'pending' && $document->uploaded_by !== auth()->id())
                                                <button type="button" wire:click="verifyEvidence({{ $document->id }})" class="border rounded px-2">Verifikasi</button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-500">Belum ada bukti yang terunggah.</p>
                                @endforelse
                            </div>
                            <h4 class="font-medium text-sm mt-5">Riwayat sintesis</h4>
                            @foreach($syntheses as $synthesis)
                                <div class="border dark:border-gray-600 rounded-lg p-3 my-2 text-sm">
                                    <div class="flex justify-between"><span>Versi {{ $synthesis->version }}</span><span>{{ $synthesis->status }}</span></div>
                                    <p class="whitespace-pre-wrap my-2">{{ $synthesis->content }}</p>
                                    @if($canReview && $synthesis->status === 'pending_review' && $synthesis->author_id !== auth()->id())
                                        <button wire:click="verifySynthesis({{ $synthesis->id }})" class="px-3 py-1 border rounded-lg">Verifikasi (jika berwenang)</button>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <p class="text-sm text-gray-500">Pilih pertanyaan di kiri untuk mengisi jawaban, membuat sintesis, dan melihat hasil review.</p>
                        @endif
                    </div>
                </div>

                @if($instanceId)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Undangan Kontributor</summary>
                        @if($isManager || $isInstanceOwner)
                            <form wire:submit.prevent="invite" class="flex flex-wrap gap-3 items-end mt-4">
                                <div><label class="block text-sm mb-1">Pengguna</label>
                                    <select wire:model.defer="inviteUserId" class="border rounded-lg p-2 dark:bg-gray-900">
                                        <option value="">Pilih anggota</option>
                                        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                                    </select></div>
                                <div><label class="block text-sm mb-1">Masa berlaku (hari)</label>
                                    <input type="number" min="1" max="30" wire:model.defer="inviteDays" class="w-24 border rounded-lg p-2 dark:bg-gray-900" /></div>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Undang</button>
                            </form>
                        @endif
                        @forelse($invitations as $invitation)
                            <div class="flex flex-wrap gap-2 justify-between items-center border-b dark:border-gray-600 py-3 text-sm">
                                <span>Pengguna #{{ $invitation->invited_user_id }} · {{ $invitation->status }} · berlaku sampai {{ $invitation->expires_at->format('d M Y') }}</span>
                                @if($invitation->invited_user_id === auth()->id() && $invitation->status === 'invited')
                                    <button type="button" wire:click="acceptInvite({{ $invitation->id }})" class="px-3 py-1 border rounded-lg">Terima undangan</button>
                                @endif
                                @if(($isManager || $isInstanceOwner) && in_array($invitation->status, ['invited','accepted','completed']))
                                    <button type="button" wire:click="revokeInvite({{ $invitation->id }})" class="px-3 py-1 border rounded-lg">Cabut</button>
                                @endif
                            </div>
                        @empty
                            <p class="mt-3 text-sm text-gray-500">Belum ada undangan.</p>
                        @endforelse
                    </details>
                @endif

                @if($isFinance)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Anggaran dan Sharing Budget</summary>
                        <div class="grid lg:grid-cols-2 gap-5 mt-4">
                            <form wire:submit.prevent="addBudget" class="space-y-2">
                                <h3 class="font-medium">Baris Anggaran</h3>
                                <input wire:model.defer="lineName" placeholder="Nama anggaran" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <input wire:model.defer="lineCategory" placeholder="Kategori anggaran" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <input wire:model.defer="lineAmount" type="number" min="0" step="0.01" placeholder="Nominal" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <select wire:model.defer="lineUnitId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Tanpa unit</option>
                                    @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                                </select>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Tambah Draft</button>
                            </form>
                            <form wire:submit.prevent="createPool" class="space-y-2">
                                <h3 class="font-medium">Cost Pool dan Alokasi</h3>
                                <input wire:model.defer="poolCode" placeholder="Kode cost pool" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <input wire:model.defer="poolName" placeholder="Nama kelompok biaya" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <input wire:model.defer="poolAmount" type="number" min="0" step="0.01" placeholder="Nominal" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <select wire:model.defer="poolMethod" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="equal">Bobot sama</option>
                                    <option value="weighted">Bobot sesuai nilai yang diisi</option>
                                </select>
                                <p class="text-xs text-gray-500">Masukkan bobot positif untuk unit penerima. Nilai 0/tidak diisi berarti bukan penerima.</p>
                                @foreach($units as $unit)
                                    <div class="flex gap-2 items-center text-sm">
                                        <label class="flex-1">{{ $unit->name }}</label>
                                        <input wire:model.defer="poolDrivers.{{ $unit->id }}" type="number" min="0" step="0.001"
                                            placeholder="Bobot" class="w-24 border rounded-lg p-2 dark:bg-gray-900" />
                                    </div>
                                @endforeach
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Hitung Alokasi Draft</button>
                            </form>
                        </div>
                        <div class="mt-4 grid lg:grid-cols-2 gap-4">
                            <div>
                                <h4 class="font-medium text-sm">Baris Anggaran Terakhir</h4>
                                @foreach($budgets as $budget)
                                    <div class="flex flex-wrap justify-between gap-2 border-b dark:border-gray-600 py-2 text-sm">
                                        <span>{{ $budget->name }} · {{ $budget->amount }} ({{ $budget->status }})</span>
                                        @if($budget->status === 'draft' && $budget->created_by !== auth()->id())
                                            <button wire:click="approveBudget({{ $budget->id }})" class="border rounded px-2 py-1">Setujui</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div>
                                <h4 class="font-medium text-sm">Cost Pool</h4>
                                @foreach($pools as $pool)
                                    <div class="border-b dark:border-gray-600 py-2 text-sm">
                                        <strong>{{ $pool->name }}</strong> · {{ $pool->amount }} ({{ $pool->status }})
                                        <p class="text-xs text-gray-500 break-all">{{ json_encode($pool->allocations) }}</p>
                                        @if($pool->status === 'draft' && $pool->created_by !== auth()->id())
                                            <button wire:click="approvePool({{ $pool->id }})" class="border rounded px-2 py-1">Setujui Alokasi</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Alokasi ini adalah rencana manajerial, bukan posting jurnal ke sistem keuangan.</p>
                    </details>
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Impor Jurnal XLSX (Staging)</summary>
                        <form wire:submit.prevent="importJournal" class="space-y-3 mt-4">
                            <p class="text-sm text-gray-500">File harus berisi header: line_id, journal_no, date, account_code, debit, credit; kolom opsional: unit_code, posting_status, entry_kind, related_reference. Format header dapat dipetakan melalui JSON. Data masuk staging, belum menjadi laporan realisasi final.</p>
                            <input wire:model.defer="journalSource" placeholder="Identitas sistem sumber" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                            <label class="block text-xs">Mapping field ke nama header XLSX</label>
                            <textarea wire:model.defer="journalMappingJson" rows="2" class="w-full border rounded-lg p-2 font-mono text-xs dark:bg-gray-900"></textarea>
                            <input wire:model="journalFile" type="file" accept=".xlsx" class="block w-full" />
                            <button class="px-4 py-2 bg-primary-600 text-white rounded-lg" wire:loading.attr="disabled" wire:target="journalFile,importJournal">Impor ke Staging</button>
                        </form>
                        <div class="mt-3">
                            @foreach($batches as $batch)
                                <div class="border-b dark:border-gray-600 py-2 text-sm">Batch #{{ $batch->id }} · {{ $batch->source }} · {{ $batch->row_count }} baris · {{ $batch->status }}</div>
                            @endforeach
                        </div>
                    </details>
                @endif

                @if($isManager)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Template Buku RKA Dinamis</summary>
                        <div class="grid lg:grid-cols-2 gap-5 mt-4">
                            <form wire:submit.prevent="createTemplate" class="space-y-2">
                                <h3 class="font-medium">Template Baru</h3>
                                <input wire:model.defer="templateName" placeholder="Nama template" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <textarea wire:model.defer="templateSections" rows="5" placeholder="Satu judul bab/subbab per baris. Tidak ada bab bawaan." class="w-full border rounded-lg p-2 dark:bg-gray-900"></textarea>
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Simpan Template</button>
                            </form>
                            <form wire:submit.prevent="createBook" class="space-y-2">
                                <h3 class="font-medium">Buku RKA dari Template</h3>
                                <select wire:model.defer="newTemplateId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Pilih template</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}">{{ $template->name }} (v{{ $template->version }})</option>
                                    @endforeach
                                </select>
                                <input wire:model.defer="bookTitle" placeholder="Judul buku" class="w-full border rounded-lg p-2 dark:bg-gray-900" />
                                <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Buat Buku</button>
                            </form>
                        </div>
                    </details>
                @endif

                @if($books->count())
                    <div class="grid lg:grid-cols-3 gap-5">
                        <div class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                            <h3 class="font-semibold">Buku RKA</h3>
                            @foreach($books as $book)
                                <button wire:click="selectBook({{ $book->id }})"
                                    class="w-full text-left border dark:border-gray-600 rounded-lg p-3 mt-2 text-sm {{ $bookId === $book->id ? 'border-primary-500' : '' }}">
                                    {{ $book->title }} · {{ $book->status }}
                                </button>
                            @endforeach
                        </div>
                        <div class="lg:col-span-2 rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                            @if($bookId)
                                <h3 class="font-semibold">Editor dan Review Buku</h3>
                                <div class="space-y-2 mt-3">
                                    @foreach($bookSections as $section)
                                        <div class="flex flex-wrap items-center justify-between gap-2 border-b dark:border-gray-600 py-2 text-sm">
                                            <button wire:click="selectSection({{ $section->id }})" class="text-left underline">{{ $section->title }}</button>
                                            <span>{{ $section->status }}</span>
                                            @if($canApproveBookSection && $section->status === 'review' && $section->last_edited_by !== auth()->id())
                                                <button wire:click="approveSection({{ $section->id }})" class="px-2 py-1 border rounded">Setujui</button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @if($sectionId)
                                    @if($canEditBook)
                                    <form wire:submit.prevent="saveSection" class="mt-4 space-y-2">
                                        <label class="font-medium text-sm">Isi bagian terpilih</label>
                                        <textarea wire:model.defer="sectionContent" rows="7" class="w-full border rounded-lg p-2 dark:bg-gray-900" placeholder="Narasi bagian buku"></textarea>
                                        <button class="px-4 py-2 bg-primary-600 text-white rounded-lg">Ajukan Review Bagian</button>
                                    </form>
                                    @else
                                        <div class="mt-3 whitespace-pre-wrap text-sm">{{ $sectionContent }}</div>
                                    @endif
                                @endif
                                @php $selectedBook = $books->firstWhere('id', $bookId); @endphp
                                @if($selectedBook && $selectedBook->status === 'published')
                                    <div class="flex flex-wrap gap-3 mt-4">
                                        @foreach(['docx','pdf','xlsx'] as $format)
                                            <a class="underline text-primary-600" href="{{ route('rka-platform.books.download', ['book' => $bookId, 'format' => $format]) }}">Unduh {{ strtoupper($format) }}</a>
                                        @endforeach
                                    </div>
                                @elseif($selectedBook)
                                    @if(config('rka_platform.enable_official_publication', false))
                                        <button wire:click="publishBook" class="mt-4 px-4 py-2 bg-primary-600 text-white rounded-lg">Validasi dan Terbitkan Snapshot</button>
                                    @else
                                        <p class="mt-4 text-xs text-amber-700">Penerbitan resmi dinonaktifkan sampai validasi jurnal, KPI, dan laporan lengkap diimplementasikan.</p>
                                    @endif
                                @endif
                            @else
                                <p class="text-sm text-gray-500">Pilih buku untuk mulai menyusun dan mereview isi.</p>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-red-400 p-4 text-sm text-red-700 dark:text-red-300">
                <strong>Periksa isian:</strong>
                <ul class="list-disc ml-5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament::page>

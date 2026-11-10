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
                                @foreach(['contributor','unit_manager','editor','reviewer','finance','publisher','admin'] as $role)
                                    <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            <label class="block text-xs">Lingkup unit (wajib untuk penanggung jawab unit)</label>
                            <select wire:model.defer="memberUnitId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                <option value="">Semua / tanpa unit</option>
                                @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
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

            @if($canPreviewLegacy)
                <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                    <summary class="font-semibold cursor-pointer">Audit Referensi RKA Existing (Baca Saja)</summary>
                    <p class="text-xs text-gray-500 mt-2">Data lama tidak memiliki pemetaan organisasi dan tahun yang terverifikasi. Pratinjau ini tidak mengimpor atau menghitung ulang RKA.</p>
                    <form wire:submit.prevent="previewLegacy" class="flex gap-3 flex-wrap items-end mt-4">
                        <div><label class="block text-xs mb-1">Jenis sumber</label>
                            <select wire:model.defer="legacySource" class="border rounded-lg p-2 dark:bg-gray-900">
                                @foreach(\App\Services\RkaPlatform\LegacyBudgetPreviewService::SOURCES as $key => $table)
                                    <option value="{{ $key }}">{{ $key }} ({{ $table }})</option>
                                @endforeach
                            </select></div>
                        <div><label class="block text-xs mb-1">Unit RKA existing</label>
                            <select wire:model.defer="legacyUnitId" class="border rounded-lg p-2 dark:bg-gray-900">
                                <option value="">Pilih unit lama</option>
                                @foreach($legacyUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->nama }}</option>
                                @endforeach
                            </select></div>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Pratinjau</button>
                    </form>
                    @if($legacyPreview)
                        <div class="mt-4 space-y-2">
                            <p class="text-sm">{{ $legacyPreview['unit'] }} · {{ $legacyPreview['source'] }} · {{ $legacyPreview['count'] }} baris</p>
                            <p class="text-xs text-amber-700">{{ $legacyPreview['warning'] }}</p>
                            <div class="overflow-x-auto"><table class="w-full text-sm">
                                <thead><tr class="text-left"><th class="p-2">ID lama</th><th class="p-2">Akun</th><th class="p-2">Uraian</th><th class="p-2">Total tersimpan (teks)</th></tr></thead>
                                <tbody>
                                    @foreach($legacyPreview['sample'] as $item)
                                        <tr class="border-t dark:border-gray-700">
                                            <td class="p-2">{{ $item['id'] }}</td><td class="p-2">{{ $item['account'] }}</td>
                                            <td class="p-2">{{ $item['description'] }}</td><td class="p-2">{{ $item['total_as_stored'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        </div>
                    @endif
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
                        <summary class="font-semibold cursor-pointer">KPI Budget — Pratinjau Sebelum Sharing Budget</summary>
                        <form wire:submit.prevent="saveKpiProfile" class="space-y-2 mt-3">
                            <label class="text-sm font-medium block">Kategori pendapatan dan biaya untuk siklus ini</label>
                            <p class="text-xs text-gray-500">Daftar kategori ditentukan oleh organisasi. Format: {"income_categories":["REV"],"expense_categories":["COST"],"sharing_already_in_expense":false,"excluded_income_categories":["SPECIAL"]}. Kode di atas hanya ilustrasi sintaks, bukan aturan organisasi. Penandaan sharing wajib sebelum closing.</p>
                            <textarea wire:model.defer="kpiProfileJson" rows="3" class="w-full border rounded-lg p-2 font-mono text-xs dark:bg-gray-900"></textarea>
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Simpan Profil KPI</button>
                        </form>
                        @if($kpiPreview)
                            <div class="mt-4 space-y-2">
                                <p class="text-xs text-amber-700 font-medium">{{ $kpiPreview['warning'] }}</p>
                                <p class="text-xs text-gray-500">Draft: {{ $kpiPreview['pending_lines'] }} baris; kategori tidak dipetakan: {{ $kpiPreview['unmapped_lines'] }} baris; Cost Pool belum direkonsiliasi: {{ $kpiPreview['shared_pools'] }}.</p>
                                <div class="overflow-x-auto"><table class="w-full text-sm">
                                    <thead><tr class="text-left"><th class="p-2">Unit</th><th class="p-2">Pendapatan</th><th class="p-2">Biaya langsung</th><th class="p-2">Surplus sebelum sharing</th></tr></thead>
                                    <tbody>
                                        @foreach($kpiPreview['rows'] as $kpiRow)
                                            <tr class="border-t dark:border-gray-700"><td class="p-2">{{ $kpiRow['unit'] }}</td>
                                                <td class="p-2">{{ $kpiRow['income'] }}</td>
                                                <td class="p-2">{{ $kpiRow['cost'] }}</td>
                                                <td class="p-2">{{ $kpiRow['surplus_before_sharing'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table></div>
                                <p class="text-sm font-semibold">Total organisasi: {{ $kpiPreview['surplus_before_sharing'] }} (sebelum biaya bersama)</p>
                            </div>
                        @endif
                    </details>
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
                                <div class="border-b dark:border-gray-600 py-2 text-sm flex flex-wrap items-center justify-between gap-2">
                                    <span>Batch #{{ $batch->id }} · {{ $batch->source }} · {{ $batch->row_count }} baris · {{ $batch->status }}</span>
                                    @if($batch->status === 'staged' && $batch->imported_by !== auth()->id())
                                        <button type="button" wire:click="verifyJournalBatch({{ $batch->id }})" class="border rounded px-2 py-1">Verifikasi Sumber</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                @if($isFinance)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Pemetaan Akun dan Klasifikasi Jurnal</summary>
                        <p class="text-xs text-gray-500 mt-2">Setiap kode akun berasal dari sumber jurnal organisasi sendiri. Mapping bersifat efektif untuk satu siklus; pembuat dan pengesah harus berbeda. Jurnal asli tidak dimodifikasi.</p>
                        <form wire:submit.prevent="draftJournalMapping" class="grid md:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
                            <div><label class="block text-xs mb-1">Kode sumber</label><input wire:model.defer="mappingSource" class="w-full border rounded-lg p-2 dark:bg-gray-900" placeholder="Contoh: ledger_1" /></div>
                            <div><label class="block text-xs mb-1">Kode akun</label><input wire:model.defer="mappingAccount" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                            <div><label class="block text-xs mb-1">Perlakuan</label>
                                <select wire:model.defer="mappingClass" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    @foreach(['income'=>'Pendapatan eksternal','expense'=>'Beban','internal_income'=>'Pemulihan/internal income','internal_expense'=>'Beban internal','clearing'=>'Akun antara/clearing','non_pnl'=>'Neraca / bukan laba rugi'] as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="block text-xs mb-1">Kategori pendapatan akuntansi (opsional, untuk KPI dengan/tanpa kategori khusus)</label>
                                <input wire:model.defer="mappingCategory" class="w-full border rounded-lg p-2 dark:bg-gray-900" placeholder="Kode kategori organisasi" />
                            </div>
                            <div><label class="block text-xs mb-1">Unit default (opsional)</label>
                                <select wire:model.defer="mappingUnitId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Unit dari baris jurnal</option>
                                    @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                                </select>
                            </div>
                            <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white">Buat Draft Mapping</button>
                        </form>
                        <div class="overflow-x-auto mt-4">
                            <table class="w-full text-sm">
                                <thead><tr class="text-left"><th class="p-2">Sumber / akun</th><th class="p-2">Klasifikasi</th><th class="p-2">Periode</th><th class="p-2">Status</th><th class="p-2">Tindakan</th></tr></thead>
                                <tbody>
                                    @forelse($accountMappings as $mapping)
                                        <tr class="border-t dark:border-gray-700">
                                            <td class="p-2">{{ $mapping->source }} / {{ $mapping->account_code }}</td>
                                            <td class="p-2">{{ $mapping->classification }} @if($mapping->category) ({{ $mapping->category }}) @endif</td>
                                            <td class="p-2">{{ $mapping->valid_from }} – {{ $mapping->valid_to }}</td>
                                            <td class="p-2">{{ $mapping->active ? 'Disahkan' : 'Draft' }}</td>
                                            <td class="p-2">
                                                @if(!$mapping->active && $mapping->created_by !== auth()->id())
                                                    <button type="button" wire:click="approveJournalMapping({{ $mapping->id }})" class="border rounded px-2 py-1">Sahkan</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="p-2 text-gray-500">Belum ada mapping. Pilih akun dari file sumber sendiri.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Rekonsiliasi, Eliminasi Internal dan KPI Budget</summary>
                        <p class="text-xs text-gray-500 mt-2">Laporan sementara memperlihatkan blocker tanpa menyembunyikannya. Jurnal tidak seimbang, akun belum terpetakan, clearing terbuka, dan pasangan pendapatan/beban internal yang tidak cocok menghentikan pengesahan.</p>
                        <div class="mt-4">
                            @if($cycleCurrent && $cycleCurrent->status === 'draft')
                                <button type="button" wire:click="prepareReconciliation" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Hitung dan Simpan Rekonsiliasi Baru</button>
                            @endif
                        </div>
                        <div class="space-y-3 mt-4">
                            @forelse($reconRuns as $run)
                                <div class="border dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex gap-2 flex-wrap justify-between items-center">
                                        <strong>Versi {{ $run->version }} · {{ $run->status }} · #{{ $run->id }}</strong>
                                        @if($run->status === 'draft' && $run->prepared_by !== auth()->id() && ($run->report_data['blocker_count'] ?? 1) === 0)
                                            <button type="button" wire:click="verifyReconciliation({{ $run->id }})" class="border rounded px-3 py-1">Sahkan Rekonsiliasi</button>
                                        @endif
                                    </div>
                                    <p class="text-sm mt-2">Jurnal posted: {{ $run->report_data['posted_line_count'] ?? 0 }} · Blocker: <strong>{{ $run->report_data['blocker_count'] ?? '-' }}</strong></p>
                                    <div class="grid sm:grid-cols-3 gap-2 text-sm mt-3">
                                        <span>Pendapatan konsolidasi: {{ $run->report_data['consolidated']['income'] ?? '-' }}</span>
                                        <span>Beban konsolidasi: {{ $run->report_data['consolidated']['expense'] ?? '-' }}</span>
                                        <span>Surplus: {{ $run->report_data['consolidated']['surplus'] ?? '-' }}</span>
                                    </div>
                                    @if(!empty($run->report_data['blockers']))
                                        <details class="mt-3"><summary class="cursor-pointer text-sm text-red-700">Lihat masalah rekonsiliasi</summary>
                                            <div class="max-h-48 overflow-y-auto mt-2 space-y-1 text-xs">
                                                @foreach($run->report_data['blockers'] as $blocker)
                                                    <p>{{ $blocker['code'] }} — {{ $blocker['reference'] ?? '' }}</p>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                    <details class="mt-3"><summary class="cursor-pointer text-sm">KPI per unit (manajerial)</summary>
                                        <div class="overflow-x-auto mt-2"><table class="w-full text-xs">
                                            <thead><tr class="text-left"><th class="p-2">Unit</th><th class="p-2">Pendapatan anggaran</th><th class="p-2">Beban anggaran</th><th class="p-2">Sharing rencana</th><th class="p-2">Surplus rencana</th><th class="p-2">Surplus aktual</th></tr></thead>
                                            <tbody>
                                                @foreach(($run->report_data['unit_managerial'] ?? []) as $entry)
                                                    <tr class="border-t"><td class="p-2">{{ $entry['unit'] }}</td>
                                                        <td class="p-2">{{ $entry['plan_income'] }}</td><td class="p-2">{{ $entry['plan_expense'] }}</td>
                                                        <td class="p-2">{{ $entry['plan_sharing'] }}</td><td class="p-2">{{ $entry['plan_surplus'] }}</td>
                                                        <td class="p-2">{{ $entry['actual_surplus'] }}</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table></div>
                                    </details>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Belum ada rekonsiliasi. Konfigurasikan mapping akun dan verifikasi sumber terlebih dahulu.</p>
                            @endforelse
                        </div>
                    </details>
                @endif

                @if($isFinance)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Kebijakan Materialitas dan Pengecualian Terkendali</summary>
                        <p class="text-xs text-gray-500 mt-2">Pengecualian hanya boleh untuk kategori anggaran yang belum dipetakan, bukan untuk jurnal tidak seimbang, transaksi ganda, clearing terbuka, atau eliminasi internal yang bermasalah. Jumlah pengecualian dihitung bruto dan dibatasi oleh batas nominal serta persentase secara bersamaan.</p>
                        @if($isManager)
                            <form wire:submit.prevent="proposeMaterialityPolicy" class="grid md:grid-cols-3 gap-3 mt-4">
                                <div><label class="block text-xs mb-1">Batas absolut (mata uang organisasi)</label>
                                    <input wire:model.defer="materialityAmount" type="number" min="0.01" step="0.01" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                                <div><label class="block text-xs mb-1">Batas relatif (basis poin, 100 = 1%)</label>
                                    <input wire:model.defer="materialityBasisPoints" type="number" min="1" max="10000" class="w-full border rounded-lg p-2 dark:bg-gray-900" /></div>
                                <div class="flex items-end"><button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Ajukan Kebijakan</button></div>
                            </form>
                        @endif
                        <div class="mt-3 space-y-2">
                            @foreach($materialityPolicies as $policy)
                                <div class="border-b dark:border-gray-700 py-2 flex flex-wrap justify-between text-sm gap-2">
                                    <span>v{{ $policy->version }} · batas {{ number_format($policy->absolute_minor / 100, 2) }} · {{ $policy->percentage_bps }} bps · {{ $policy->status }}</span>
                                    @if($isManager && $policy->status === 'draft' && $policy->created_by !== auth()->id())
                                        <button type="button" wire:click="activateMaterialityPolicy({{ $policy->id }})" class="border rounded px-2 py-1">Sahkan Batas</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($unmappedBudgetLines->count() && $cycleCurrent?->status === 'draft')
                            <form wire:submit.prevent="requestMaterialityException" class="grid md:grid-cols-3 gap-3 mt-4">
                                <div class="md:col-span-2"><label class="block text-xs mb-1">Baris anggaran yang belum masuk profil KPI</label>
                                    <select wire:model.defer="exceptionBudgetId" class="w-full border rounded-lg p-2 dark:bg-gray-900">
                                        <option value="">Pilih anggaran</option>
                                        @foreach($unmappedBudgetLines as $line)
                                            <option value="{{ $line->id }}">#{{ $line->id }} · {{ $line->category }} · {{ $line->name }} · {{ $line->amount }}</option>
                                        @endforeach
                                    </select></div>
                                <textarea wire:model.defer="exceptionReason" rows="2" class="border rounded-lg p-2 dark:bg-gray-900" placeholder="Alasan dan bukti pertimbangan minimal 10 karakter"></textarea>
                                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Ajukan Pengecualian</button>
                            </form>
                        @endif
                        <div class="mt-3 space-y-2">
                            @foreach($materialityExceptions as $exception)
                                <div class="border-b dark:border-gray-700 py-2 flex flex-wrap gap-2 justify-between items-center text-sm">
                                    <span>#{{ $exception->id }} · Anggaran #{{ $exception->budget_line_id }} · {{ $exception->amount_minor / 100 }} · {{ $exception->status }} · {{ $exception->reason }}</span>
                                    @if($exception->status === 'requested' && $exception->requested_by !== auth()->id())
                                        <button type="button" wire:click="approveMaterialityException({{ $exception->id }})" class="border rounded px-2 py-1">Sahkan Pengecualian</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                @if($canRaiseDispute)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Sengketa Alokasi Sharing Budget</summary>
                        <p class="text-xs text-gray-500 mt-2">Sengketa terbuka menghentikan closing sampai ada resolusi tercatat. Penanggung jawab unit hanya dapat mengajukan atas unitnya sendiri.</p>
                        @if($cycleCurrent && $cycleCurrent->status === 'draft')
                            <form wire:submit.prevent="raiseSharingDispute" class="grid md:grid-cols-3 gap-3 mt-4">
                                <select wire:model.defer="disputePoolId" class="border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Pilih cost pool</option>
                                    @foreach($disputePools as $pool)<option value="{{ $pool->id }}">{{ $pool->name }}</option>@endforeach
                                </select>
                                <select wire:model.defer="disputeUnitId" class="border rounded-lg p-2 dark:bg-gray-900">
                                    <option value="">Unit terdampak</option>
                                    @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                                </select>
                                <textarea wire:model.defer="disputeReason" rows="2" class="border rounded-lg p-2 dark:bg-gray-900" placeholder="Dasar keberatan dan rujukan bukti"></textarea>
                                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Ajukan Sengketa</button>
                            </form>
                        @endif
                        @if($isFinance)
                            <div class="mt-3"><label class="block text-xs">Resolusi (wajib dicatat sebelum menyelesaikan sengketa)</label>
                                <textarea wire:model.defer="disputeResolution" rows="2" class="w-full border rounded-lg p-2 dark:bg-gray-900"></textarea></div>
                        @endif
                        @foreach($sharingDisputes as $dispute)
                            <div class="border-b dark:border-gray-700 py-3 text-sm">
                                <div class="flex flex-wrap justify-between gap-3"><span>#{{ $dispute->id }} · {{ $dispute->status }} · {{ $dispute->reason }}</span>
                                    @if($isFinance && $dispute->status === 'open' && $dispute->raised_by !== auth()->id())
                                        <button type="button" wire:click="resolveSharingDispute({{ $dispute->id }})" class="border rounded px-3 py-1">Selesaikan</button>
                                    @endif
                                </div>
                                @if($dispute->resolution)<p class="mt-1 text-xs text-gray-500">Resolusi: {{ $dispute->resolution }}</p>@endif
                            </div>
                        @endforeach
                    </details>
                @endif

                @if($isManager)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Kebijakan Approval Closing / Reopening</summary>
                        <p class="text-xs text-gray-500 mt-2">Konfigurasikan 2–5 tingkat persetujuan berdasarkan role organisasi. Pembuat dan pengaktif kebijakan harus berbeda; tahap approval selalu menggunakan versi policy yang dibekukan saat pengajuan.</p>
                        <form wire:submit.prevent="proposeClosingPolicy" class="grid md:grid-cols-3 gap-3 mt-4">
                            <select wire:model.defer="policyAction" class="border rounded-lg p-2 dark:bg-gray-900">
                                <option value="cycle.close">Closing</option><option value="cycle.reopen">Pembukaan kembali</option>
                            </select>
                            <select wire:model.defer="policyRisk" class="border rounded-lg p-2 dark:bg-gray-900">
                                <option value="normal">Normal</option><option value="high">Risiko tinggi</option>
                            </select>
                            <div class="md:col-span-3"><label class="block text-xs mb-1">Tahapan JSON (role dan label)</label>
                                <textarea wire:model.defer="policyStepsJson" rows="3" class="w-full font-mono text-xs border rounded-lg p-2 dark:bg-gray-900" placeholder='[{"label":"Verifikasi","roles":["finance"]},{"label":"Pengesahan","roles":["owner"]}]'></textarea>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Buat Draft Kebijakan</button>
                        </form>
                        @foreach($closingPolicies as $policy)
                            <div class="border-b dark:border-gray-700 py-2 text-sm flex flex-wrap justify-between gap-2">
                                <span>{{ $policy->action }} · risiko {{ $policy->conditions['risk'] ?? '-' }} · v{{ $policy->version }} · {{ $policy->active ? 'Aktif' : 'Draft/nonaktif' }}</span>
                                @if(!$policy->active && $policy->created_by !== auth()->id())
                                    <button type="button" wire:click="activateClosingPolicy({{ $policy->id }})" class="border rounded px-2 py-1">Aktifkan</button>
                                @endif
                            </div>
                        @endforeach
                    </details>
                @endif

                @if($canControlClose)
                    <details class="rounded-xl bg-white dark:bg-gray-800 shadow p-5">
                        <summary class="font-semibold cursor-pointer">Closing, Pembukaan Kembali dan Persetujuan</summary>
                        <p class="text-xs text-gray-500 mt-2">Status siklus: <strong>{{ $cycleCurrent?->status }}</strong>. Closing mewajibkan rekonsiliasi terakhir disahkan tanpa blocker, serta kebijakan approval yang aktif. Snapshot lama tidak boleh ditimpa.</p>
                        <div class="mt-4 space-y-3">
                            <textarea wire:model.defer="cycleApprovalReason" rows="2" class="w-full border rounded-lg p-2 dark:bg-gray-900" placeholder="Alasan dan dasar pengajuan minimal 10 karakter"></textarea>
                            @if($cycleCurrent?->status === 'draft' && $isFinance)
                                <button type="button" wire:click="submitCycleApproval('cycle.close')" class="px-4 py-2 bg-primary-600 text-white rounded-lg">Ajukan Closing Siklus</button>
                            @elseif($cycleCurrent?->status === 'closed' && $isManager)
                                <button type="button" wire:click="submitCycleApproval('cycle.reopen')" class="px-4 py-2 border rounded-lg">Ajukan Pembukaan Kembali</button>
                            @endif
                        </div>
                        <div class="mt-5">
                            <label class="block text-xs mb-1">Alasan penolakan persetujuan (bila diperlukan)</label>
                            <textarea wire:model.defer="approvalDecisionReason" rows="2" class="w-full border rounded-lg p-2 dark:bg-gray-900"></textarea>
                        </div>
                        <div class="space-y-3 mt-4">
                            @foreach($cycleApprovals as $approval)
                                <div class="border dark:border-gray-700 rounded-lg p-3 text-sm">
                                    <div class="flex flex-wrap justify-between gap-2">
                                        <span>#{{ $approval->id }} · {{ $approval->action }} · {{ $approval->status }} · tahap {{ $approval->current_step + 1 }}</span>
                                        @if($approval->status === 'pending' && $approval->requested_by !== auth()->id() &&
                                            ! $approval->decisions()->where('actor_id', auth()->id())->exists())
                                            <span class="flex gap-2">
                                                <button type="button" wire:click="decideCycleApproval({{ $approval->id }}, true)" class="border rounded px-3 py-1">Setujui</button>
                                                <button type="button" wire:click="decideCycleApproval({{ $approval->id }}, false)" class="border rounded px-3 py-1">Tolak</button>
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs mt-2 text-gray-500">{{ $approval->reason }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 space-y-1 text-sm">
                            @foreach($cycleClosings as $closing)
                                <div class="border-b dark:border-gray-700 py-2">
                                    <strong>Snapshot closing v{{ $closing->version }}</strong> · {{ $closing->closed_at }} · hash {{ substr($closing->report_hash, 0, 14) }}
                                </div>
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

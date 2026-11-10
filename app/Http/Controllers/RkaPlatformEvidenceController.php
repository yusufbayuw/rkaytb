<?php

namespace App\Http\Controllers;

use App\Models\RkaPlatform\EvidenceRecord;
use App\Services\RkaPlatform\EvidenceService;
use Illuminate\Http\Request;

class RkaPlatformEvidenceController extends Controller
{
    public function __invoke(Request $request, EvidenceRecord $evidence, EvidenceService $evidenceService)
    {
        return $evidenceService->download($evidence, (int)$request->user()->id);
    }
}

<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Answer, QuestionInstance};

/** Assemble only the latest authorized source answers into an editable draft. No write or approval. */
class SynthesisDraftService
{
    public function __construct(private TenantAccess $access) {}

    public function prepare(QuestionInstance $instance, int $actor): array
    {
        $this->access->requireReadInstance($instance, $actor);
        if ($instance->owner_user_id !== $actor &&
            !$this->access->hasRole($instance->organization_id, $actor, ['owner','admin'])) {
            return ['content' => '', 'answer_ids' => []];
        }
        $answers = Answer::where('organization_id', $instance->organization_id)
            ->where('instance_id', $instance->id)->orderBy('author_id')
            ->orderByDesc('revision')->get()->unique('author_id')->values();
        $content = '';
        $ids = [];
        foreach ($answers as $answer) {
            $segment = '[Sumber jawaban #'.$answer->id.'; revisi '.$answer->revision.']'
                ."\n".trim($answer->content);
            if (mb_strlen($content) + mb_strlen($segment) > 95000) break;
            $ids[] = $answer->id;
            $content .= ($content === '' ? '' : "\n\n").$segment;
        }
        return ['content' => $content, 'answer_ids' => $ids];
    }
}

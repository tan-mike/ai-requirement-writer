<?php

namespace App\Http\Controllers;

use App\Models\DraftScore;
use App\Models\Feature;
use App\Models\RequirementDraft;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequirementDraftController extends Controller
{
    private function checkAccess(Request $request, Feature $feature, ?RequirementDraft $draft = null): void
    {
        $user = $request->user();
        $project = $feature->project;
        
        if ($project->user_id !== $user->id && !$project->is_curated) {
            abort(403, 'Forbidden');
        }

        if ($draft) {
            if ($draft->feature_id !== $feature->id) {
                abort(403, 'Forbidden');
            }

            if ($project->is_curated && $draft->user_id !== $user->id) {
                abort(403, 'Forbidden');
            }
        }
    }

    public function index(Request $request, Feature $feature): JsonResponse
    {
        $this->checkAccess($request, $feature);

        $query = $feature->drafts()->with('critiques.persona')->latest();
        
        if ($feature->project->is_curated) {
            $query->where('user_id', $request->user()->id);
        }

        $drafts = $query->get()->groupBy('type');

        return response()->json(['data' => $drafts]);
    }

    public function show(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        return response()->json(['data' => $draft->load('critiques.persona')]);
    }

    public function update(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $draft->update(['content' => $request->content]);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function approve(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        if ($feature->status === Feature::STATUS_COMPLETED) {
            return response()->json(['message' => 'Cannot approve drafts for a finalized feature.'], 422);
        }

        if ($draft->status === 'approved') {
            return response()->json(['message' => 'Draft has already been approved.'], 422);
        }

        $criteria = config('scoring.criteria');
        $threshold = (int) config('scoring.low_score_threshold', 2);

        $rules = [
            'scores' => ['required', 'array:' . implode(',', array_keys($criteria))],
        ];
        foreach ($criteria as $key => $criterion) {
            $rules["scores.{$key}.score"] = ['required', 'integer', 'min:1', 'max:5'];
            $rules["scores.{$key}.tags"] = ['array'];
            $rules["scores.{$key}.tags.*"] = [Rule::in(array_keys($criterion['tags']))];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $criteria, $threshold) {
            foreach ($criteria as $key => $criterion) {
                $score = $request->input("scores.{$key}.score");
                $tags = $request->input("scores.{$key}.tags", []);

                // The `integer` rule accepts numeric strings (e.g. "2"), so use an
                // integer-like check here rather than is_int() to avoid bypassing the
                // required-tag rule with a numeric-string score.
                if (!is_numeric($score)) {
                    continue;
                }

                $score = (int) $score;

                if ($score <= $threshold && count($tags) === 0) {
                    $validator->errors()->add(
                        "scores.{$key}.tags",
                        "Select at least one issue tag for {$criterion['label']}."
                    );
                }

                if ($score > $threshold && count($tags) > 0) {
                    $validator->errors()->add(
                        "scores.{$key}.tags",
                        "Remove issue tags for {$criterion['label']} when the score is above {$threshold}."
                    );
                }
            }
        });

        $validator->validate();

        $scores = $request->input('scores');

        try {
            DB::transaction(function () use ($draft, $scores, $request) {
                DraftScore::create([
                    'requirement_draft_id' => $draft->id,
                    'user_id' => $request->user()->id,
                    'criterion_scores' => collect($scores)->map(fn ($c) => [
                        'score' => (int) $c['score'],
                        'tags' => $c['tags'] ?? [],
                    ])->all(),
                    'overall_score' => round(collect($scores)->avg(fn ($c) => $c['score']), 1),
                    'recipe_snapshot' => [
                        'lead_persona_id' => $draft->lead_persona_id,
                        'reviewer_persona_ids' => $draft->reviewer_persona_ids,
                        'context_ids' => $draft->context_ids,
                        'model' => $draft->model,
                        'draft_type' => $draft->type,
                        'draft_version' => $draft->version,
                    ],
                ]);

                $draft->approve();
            });
        } catch (QueryException $e) {
            // SQLSTATE class 23 = integrity constraint violation (unique race)
            if (str_starts_with((string) ($e->errorInfo[0] ?? ''), '23')) {
                return response()->json(['message' => 'Draft has already been approved.'], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $draft->fresh()]);
    }
}

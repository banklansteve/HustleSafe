<?php

namespace App\Http\Requests\Portfolio;

use App\Enums\PortfolioStatus;
use App\Enums\QuestStatus;
use App\Models\Portfolio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $portfolio = $this->route('portfolio');

        return $user !== null
            && $user->role?->slug === 'freelancer'
            && $portfolio instanceof Portfolio
            && $user->id === $portfolio->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryId = $this->integer('category_id');
        $portfolio = $this->route('portfolio');
        $portfolioId = $portfolio instanceof Portfolio ? $portfolio->id : 0;

        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:20000'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('quest_categories', 'id')->where(fn ($q) => $q->whereNull('parent_id')->where('is_active', true)),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('quest_categories', 'id')->where(fn ($q) => $q->where('parent_id', $categoryId)->where('is_active', true)),
            ],
            'quest_id' => [
                'nullable',
                'integer',
                Rule::exists('quests', 'id')->where(function ($q) {
                    $q->where('freelancer_id', $this->user()->id)
                        ->whereIn('status', [
                            QuestStatus::Completed,
                            QuestStatus::Archived,
                            QuestStatus::Closed,
                        ]);
                }),
            ],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'project_cost_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'status' => ['required', Rule::enum(PortfolioStatus::class)],
            'files' => ['nullable', 'array', 'max:12'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,pdf'],
            'remove_file_ids' => ['nullable', 'array', 'max:50'],
            'remove_file_ids.*' => [
                'integer',
                Rule::exists('portfolio_files', 'id')->where('portfolio_id', $portfolioId),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => __('category'),
            'subcategory_id' => __('subcategory'),
            'quest_id' => __('quest'),
            'project_cost_minor' => __('project cost'),
        ];
    }
}

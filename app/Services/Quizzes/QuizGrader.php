<?php

namespace App\Services\Quizzes;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Collection;

/**
 * Grades a submitted Attempt.
 *
 * The score, the percentage, and the pass state are calculated here from the
 * stored Options only. No value is ever read from a request.
 */
class QuizGrader
{
    /**
     * @param  array<int, int>  $selected  question id => selected option id
     * @return array{score_points: float, total_points: float, score_percent: float, passed: bool}
     */
    public function grade(Quiz $quiz, array $selected): array
    {
        $questions = $quiz->questions()->with('options')->get()->keyBy('id');

        $score = 0.0;
        $total = 0.0;

        foreach ($questions as $question) {
            $points = (float) $question->points;
            $total += $points;

            $optionId = $selected[$question->id] ?? null;

            if ($optionId === null) {
                continue;
            }

            $option = $question->options->firstWhere('id', (int) $optionId);

            if ($option !== null && $option->is_correct) {
                $score += $points;
            }
        }

        $percent = $total > 0 ? round(($score / $total) * 100, 2) : 0.0;

        return [
            'score_points' => round($score, 2),
            'total_points' => round($total, 2),
            'score_percent' => $percent,
            'passed' => $total > 0 && $percent >= (float) $quiz->passing_score_percent,
        ];
    }

    /**
     * Reject a selection that does not belong to this Quiz.
     *
     * @param  array<int, int>  $selected
     * @return array<int, int> question ids that are invalid
     */
    public function invalidSelections(Quiz $quiz, array $selected): array
    {
        $questions = $quiz->questions()->with('options')->get()->keyBy('id');

        $invalid = [];

        foreach ($selected as $questionId => $optionId) {
            $question = $questions->get((int) $questionId);

            if ($question === null) {
                $invalid[] = (int) $questionId;

                continue;
            }

            $option = $question->options->firstWhere('id', (int) $optionId);

            if ($option === null) {
                $invalid[] = (int) $questionId;
            }
        }

        return $invalid;
    }

    /**
     * Questions a Student may see, with the key and every explanation hidden.
     *
     * @return Collection<int, QuizQuestion>
     */
    public function studentProjection(Quiz $quiz): Collection
    {
        return $quiz->questions()
            ->with('options')
            ->get()
            ->each(function (QuizQuestion $question): void {
                $question->makeHidden(['explanation']);

                $question->options->each(function ($option): void {
                    $option->makeHidden(['is_correct', 'explanation']);
                });
            });
    }
}

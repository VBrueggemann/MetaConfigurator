<?php

namespace App\Actions;

use App\Actions\DTO\AnchorAnalytics;
use App\Actions\DTO\ConfiguratorAnalytics;
use App\Models\AnswerChunk;
use App\Models\Configurator;
use App\Models\View;
use App\Queries\GetRelevantAnswerChunksForConfigurator;

final class BuildConfiguratorAnalytics
{
    private const END_TRANSITION_KEY = '__end__';

    public function __construct(
        private readonly GetRelevantAnswerChunksForConfigurator $getRelevantAnswerChunks,
    ) {}

    public function __invoke(Configurator $configurator): ConfiguratorAnalytics
    {
        $chunksByConfiguration = ($this->getRelevantAnswerChunks)($configurator)
            ->with(['anchorView.templates', 'answers.view.templates'])
            ->orderBy('configuration_id')
            ->orderBy('sequence')
            ->get()
            ->groupBy('configuration_id');

        $anchors = [];
        $minSequenceByAnchor = [];

        foreach ($chunksByConfiguration as $chunks) {
            $chunks = $chunks->values();

            foreach ($chunks as $index => $chunk) {
                $anchorId = $chunk->anchor_view_id;
                $minSequenceByAnchor[$anchorId] = min($minSequenceByAnchor[$anchorId] ?? PHP_INT_MAX, $chunk->sequence);

                $anchors[$anchorId]['view'] ??= $chunk->anchorView;
                $anchors[$anchorId]['visitCount'] = ($anchors[$anchorId]['visitCount'] ?? 0) + 1;

                foreach ($chunk->answers as $answer) {
                    $valueKey = json_encode($answer->value);

                    $anchors[$anchorId]['answers'][$answer->view_id]['view'] ??= $answer->view;
                    $anchors[$anchorId]['answers'][$answer->view_id]['values'][$valueKey]['value'] ??= $answer->value;
                    $anchors[$anchorId]['answers'][$answer->view_id]['values'][$valueKey]['count'] =
                        ($anchors[$anchorId]['answers'][$answer->view_id]['values'][$valueKey]['count'] ?? 0) + 1;
                }

                /** @var ?AnswerChunk $nextChunk */
                $nextChunk = $chunks->get($index + 1);
                $transitionKey = $nextChunk?->anchor_view_id ?? self::END_TRANSITION_KEY;

                $anchors[$anchorId]['transitions'][$transitionKey]['view'] ??= $nextChunk?->anchorView;
                $anchors[$anchorId]['transitions'][$transitionKey]['count'] =
                    ($anchors[$anchorId]['transitions'][$transitionKey]['count'] ?? 0) + 1;
            }
        }

        $anchorAnalytics = collect($anchors)
            ->map(fn (array $data): AnchorAnalytics => $this->buildAnchorAnalytics($data))
            ->sortBy(fn (AnchorAnalytics $anchor): int => $minSequenceByAnchor[$anchor->view->id])
            ->values();

        return new ConfiguratorAnalytics($configurator, $configurator->configurations()->count(), $anchorAnalytics);
    }

    /**
     * @param  array{view: View, visitCount: int, answers?: array<string, array{view: View, values: array<string, array{value: mixed, count: int}>}>, transitions?: array<string, array{view: ?View, count: int}>}  $data
     */
    private function buildAnchorAnalytics(array $data): AnchorAnalytics
    {
        $visitCount = $data['visitCount'];

        $answers = collect($data['answers'] ?? [])
            ->map(fn (array $fieldData): array => [
                'view' => $fieldData['view'],
                'values' => collect($fieldData['values'])
                    ->map(fn (array $valueData): array => [
                        'value' => $valueData['value'],
                        'count' => $valueData['count'],
                        'percentage' => round($valueData['count'] / $visitCount * 100, 1),
                    ])
                    ->sortByDesc('count')
                    ->values(),
            ])
            ->values();

        $transitions = collect($data['transitions'] ?? [])
            ->map(fn (array $transitionData): array => [
                'view' => $transitionData['view'],
                'count' => $transitionData['count'],
                'percentage' => round($transitionData['count'] / $visitCount * 100, 1),
            ])
            ->sortByDesc('count')
            ->values();

        return new AnchorAnalytics($data['view'], $visitCount, $answers, $transitions);
    }
}

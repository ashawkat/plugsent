<?php

namespace App\Http\Controllers\Connector;

use App\Actions\ProcessInventoryResult;
use App\Http\Controllers\Controller;
use App\Models\SiteCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $site = $request->attributes->get('connector.site');

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*.id' => ['required', 'integer'],
            'results.*.status' => ['required', 'in:ok,failed'],
            'results.*.data' => ['nullable', 'array'],
            'results.*.error' => ['nullable', 'string'],
        ]);

        $processed = 0;

        foreach ($validated['results'] as $result) {
            $command = SiteCommand::query()
                ->where('site_id', $site->getKey())
                ->whereKey($result['id'])
                ->first();

            if ($command === null) {
                continue;
            }

            $command->forceFill([
                'status' => $result['status'] === 'ok'
                    ? SiteCommand::STATUS_COMPLETED
                    : SiteCommand::STATUS_FAILED,
                'result' => array_filter([
                    'data' => $result['data'] ?? null,
                    'error' => $result['error'] ?? null,
                ]),
                'completed_at' => now(),
            ])->save();

            if ($command->type === 'inventory.get' && $result['status'] === 'ok') {
                app(ProcessInventoryResult::class)($site, $result['data']['inventory'] ?? []);
            }

            $processed++;
        }

        return response()->json(['processed' => $processed]);
    }
}
